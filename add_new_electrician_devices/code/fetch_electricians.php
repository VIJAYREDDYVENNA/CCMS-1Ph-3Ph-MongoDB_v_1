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
        // Get MongoDB collections
        $electrician_devices_collection = $user_db_conn->electrician_devices;
        $electricians_list_collection = $user_db_conn->electricians_list;

        $electricians = [];
        $temp_data = [];

        // ========== SUPERADMIN CONDITION ==========
        if ($role === 'SUPERADMIN') {
            // Fetch all electricians from electricians_list collection for superadmin
            $cursor = $electricians_list_collection->find([]);

            foreach ($cursor as $document) {
                $electricians[] = [
                    "id" => (string)$document["_id"],
                    "name" => $document["name"],
                    "phone" => $document["phone_number"]
                ];
            }
        } else {
            // ========== ORIGINAL LOGIC FOR NON-SUPERADMIN USERS ==========
            if (!empty($user_devices)) {
                // Convert comma-separated string to array
                $device_ids_array = explode(',', $user_devices);
                $device_ids_array = array_map('trim', $device_ids_array);

                // ========== STEP 1: FETCH ELECTRICIANS FROM ELECTRICIAN_DEVICES ==========
                $cursor = $electrician_devices_collection->aggregate([
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
                            ]
                        ]
                    ],
                    [
                        '$project' => [
                            '_id' => 0,
                            'electrician_name' => '$_id.electrician_name',
                            'phone_number' => '$_id.phone_number'
                        ]
                    ]
                ]);

                foreach ($cursor as $document) {
                    $temp_data[] = [
                        "id" =>  (string)$document["_id"],
                        'name' => $document['electrician_name'],
                        'phone' => $document['phone_number']
                    ];
                }
            }

            $fetched_phone_numbers = []; // to track already fetched

            // ========== STEP 2: FALLBACK TO ELECTRICIANS_LIST IF NO DATA ==========
            if (empty($temp_data)) {
                $cursor = $electricians_list_collection->find(['user_login_id' => (int)$user_login_id]);

                foreach ($cursor as $document) {
                    $electricians[] = [
                        "id" =>  (string)$document["_id"],
                        "name" => $document["name"],
                        "phone" => $document["phone_number"]
                    ];
                    $fetched_phone_numbers[] = $document["phone_number"];
                }
            } else {
                // ========== STEP 3: GET ELECTRICIAN IDs FROM ELECTRICIANS_LIST ==========
                foreach ($temp_data as $data) {
                    $name = sanitize_input($data['name']);
                    $phone = (int)sanitize_input($data['phone']); // Convert to int for MongoDB

                    $document = $electricians_list_collection->findOne([
                        'name' => $name,
                        'phone_number' => $phone
                    ]);

                    if ($document) {
                        $electricians[] = [
                            "id" =>  (string)$document["_id"],
                            "name" => $document["name"],
                            "phone" => $document["phone_number"]
                        ];
                        $fetched_phone_numbers[] = $document["phone_number"];
                    }
                }

                // ========== STEP 4: FETCH REMAINING ELECTRICIANS NOT ALREADY FETCHED ==========
                $query_filter = ['user_login_id' => (int)$user_login_id];
                
                if (!empty($fetched_phone_numbers)) {
                    $query_filter['phone_number'] = ['$nin' => $fetched_phone_numbers];
                }

                $cursor_remaining = $electricians_list_collection->find($query_filter);

                foreach ($cursor_remaining as $document) {
                    $electricians[] = [
                        "_id" => (string)$document["_id"],
                        "id" => $document["id"],
                        "name" => $document["name"],
                        "phone" => $document["phone_number"]
                    ];
                }
            }
        }

        echo json_encode($electricians);

    } catch (MongoDB\Exception\ConnectionTimeoutException $e) {
        echo json_encode(["status" => "error", "message" => "Database connection timeout."]);
    } catch (MongoDB\Exception\RuntimeException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "An error occurred: " . $e->getMessage()]);
    }
} else {
    echo json_encode([]);
}
?>