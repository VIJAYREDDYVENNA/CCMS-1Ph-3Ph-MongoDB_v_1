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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["group_id"])) {
    $group_id = $_POST['group_id'];
    
    // Get pagination parameters
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
    $skip = ($page - 1) * $limit;
    
    include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");
    
    $user_devices_array = [];
    if (!empty($user_devices)) {
        // remove leading/trailing quotes and split by "','"
        $user_devices_raw = trim($user_devices, "'");
        $user_devices_array = array_map('trim', explode("','", $user_devices_raw));
    }

    try {
        $electricians = [];
        $unassigned_devices = [];
        $group_areas = [];
        $group_by = null;
        $total_count = 0;
        
        if ($group_id === "ALL") {
            // Get total count first
            if (!empty($user_devices_array)) {
                $total_count = $user_db_conn->electrician_devices->countDocuments([
                    'device_id' => ['$in' => $user_devices_array]
                ]);

                // Fetch electricians with pagination
                $electricians_cursor = $user_db_conn->electrician_devices->find([
                    'device_id' => ['$in' => $user_devices_array]
                ], [
                    'skip' => $skip,
                    'limit' => $limit
                ]);

                foreach ($electricians_cursor as $doc) {
                    $electricians[] = [
                        "id" => (string)$doc['_id'],
                        "name" => $doc["electrician_name"] ?? '',
                        "phone" => $doc["phone_number"] ?? '',
                        "device_id" => $doc["device_id"] ?? ''
                    ];
                }
            }

            // Assigned devices for this user (for unassigned devices calculation)
            $assigned_devices_cursor = $user_db_conn->electrician_devices->find([
                'user_login_id' => (int)$user_login_id
            ], [
                'projection' => ['device_id' => 1]
            ]);

            $assigned_device_ids = [];
            foreach ($assigned_devices_cursor as $doc) {
                $assigned_device_ids[] = $doc['device_id'];
            }

            // Unassigned devices
            $pipeline = [
                [
                    '$match' => [
                        'device_id' => ['$nin' => $assigned_device_ids]
                    ]
                ],
                [
                    '$group' => [
                        '_id' => [
                            'device_id' => '$device_id',
                            'c_device_name' => '$c_device_name'
                        ]
                    ]
                ],
                [
                    '$project' => [
                        '_id' => 0,
                        'device_id' => '$_id.device_id',
                        'c_device_name' => '$_id.c_device_name'
                    ]
                ]
            ];

            $unassigned_cursor = $user_db_conn->user_device_list->aggregate($pipeline);
            foreach ($unassigned_cursor as $doc) {
                $unassigned_devices[] = [
                    "device_id" => $doc["device_id"] ?? '',
                    "device_name" => $doc["c_device_name"] ?? 'Unknown'
                ];
            }
            
        } else {
            // Get the group_by value
            $group_doc = $user_db_conn->device_selection_group->findOne([
                'login_id' => (int)$user_login_id
            ]);
            if ($group_doc) {
                $group_by = $group_doc['group_by'];
            }

            $field_mapping = [
                "state" => "state",
                "district" => "district", 
                "city_or_town" => "city_or_town"
            ];

            if ($group_by && $group_by !== "device_group_or_area" && isset($field_mapping[$group_by])) {
                $pipeline = [
                    ['$match' => [$field_mapping[$group_by] => $group_id]],
                    ['$group' => ['_id' => '$device_group_or_area']],
                    ['$project' => ['_id' => 0, 'device_group_or_area' => '$_id']]
                ];

                $areas_cursor = $user_db_conn->user_device_group_view->aggregate($pipeline);
                foreach ($areas_cursor as $doc) {
                    $group_areas[] = $doc['device_group_or_area'];
                }
            }

            // Build electrician filter
            $electrician_filter = [];
            if (!empty($user_devices_array)) {
                $electrician_filter['device_id'] = ['$in' => $user_devices_array];
            }
            $electrician_filter['group_area'] = !empty($group_areas) ? ['$in' => $group_areas] : $group_id;

            // Get total count
            $total_count = $user_db_conn->electrician_devices->countDocuments($electrician_filter);

            // Fetch electricians for this group with pagination
            if (!empty($user_devices_array)) {
                $electricians_cursor = $user_db_conn->electrician_devices->find($electrician_filter, [
                    'skip' => $skip,
                    'limit' => $limit
                ]);

                foreach ($electricians_cursor as $doc) {
                    $electricians[] = [
                        "id" => (string)$doc['_id'],
                        "name" => $doc["electrician_name"] ?? '',
                        "phone" => $doc["phone_number"] ?? '',
                        "device_id" => $doc["device_id"] ?? ''
                    ];
                }
            }

            // Assigned devices
            $device_filter = [];
            $electrician_filter_for_assigned = ['user_login_id' => (int)$user_login_id];
            if (!empty($group_areas)) {
                $device_filter['device_group_or_area'] = ['$in' => $group_areas];
                $electrician_filter_for_assigned['group_area'] = ['$in' => $group_areas];
            } else {
                $device_filter['device_group_or_area'] = $group_id;
                $electrician_filter_for_assigned['group_area'] = $group_id;
            }

            $assigned_devices_cursor = $user_db_conn->electrician_devices->find(
                $electrician_filter_for_assigned,
                ['projection' => ['device_id' => 1]]
            );

            $assigned_device_ids = [];
            foreach ($assigned_devices_cursor as $doc) {
                $assigned_device_ids[] = $doc['device_id'];
            }

            if (!empty($assigned_device_ids)) {
                $device_filter['device_id'] = ['$nin' => $assigned_device_ids];
            }

            $pipeline = [
                ['$match' => $device_filter],
                ['$group' => ['_id' => ['device_id' => '$device_id', 'c_device_name' => '$c_device_name']]],
                ['$project' => ['_id' => 0, 'device_id' => '$_id.device_id', 'c_device_name' => '$_id.c_device_name']]
            ];

            $unassigned_cursor = $user_db_conn->user_device_group_view->aggregate($pipeline);
            foreach ($unassigned_cursor as $doc) {
                $unassigned_devices[] = [
                    "device_id" => $doc["device_id"] ?? '',
                    "device_name" => $doc["c_device_name"] ?? 'Unknown'
                ];
            }
        }

        // SIMPLE RESPONSE - ONLY ELECTRICIANS AND BASIC PAGINATION INFO
        echo json_encode([
            "electricians" => $electricians,
            "total_count" => $total_count,
            "current_page" => $page,
            "items_per_page" => $limit,
            "total_pages" => ceil($total_count / $limit)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            "status" => "error", 
            "message" => "Database operation failed: " . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([]);
}
?>