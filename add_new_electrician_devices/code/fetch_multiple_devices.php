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

$device_list = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["GROUP_ID"])) {
    $group_id = strtoupper(trim($_POST['GROUP_ID']));
    $selected_phase = strtoupper($_SESSION["SELECTED_PHASE"]);

    try {
        // Determine device name field based on role
        $device_name_field = ($role == "SUPERADMIN") ? "s_device_name" : "c_device_name";
        
        $group_by = "device_group_or_area"; // default

        // Get MongoDB collections
        $user_device_list_collection = $user_db_conn->user_device_list;
        $user_device_group_view_collection = $user_db_conn->user_device_group_view;
        $device_selection_group_collection = $user_db_conn->device_selection_group;
        $electrician_devices_collection = $user_db_conn->electrician_devices;

        // ========== GET ASSIGNED DEVICE IDs ==========
        // First get all device IDs that are already assigned to electricians
        $assigned_cursor = $electrician_devices_collection->find(
            [], 
            ['projection' => ['device_id' => 1]]
        );
        
        $assigned_device_ids = [];
        foreach ($assigned_cursor as $doc) {
            $assigned_device_ids[] = $doc['device_id'];
        }

        if ($group_id === "ALL") {
            // ========== FETCH ALL UNASSIGNED DEVICES ==========
            
            $filter = ['login_id' => (int)$user_login_id];
            
            // Add phase filter if not ALL
            if ($selected_phase !== "ALL") {
                $filter['phase'] = $selected_phase;
            }
            
            // Exclude assigned devices
            if (!empty($assigned_device_ids)) {
                $filter['device_id'] = ['$nin' => $assigned_device_ids];
            }

            // MongoDB pipeline for sorting (equivalent to MySQL REGEXP_REPLACE sorting)
            $pipeline = [
                ['$match' => $filter],
                [
                    '$addFields' => [
                        'device_prefix' => ['$regexFind' => ['input' => '$device_id', 'regex' => '^[A-Za-z_]+']],
                        'device_number' => ['$regexFind' => ['input' => '$device_id', 'regex' => '[0-9]+$']]
                    ]
                ],
                [
                    '$addFields' => [
                        'sort_prefix' => ['$ifNull' => ['$device_prefix.match', '']],
                        'sort_number' => [
                            '$toInt' => [
                                '$ifNull' => ['$device_number.match', '0']
                            ]
                        ]
                    ]
                ],
                ['$sort' => ['sort_prefix' => 1, 'sort_number' => 1]],
                [
                    '$project' => [
                        'device_id' => 1,
                        'device_name' => '$' . $device_name_field
                    ]
                ]
            ];

            $cursor = $user_device_list_collection->aggregate($pipeline);

        } else {
            // ========== HANDLE SPECIFIC GROUP ==========
            
            // Get the group_by column for current login_id
            $group_doc = $device_selection_group_collection->findOne(['login_id' => (int)$user_login_id]);
            if ($group_doc && isset($group_doc['group_by'])) {
                $group_by = $group_doc['group_by'];
            }

            $filter = [
                'login_id' => (int)$user_login_id,
                $group_by => $group_id
            ];
            
            // Add phase filter if not ALL
            if ($selected_phase !== "ALL") {
                $filter['phase'] = $selected_phase;
            }
            
            // Exclude assigned devices
            if (!empty($assigned_device_ids)) {
                $filter['device_id'] = ['$nin' => $assigned_device_ids];
            }

            // MongoDB pipeline for sorting
            $pipeline = [
                ['$match' => $filter],
                [
                    '$addFields' => [
                        'device_prefix' => ['$regexFind' => ['input' => '$device_id', 'regex' => '^[A-Za-z_]+']],
                        'device_number' => ['$regexFind' => ['input' => '$device_id', 'regex' => '[0-9]+$']]
                    ]
                ],
                [
                    '$addFields' => [
                        'sort_prefix' => ['$ifNull' => ['$device_prefix.match', '']],
                        'sort_number' => [
                            '$toInt' => [
                                '$ifNull' => ['$device_number.match', '0']
                            ]
                        ]
                    ]
                ],
                ['$sort' => ['sort_prefix' => 1, 'sort_number' => 1]],
                [
                    '$project' => [
                        'device_id' => 1,
                        'device_name' => '$' . $device_name_field
                    ]
                ]
            ];

            $cursor = $user_device_group_view_collection->aggregate($pipeline);
        }

        // ========== BUILD DEVICE LIST ==========
        foreach ($cursor as $document) {
            $device_list[] = [
                "D_ID" => $document['device_id'], 
                "D_NAME" => $document['device_name']
            ];
        }

        echo json_encode($device_list);

    } catch (MongoDB\Exception\ConnectionTimeoutException $e) {
        echo json_encode(["status" => "error", "message" => "Database connection timeout."]);
    } catch (MongoDB\Exception\RuntimeException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "An error occurred: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}
?>