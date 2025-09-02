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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $group_id = filter_input(INPUT_POST, 'GROUP_ID', FILTER_SANITIZE_STRING);
    $device_status = filter_input(INPUT_POST, 'STATUS', FILTER_SANITIZE_STRING);

    include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");

    if ($user_devices != "") {
        $user_devices = substr($user_devices, 0, -1);
    }

    $user_devices_array = explode(',', $user_devices);
    $user_devices_array = array_map(function ($item) {
        return trim($item, " '\"");
    }, $user_devices_array);
    $user_devices_array = array_filter($user_devices_array);

    if (empty($user_devices_array)) {
        echo json_encode('<tr><td colspan="6" class="text-danger">No devices found for this group</td></tr>');
        exit;
    }

    // Build MongoDB filter base
    $filter = [
        'active_device' => 1,
        'installed_status' => 1,
        'device_id' => ['$in' => $user_devices_array],
    ];

    // Add specific on_off_status condition according to device_status
    switch ($device_status) {
        case "ON_LIGHTS":
            $filter['on_off_status'] = ['$in' => [1, 3, 4]];
            break;
        case "OFF_LIGHTS":
            $filter['on_off_status'] = 0;
            break;
        case "MANUAL_ON":
            $filter['on_off_status'] = 5;
            break;
        default:
            // No additional filter
            break;
    }

    // MongoDB does not support ordering by string length natively,
    // so order by device_id ascending only
    // Sorting by device_id lexically (you may implement client side length sorting if necessary)
    $options = [
        'sort' => ['device_id' => 1],
    ];

    try {
        $collection = $devices_db_conn->live_data_updates;
        $cursor = $collection->find($filter, $options);

        foreach ($cursor as $r) {
            $device_id = $r['device_id'] ?? '';
            $location = $r['location'] ?? '0,0';

            // Format dates
            $frame_date_time = isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime
                ? $r['date_time']->toDateTime()->modify('+5 hours 30 minutes')->format("H:i:s d-m-Y")
                : '';
            $ping_date_time = isset($r['ping_time']) && $r['ping_time'] instanceof MongoDB\BSON\UTCDateTime
                ? $r['ping_time']->toDateTime()->modify('+5 hours 30 minutes')->format("H:i:s d-m-Y")
                : '';

            // Handle location and address
            if ($location != '0,0' && strpos($location, "0000000,000000") === false) {
                $address = '<a href="#" class="pt-0 pb-0" onclick="show_location(\'' . $location . '\')">Map</a>';
            } else {
                $address = '<button class="address_update btn btn-primary pt-0 pb-0" onclick="address_update(\'' . htmlspecialchars($device_id) . '\')">Update</button>';
            }

            // Map device name from device_list if available
            $name = htmlspecialchars($device_id);
            foreach ($device_list as $device) {
                if (trim($device_id) === ($device['D_ID'] ?? '')) {
                    $name = htmlspecialchars($device['D_NAME'] ?? $device_id);
                    break;
                }
            }

            // Determine button classes per device status
            switch ($device_status) {
                case "ON_LIGHTS":
                    $btnClass = 'btn-info';
                    break;
                case "OFF_LIGHTS":
                    $btnClass = 'btn-info';
                    break;
                case "MANUAL_ON":
                    $btnClass = 'btn-info';
                    break;
                default:
                    $btnClass = '';
                    break;
            }

            $return_response .= '<tr>
                <td>' . htmlspecialchars($device_id) . '</td>
                <td>' . $name . '</td>
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
