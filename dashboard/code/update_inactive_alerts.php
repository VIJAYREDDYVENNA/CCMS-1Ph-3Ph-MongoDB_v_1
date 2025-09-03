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

$return_response = "";
$user_devices = "";
$device_list = array();
$total_switch_point = 0;


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["GROUP_ID"])) {
    $group_id = $_POST['GROUP_ID']; 

    include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");
    if ($user_devices != "") {
        $user_devices = substr($user_devices, 0, -1);
    }

// Clean devices array
    $user_devices_array = explode(',', $user_devices);
    $user_devices_array = array_map(function ($item) {
        return trim(trim($item, "'"));
    }, $user_devices_array);
    $user_devices_array = array_filter($user_devices_array);

    if (empty($user_devices_array)) {
        echo json_encode(['error' => 'No devices found for this group']);
        exit;
    }

    try {
    // Setup MongoDB connections
    // $client initialized in config.php


    // Step 1: Fetch relevant alerts from devices DB
        $pipeline = [
            [
                '$match' => [
                    'device_id' => ['$in' => array_values($user_devices_array)],
                    'installed_status' => 1,
                    '$or' => [
                        ['poor_network' => 1],
                        ['power_failure' => 1],
                        ['faulty' => 1]
                    ]
                ]
            ],
            [
                '$sort' => ['date_time' => -1]
            ],
            [
                '$limit' => 100
            ]
        ];

        $alerts_cursor = $devices_db_conn->live_data_updates->aggregate($pipeline);
        $alerts = iterator_to_array($alerts_cursor, false);

    // Step 2: Collect device_ids needing electrician lookup
        $device_ids = [];
        foreach ($alerts as $row) {
            $device_ids[] = $row['device_id'];
        }
        $device_ids = array_unique($device_ids);

    // Step 3: Fetch electricians for these device_ids from user DB
        $electrician_map = [];
        if (!empty($device_ids)) {
            $electricians_cursor = $user_db_conn->electricians_list->find([
                'device_id' => ['$in' => $device_ids]
            ]);
            foreach ($electricians_cursor as $electr) {
                $device_id = is_array($electr['device_id']) ? (string)$electr['device_id'] : $electr['device_id'];
                $electrician_map[$device_id] = $electr;
            }
        }

    // Step 4: Render results
        $found = false;
        foreach ($alerts as $row) {
            $found = true;
            $device_id = htmlspecialchars($row['device_id']);

        // Convert MongoDB\BSON\UTCDateTime to readable format
            $date_time = "";
            if (isset($row['date_time'])) {
                if ($row['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                    $dt = $row['date_time']->toDateTime();
                } else {
                    $dt = new DateTime($row['date_time']);
                }
                $date_time = $dt->modify('+5 hours 30 minutes')->format("H:i:s d-m-Y");
            }
          /*  if (isset($row['date_time']) && $row['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                $date_time = $row['date_time']->toDateTime()->modify('+5 hours 30 minutes')->format("Y-m-d H:i:s");
            }*/

        // Detect alert category
            if (!empty($row['power_failure']) && (int)$row['power_failure'] === 1) {
                $category = 'power_failure';
            } elseif (!empty($row['faulty']) && (int)$row['faulty'] === 1) {
                $category = 'faulty';
            } elseif (!empty($row['poor_network']) && (int)$row['poor_network'] === 1) {
                $category = 'poor_network';
            } else {
                $category = '';
            }

        // Category icon, label, class
            $status_icon = '';
            $status_class = '';
            $status_text = '';
            switch ($category) {
                case 'power_failure':
                $status_icon = 'bi-power';
                $status_class = 'text-danger';
                $status_text = 'Power Failure';
                break;
                case 'faulty':
                $status_icon = 'bi-exclamation-triangle';
                $status_class = 'text-warning';
                $status_text = 'Faulty';
                break;
                case 'poor_network':
                $status_icon = 'bi-wifi-off';
                $status_class = 'text-secondary';
                $status_text = 'Poor Network';
                break;
            }

        // Fetch electrician details via the PHP map
            $electr = $electrician_map[$row['device_id']] ?? null;
            $electrician_name = $electr['electrician_name'] ?? 'Not Assigned';
            $phone_number     = $electr['phone_number'] ?? '';

            $return_response .= '<div class="alert-item mb-1 p-1 border rounded" style="font-size: 0.8rem;">
            <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="fw-bold" style="font-size: 0.75rem;">
            <i class="bi bi-cpu text-primary" style="font-size: 0.7rem;"></i>
            ' . $device_id . '
            </span>
            <span class="' . $status_class . '" style="font-size: 0.7rem;">
            <i class="' . $status_icon . '" style="font-size: 0.65rem;"></i>
            ' . $status_text . '
            </span>                        
            </div>

            <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center" style="font-size: 0.7rem;">
            <span class="me-2">
            <i class="bi bi-person" style="font-size: 0.65rem;"></i>
            ' . $electrician_name . '
            </span>
            <a href="tel:' . $phone_number . '" class="text-decoration-none" style="font-size: 0.7rem;">
            <i class="bi bi-telephone" style="font-size: 0.65rem;"></i>
            ' . $phone_number . '
            </a>
            </div>
            <small class="text-muted" style="font-size: 0.65rem;">
            <i class="bi bi-clock" style="font-size: 0.6rem;"></i>
            ' . $date_time . '
            </small>
            </div>';

            $return_response .= '</div>';
        }

        if (!$found) {
            $return_response = '<div class="alert alert-success p-2" style="font-size: 0.8rem;">
            <i class="bi bi-check-circle" style="font-size: 0.75rem;"></i>
            All devices in this group are active and functioning normally.
            </div>';
        }

    } catch (Exception $e) {
        error_log("Error in device status check: " . $e->getMessage());
        $return_response = '<div class="alert alert-danger p-2" style="font-size: 0.8rem;">
        <i class="bi bi-exclamation-triangle" style="font-size: 0.75rem;"></i>
        Error loading device status. Please try again.
        </div>';
    }

    echo json_encode($return_response);

} else {
    echo json_encode(['error' => 'Invalid request method or missing GROUP_ID']);
}
