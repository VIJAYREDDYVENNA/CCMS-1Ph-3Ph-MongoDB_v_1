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
$total_switch_point = 0;
$user_devices = "";
$add_response = "";
$group_id = "ALL";
$device_status = "ALL";
$page = 1;
$items_per_page = 20;
$total_records = 0;
$total_pages = 0;

function sendResponse($success, $data = "", $message = "", $totalRecords = 0, $totalPages = 0, $currentPage = 1) {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'totalRecords' => $totalRecords,
        'totalPages' => $totalPages,
        'currentPage' => $currentPage
    ]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $group_id = filter_input(INPUT_POST, 'GROUP_ID', FILTER_SANITIZE_STRING);
    $device_status = filter_input(INPUT_POST, 'STATUS', FILTER_SANITIZE_STRING);
    $page = filter_input(INPUT_POST, 'PAGE', FILTER_VALIDATE_INT) ?: 1;
    $items_per_page = filter_input(INPUT_POST, 'ITEMS_PER_PAGE', FILTER_VALIDATE_INT) ?: 20;

    // Ensure page is at least 1
    $page = max(1, $page);
    $items_per_page = max(1, min(500, $items_per_page)); // Limit max items per page

    include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");

    if ($user_devices != "") {
        $user_devices = rtrim($user_devices, ',');
    }

    // Prepare list of device IDs
    $user_devices_array = array_filter(array_map(function ($item) {
        return trim(trim($item, "'"));
    }, explode(',', $user_devices)));

    if (empty($user_devices_array)) {
        sendResponse(false, '<tr><td colspan="6" class="text-danger">No devices found for the group</td></tr>', 'No devices found for the group');
    }

    try {
        $collection = $devices_db_conn->live_data_updates;

        // Find all devices in the list to identify available devices
        $filter_all = ['device_id' => ['$in' => $user_devices_array]];
        $cursor_all = $collection->find($filter_all);

        $available_devices = [];
        foreach ($cursor_all as $doc) {
            $available_devices[] = $doc['device_id'];
        }

        // Find devices not available (in user_devices_array but not in MongoDB)
        $not_available_devices = array_diff($user_devices_array, $available_devices);

        // Count not available devices for pagination calculation
        $not_available_count = count($not_available_devices);

        // Prepare filter for main query based on device_status
        $filter = ['device_id' => ['$in' => $user_devices_array]];
        if ($device_status == "INSTALLED") {
            $filter['installed_status'] = 1;
        } elseif ($device_status == "NOTINSTALLED") {
            $filter['installed_status'] = 0;
        }

        // Get total count for pagination
        $available_count = $collection->countDocuments($filter);
        
        // Calculate total records based on status
        if ($device_status == "ALL") {
            $total_records = $available_count + $not_available_count;
        } elseif ($device_status == "NOTINSTALLED") {
            // For not installed, we need to count both MongoDB not installed + not available devices
            $total_records = $collection->countDocuments(['device_id' => ['$in' => $user_devices_array], 'installed_status' => 0]) + $not_available_count;
        } else {
            $total_records = $available_count;
        }

        $total_pages = ceil($total_records / $items_per_page);

        // Calculate skip value for pagination
        $skip = ($page - 1) * $items_per_page;

        // Sorting and pagination options
        $options = [
            'sort' => ['device_id' => 1],
            'skip' => $skip,
            'limit' => $items_per_page
        ];

        // Handle pagination for different scenarios
        $mongodb_data = [];
        $not_available_to_show = [];

        if ($device_status == "ALL" || $device_status == "NOTINSTALLED") {
            // For ALL and NOTINSTALLED, we need to handle both MongoDB data and not available devices
            
            if ($skip < $available_count) {
                // We need some data from MongoDB
                $mongodb_limit = min($items_per_page, $available_count - $skip);
                $options['limit'] = $mongodb_limit;
                
                $cursor = $collection->find($filter, $options);
                foreach ($cursor as $doc) {
                    $mongodb_data[] = $doc;
                }
            }

            // Check if we need to show some not available devices
            $mongodb_data_count = count($mongodb_data);
            if ($mongodb_data_count < $items_per_page && ($skip + $mongodb_data_count) < $total_records) {
                $not_available_skip = max(0, $skip - $available_count);
                $not_available_limit = $items_per_page - $mongodb_data_count;
                $not_available_to_show = array_slice($not_available_devices, $not_available_skip, $not_available_limit);
            }
        } else {
            // For INSTALLED, only MongoDB data
            $cursor = $collection->find($filter, $options);
            foreach ($cursor as $doc) {
                $mongodb_data[] = $doc;
            }
        }

        // Generate HTML for MongoDB data
        foreach ($mongodb_data as $r) {
            $device_id = $r['device_id'] ?? '';
            $name = $device_id;
            foreach ($device_list as $device) {
                $c_id = $device['D_ID'] ?? '';
                if (trim($device_id) === $c_id) {
                    $name = $device['D_NAME'] ?? $device_id;
                    break;
                }
            }

            // Convert and add +5:30 hours to date_time
            if (isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                $date = $r['date_time']->toDateTime();
                $date->modify('+5 hours 30 minutes');
                $frame_date_time = $date->format("H:i:s d-m-Y");
            } else {
                $frame_date_time = "--";
            }

            // installed_date to string or --
            if (isset($r['installed_date']) && $r['installed_date'] instanceof MongoDB\BSON\UTCDateTime) {
                $installed_date = $r['installed_date']->toDateTime()->format("Y-m-d");
            } else {
                $installed_date = "--";
            }

            $status = $r['active_device'] ?? 0;
            $location = $r['location'] ?? '0,0';
            if ($location != '0,0' && strpos($location, "0000000,000000") === false) {
                $address = '<a href="#" class="pt-0 pb-0" onclick="show_location(\'' . $location . '\')">Map</a>';
            } else {
                $address = '<a href="location-details.php?id=' . htmlspecialchars($device_id) . '" target="_blank"><button class="btn btn-primary pt-0 pb-0">Update</button></a>';
            }

            $installation_status = ($r['installed_status'] ?? 0) == 1 ? "Installed" : "Not Installed";

            if (($r['installed_status'] ?? 0) == 1) {
                $return_response .= '<tr>
                    <td><input type="checkbox" name="selectedDevice" value="' . htmlspecialchars($device_id) . '"></td>
                    <td>' . htmlspecialchars($device_id) . '</td>
                    <td>' . htmlspecialchars($name) . '</td>
                    <td class="text-success fw-semibold">' . $installation_status . '</td>
                    <td>' . $installed_date . '</td>
                    <td>' . $address . '</td>
                </tr>';
            } else {
                if ($device_status == "NOTINSTALLED") {
                    $return_response .= '<tr>
                        <td><input type="checkbox" name="selectedDevice" value="' . htmlspecialchars($device_id) . '"></td>
                        <td>' . htmlspecialchars($device_id) . '</td>
                        <td>' . htmlspecialchars($name) . '</td>
                    </tr>';
                } else {
                    $return_response .= '<tr>
                        <td><input type="checkbox"  name="selectedDevice" value="' . htmlspecialchars($device_id) . '"></td>
                        <td>' . htmlspecialchars($device_id) . '</td>
                        <td>' . htmlspecialchars($name) . '</td>
                        <td class="text-danger fw-semibold">' . $installation_status . '</td>
                        <td>' . $installed_date . '</td>
                        <td>--</td>
                    </tr>';
                }
            }
        }

        // Generate HTML for not available devices
        foreach ($not_available_to_show as $device_id) {
            if ($device_id != "") {
                $name = $device_id;
                foreach ($device_list as $device) {
                    $c_id = $device['D_ID'] ?? '';
                    if (trim($device_id) === $c_id) {
                        $name = $device['D_NAME'] ?? $device_id;
                        break;
                    }
                }
                if ($device_status == "ALL") {
                    $return_response .= '<tr>
                        <td><input type="checkbox"  name="selectedDevice" value="' . htmlspecialchars($device_id) . '" ></td>
                        <td>' . htmlspecialchars($device_id) . '</td>
                        <td>' . htmlspecialchars($name) . '</td>
                        <td class="text-danger fw-semibold">Not Installed.</td>
                        <td>---</td>
                        <td>--</td>
                    </tr>';
                } else {
                    $return_response .= '<tr>
                        <td><input type="checkbox"  name="selectedDevice" value="' . htmlspecialchars($device_id) . '"></td>
                        <td>' . htmlspecialchars($device_id) . '</td>
                        <td>' . htmlspecialchars($name) . '</td>
                    </tr>';
                }
            }
        }

        sendResponse(true, $return_response, 'Success', $total_records, $total_pages, $page);

    } catch (Exception $e) {
        sendResponse(false, '<tr><td colspan="6" class="text-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</td></tr>', 'Database error: ' . $e->getMessage());
    }
} else {
    sendResponse(false, '<tr><td colspan="6" class="text-danger">Input Data Not Valid</td></tr>', 'Invalid request method');
}
?>