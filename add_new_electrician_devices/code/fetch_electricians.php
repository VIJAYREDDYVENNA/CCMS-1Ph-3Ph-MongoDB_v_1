<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();
$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];
$user_devices = "";
header('Content-Type: application/json');

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["group_id"])) {
    $group_id = $_POST['group_id'];
    include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");

    if ($user_devices != "") {
        $user_devices = rtrim($user_devices, ",");
    }

    try {
        // MongoDB database and collections
        // $user_db_conn = $client->ccms_user_db;
        $electrician_devices_collection = $user_db_conn->electrician_devices;
        $electricians_list_collection = $user_db_conn->electricians_list;

        $electricians = [];
        $temp_data = [];

        if (!empty($user_devices)) {
            // Convert MySQL IN clause format to MongoDB array
            $user_devices_clean = str_replace("'", "", $user_devices);
            $device_ids_array = array_map('trim', explode(',', $user_devices_clean));

            // 1. Fetch electricians from electrician_devices using device_ids
            try {
                // Use MongoDB aggregation to get distinct combinations
                $pipeline = [
                    [
                        '$match' => [
                            'device_id' => ['$in' => $device_ids_array]
                        ]
                    ],
                    [
                        '$group' => [
                            '_id' => [
                                'electrician_name' => '$electrician_name',
                                'phone_number' => '$phone_number'
                            ],
                            'electrician_name' => ['$first' => '$electrician_name'],
                            'phone_number' => ['$first' => '$phone_number']
                        ]
                    ],
                    [
                        '$project' => [
                            '_id' => 0,
                            'electrician_name' => 1,
                            'phone_number' => 1
                        ]
                    ]
                ];

                $cursor = $electrician_devices_collection->aggregate($pipeline);
                
                foreach ($cursor as $document) {
                    if (isset($document['electrician_name']) && isset($document['phone_number'])) {
                        $temp_data[] = [
                            'name' => $document['electrician_name'],
                            'phone' => $document['phone_number']
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("Error in electrician_devices query: " . $e->getMessage());
            }
        }

        $fetched_phone_numbers = []; // to track already fetched

        // 2. If temp_data is still empty, fallback to electricians_list using login_id
        if (empty($temp_data)) {
            try {
                // Try both integer and string versions of user_login_id
                $cursor = $electricians_list_collection->find([
                    '$or' => [
                        ['user_login_id' => (int)$user_login_id],
                        ['user_login_id' => (string)$user_login_id]
                    ]
                ]);

                foreach ($cursor as $document) {
                    $electricians[] = [
                        "id" => isset($document["id"]) ? $document["id"] : (string)$document["_id"],
                        "name" => $document["name"] ?? "",
                        "phone" => $document["phone_number"] ?? ""
                    ];
                    $fetched_phone_numbers[] = $document["phone_number"] ?? "";
                }
            } catch (Exception $e) {
                error_log("Error in fallback query: " . $e->getMessage());
            }
        } else {
            // 3. Get electrician IDs from electricians_list based on name & phone
            foreach ($temp_data as $data) {
                $name = sanitize_input($data['name']);
                $phone = sanitize_input($data['phone']);

                try {
                    $document = $electricians_list_collection->findOne([
                        'name' => $name,
                        'phone_number' => $phone
                    ]);

                    if ($document) {
                        $electricians[] = [
                            "id" => isset($document["id"]) ? $document["id"] : (string)$document["_id"],
                            "name" => $document["name"],
                            "phone" => $document["phone_number"]
                        ];
                        $fetched_phone_numbers[] = $document["phone_number"];
                    }
                } catch (Exception $e) {
                    error_log("Error finding electrician by name/phone: " . $e->getMessage());
                }
            }

            // 4. Now fetch remaining electricians from list not already fetched
            try {
                $filter = [
                    '$or' => [
                        ['user_login_id' => (int)$user_login_id],
                        ['user_login_id' => (string)$user_login_id]
                    ]
                ];
                
                if (!empty($fetched_phone_numbers)) {
                    $filter['phone_number'] = ['$nin' => $fetched_phone_numbers];
                }

                $cursor = $electricians_list_collection->find($filter);

                foreach ($cursor as $document) {
                    $electricians[] = [
                        "id" => isset($document["id"]) ? $document["id"] : (string)$document["_id"],
                        "name" => $document["name"],
                        "phone" => $document["phone_number"]
                    ];
                }
            } catch (Exception $e) {
                error_log("Error fetching remaining electricians: " . $e->getMessage());
            }
        }

        echo json_encode($electricians);
        
    } catch (Exception $e) {
        error_log("MongoDB connection error: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    }
} else {
    echo json_encode([]);
}
?>