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

function sanitize_input($data)
{
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
        $electricians = [];
        $temp_data = [];

        if (!empty($user_devices)) {
            // Convert comma-separated string to array
            $device_ids_array = explode(',', $user_devices);
            $device_ids_array = array_map('trim', $device_ids_array);

            // 1. Fetch electricians from electrician_devices using device_ids
            $electrician_devices_collection = $user_db_conn->electrician_devices;
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
                    "id" => (string) $document["_id"],
                    'name' => $document['electrician_name'],
                    'phone' => $document['phone_number']
                ];
            }
        }

        $fetched_phone_numbers = []; // to track already fetched

        // 2. If temp_data is still empty, fallback to electricians_list using login_id
        if (empty($temp_data)) {
            $electricians_collection = $user_db_conn->electricians_list;
            $cursor = $electricians_collection->find(['user_login_id' => (int)$user_login_id]);

            foreach ($cursor as $document) {
                $electricians[] = [
                    "id" => (string) $document["_id"],
                    "name" => $document["name"],
                    "phone" => $document["phone_number"]
                ];
                $fetched_phone_numbers[] = $document["phone_number"];
            }
        } else {
            // 3. Get electrician IDs from electricians_list based on name & phone
            $electricians_collection = $user_db_conn->electricians_list;

            foreach ($temp_data as $data) {
                $name = sanitize_input($data['name']);
                $phone = (int)sanitize_input($data['phone']); // Convert to int for MongoDB

                $document = $electricians_collection->findOne([
                    'name' => $name,
                    'phone_number' => $phone
                ]);

                if ($document) {
                    $electricians[] = [
                       "id" => (string) $document["_id"],
                        "name" => $document["name"],
                        "phone" => $document["phone_number"]
                    ];
                    $fetched_phone_numbers[] = $document["phone_number"];
                }
            }

            // 4. Now fetch remaining electricians from list not already fetched
            $query_filter = ['user_login_id' => (int)$user_login_id];

            if (!empty($fetched_phone_numbers)) {
                $query_filter['phone_number'] = ['$nin' => $fetched_phone_numbers];
            }

            $cursor_remaining = $electricians_collection->find($query_filter);

            foreach ($cursor_remaining as $document) {
                $electricians[] = [
                    "id" => (string) $document["_id"],
                    "name" => $document["name"],
                    "phone" => $document["phone_number"]
                ];
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
