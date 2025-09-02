<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

// Check session and retrieve session variables
SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();
$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];

$return_response = "";
$user_devices = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $group_id = filter_input(INPUT_POST, 'GROUP_ID', FILTER_SANITIZE_STRING);
    $device_status = filter_input(INPUT_POST, 'STATUS', FILTER_SANITIZE_STRING);

    include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");

    if ($user_devices != "") {
        $user_devices = substr($user_devices, 0, -1);
    }

    $user_devices_array = explode(',', $user_devices);
    $user_devices_array = array_map(function ($item) {
        return trim(trim($item, "'"));
    }, $user_devices_array);
    $user_devices_array = array_filter($user_devices_array);

    if (empty($user_devices_array)) {
        echo json_encode('<tr><td colspan="6" class="text-danger">No devices found for this group</td></tr>');
        exit;
    }

    // Build MongoDB filter
    $filter = [
        'installed_status' => 1,
        'device_id' => ['$in' => $user_devices_array],
    ];

    // Add condition according to $device_status
    switch ($device_status) {
        case "ACTIVE_DEVICES":
            $filter['active_device'] = 1;
            break;
        case "POOR_NW_DEVICES":
            $filter['poor_network'] = 1;
            break;
        case "POWER_FAIL_DEVICES":
            $filter['power_failure'] = 1;
            break;
        case "FAULTY_DEVICES":
            $filter['faulty'] = 1;
            break;
        default:
            // No extra filter
            break;
    }

    $options = [
        'sort' => ['date_time' => -1],
    ];

    try {
        $collection = $devices_db_conn->live_data_updates;
        $cursor = $collection->find($filter, $options);

        foreach ($cursor as $r) {
            $device_id = $r['device_id'] ?? '';
            $installed_date = '';
            $frame_date_time = '';
            $ping_date_time = '';
            $location = $r['location'] ?? '0,0';

            if (isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                $frame_date_time = $r['date_time']->toDateTime()->modify('+5 hours 30 minutes')->format("H:i:s d-m-Y");
            }
            if (isset($r['ping_time']) && $r['ping_time'] instanceof MongoDB\BSON\UTCDateTime) {
                $ping_date_time = $r['ping_time']->toDateTime()->modify('+5 hours 30 minutes')->format("H:i:s d-m-Y");
            }
            if (isset($r['installed_date']) && $r['installed_date'] instanceof MongoDB\BSON\UTCDateTime) {
                $installed_date = $r['installed_date']->toDateTime()->format("Y-m-d");
            }

            // Handle location link
            if ($location != '0,0' && strpos($location, "0000000,000000") === false) {
                $address = '<a href="#" class="pt-0 pb-0" onclick="show_location(\'' . $location . '\')">Map</a>';
            } else {
                $address = '<a href="location-details.php?id=' . htmlspecialchars($device_id) . '" target="_blank"><button class="btn btn-primary pt-0 pb-0">Update</button></a>';
            }

            // Assume $device_list is populated somewhere else, map device name
            $name = $device_id;
            foreach ($device_list as $device) {
                if (trim($device_id) === ($device['D_ID'] ?? '')) {
                    $name = $device['D_NAME'] ?? $device_id;
                    break;
                }
            }

            // Compose table row HTML according to device status
            switch ($device_status) {
                case "ACTIVE_DEVICES":
                    $btnClass = 'text-success bg-success-subtle';
                    break;
                case "POOR_NW_DEVICES":
                    $btnClass = 'btn-warning';
                    break;
                case "FAULTY_DEVICES":
                    $btnClass = 'text-danger bg-danger-subtle';
                    break;
                case "POWER_FAIL_DEVICES":
                    $btnClass = 'bg-secondary-subtle';
                    break;
                default:
                    $btnClass = '';
                    break;
            }

            $return_response .= '<tr>
                <td>' . htmlspecialchars($device_id) . '</td>
                <td>' . htmlspecialchars($name) . '</td>
                <td class="col-size-1">' . $frame_date_time . '</td>
                <td class="col-size-1">' . $ping_date_time . '</td>
                <td><button class="btn fw-semibold ' . $btnClass . ' btn-sm p-0 px-2" onclick="openOpenviewModal(\'' . htmlspecialchars($device_id) . '\')">View</button></td>
                <td>' . $address . '</td>
            </tr>';
        }

        if (empty($return_response)) {
            $return_response = '<tr><td colspan="6" class="text-danger">Devices Not Found</td></tr>';
        }
    } catch (Exception $e) {
        error_log('Error fetching devices: ' . $e->getMessage());
        $return_response = '<tr><td colspan="6" class="text-danger">Error loading devices</td></tr>';
    }
} else {
    $return_response = '<tr><td colspan="6" class="text-danger">Input Data Not Valid</td></tr>';
}

echo json_encode($return_response);
