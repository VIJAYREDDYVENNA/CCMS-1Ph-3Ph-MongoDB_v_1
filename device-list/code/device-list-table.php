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
$send = [];
$page = 1;
$items_per_page = 20;
$total_records = 0;
$total_pages = 0;

// Helper function to safely get device name from device_list
function getDeviceName($device_list, $device_id) {
    foreach ($device_list as $device) {
        if (trim($device_id) === ($device['D_ID'] ?? '')) {
            return $device['D_NAME'] ?? $device_id;
        }
    }
    return $device_id;
}

// Helper function to send JSON response and exit
function sendResponse($success, $data = [], $message = "", $totalRecords = 0, $totalPages = 0, $currentPage = 1) {
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
    $group_id = filter_input(INPUT_POST, 'GROUP_ID', FILTER_SANITIZE_STRING) ?? '';
    $page = filter_input(INPUT_POST, 'PAGE', FILTER_VALIDATE_INT) ?: 1;
    $items_per_page = filter_input(INPUT_POST, 'ITEMS_PER_PAGE', FILTER_VALIDATE_INT) ?: 20;

    // Ensure page is at least 1 and limit max items per page
    $page = max(1, $page);
    $items_per_page = max(1, min(500, $items_per_page));

    include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");

    if ($user_devices != "") {
        $user_devices = rtrim($user_devices, ',');
    } else {
        sendResponse(false, [], "No devices found for the group");
    }

    // Prepare device ids array
    $user_devices_array = array_filter(array_map(function ($item) {
        return trim(trim($item, "'"));
    }, explode(',', $user_devices)));

    if (empty($user_devices_array)) {
        sendResponse(false, [], "No valid devices found for the group");
    }

    try {
        $collection = $devices_db_conn->live_data_updates;

        // Find all devices with given device_ids to get available devices
        $filter_all = ['device_id' => ['$in' => $user_devices_array]];
        $cursor_all = $collection->find($filter_all);
        
        $available_devices = [];
        $installed_devices = [];
        $not_installed_devices = [];
        
        foreach ($cursor_all as $doc) {
            $device_id = $doc['device_id'];
            $available_devices[] = $device_id;
            
            // Separate by installation status
            if (($doc['installed_status'] ?? 0) == 1) {
                $installed_devices[] = $device_id;
            } else {
                $not_installed_devices[] = $device_id;
            }
        }

        // Find devices not available (in user_devices_array but not in MongoDB)
        $not_available_devices = array_diff($user_devices_array, $available_devices);

        // Calculate total records
        $installed_count = count($installed_devices);
        $not_installed_count = count($not_installed_devices);
        $not_available_count = count($not_available_devices);
        $total_records = $installed_count + $not_installed_count + $not_available_count;
        $total_pages = ceil($total_records / $items_per_page);

        // Calculate skip value for pagination
        $skip = ($page - 1) * $items_per_page;

        // Prepare combined array for sorting (installed devices first, then not installed, then not available)
        $all_devices = array_merge($installed_devices, $not_installed_devices, $not_available_devices);
        
        // Get devices for current page
        $current_page_devices = array_slice($all_devices, $skip, $items_per_page);

        // Separate devices for current page
        $current_installed = array_intersect($current_page_devices, $installed_devices);
        $current_not_installed = array_intersect($current_page_devices, $not_installed_devices);
        $current_not_available = array_intersect($current_page_devices, $not_available_devices);

        // Fetch MongoDB data for available devices on current page (both installed and not installed)
        $mongodb_data = [];
        $current_available = array_merge($current_installed, $current_not_installed);
        
        if (!empty($current_available)) {
            $filter = ['device_id' => ['$in' => array_values($current_available)]];
            // Sort by installed_status desc (1 first, then 0), then by active_device desc, then device_id
            $options = ['sort' => ['installed_status' => -1, 'active_device' => -1, 'device_id' => 1]]; 
            
            $cursor = $collection->find($filter, $options);
            foreach ($cursor as $r) {
                $device_id = $r['device_id'] ?? '';

                // Format dates
                $date = isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime
                    ? $r['date_time']->toDateTime()->modify('+5 hours 30 minutes')->format("H:i:s d-m-Y")
                    : '--';

                // Location handling
                $location = $r['location'] ?? '0,0';
                if ($location != '0,0' && strpos($location, "0000000,000000") === false) {
                    $address = '<a href="#" class="pt-0 pb-0" onclick="show_location(\'' . $location . '\')">Map</a>';
                } else {
                    $address = '<a href="location-details.php?id=' . htmlspecialchars($device_id) . '" target="_blank"><button class="btn btn-primary pt-0 pb-0">Update</button></a>';
                }

                // Device name from device_list
                $name = htmlspecialchars(getDeviceName($device_list, $device_id));

                // Interpret statuses
                $installation_status = ($r['installed_status'] ?? 0) == 1 
                    ? "<span class='text-success-emphasis fw-semibold'> Installed</span>" 
                    : "<span class='text-danger fw-semibold'> Not Installed</span>";

                $active_device = $r['active_device'] ?? 0;
                $poor_network = $r['poor_network'] ?? 0;
                $power_failure = $r['power_failure'] ?? 0;
                $faulty = $r['faulty'] ?? 0;
                $device_status = '';
                if (($r['installed_status'] ?? 0) == 1) {
                    if ($active_device == 1) $device_status = "<span class='text-white fw-semibold bg-success py-1 px-2 rounded'> Active</span>";
                    else if ($poor_network == 1) $device_status = "<span class='text-white fw-semibold bg-warning py-1 px-2 rounded'> Poor N/W</span>";
                    else if ($power_failure == 1) $device_status = "<span class='text-white fw-semibold bg-secondary py-1 px-2 rounded'> Power Fail</span>";
                    else if ($faulty == 1) $device_status = "<span class='text-white fw-semibold bg-danger py-1 px-2 rounded'> Faulty</span>";
                } else {
                    $device_status = "<span class='text-danger fw-semibold'> Not Installed</span>";
                }

                $on_off_status_raw = isset($r['on_off_status']) ? (string)$r['on_off_status'] : '0';
                switch ($on_off_status_raw) {
                    case "1":
                        $on_off_status = "<span class='text-white fw-semibold bg-info-emphasis py-1 px-2 rounded'>Auto ON</span>";
                        break;
                    case "2":
                        $on_off_status = "Power Fail";
                        break;
                    case "3":
                        $on_off_status = "<span class='text-white fw-semibold bg-success py-1 px-2 rounded'> Server ON</span>";
                        break;
                    case "4":
                        $on_off_status = "<span class='text-white fw-semibold bg-success py-1 px-2 rounded'> Wifi ON</span>";
                        break;
                    case "5":
                        $on_off_status = "<span class='text-white fw-semibold bg-info-emphasis py-1 px-2 rounded'> Manual ON</span>";
                        break;
                    case "6":
                        $on_off_status = "<span class='text-white fw-semibold bg-danger py-1 px-2 rounded'> SERVER OFF</span>";
                        break;
                    case "7":
                        $on_off_status = "<span class='text-white fw-semibold bg-danger py-1 px-2 rounded'> WIFI OFF</span>";
                        break;
                    case "0":
                    default:
                        $on_off_status = "<span class='text-white fw-semibold bg-danger py-1 px-2 rounded'> OFF</span>";
                        break;
                }

                $installed_lights = $r['total_lights'] ?? 0;
                $installed_lights_btn = '<button class="btn btn-info btn-sm p-0 px-2" onclick=openLightsModal("' . $device_id . '","' . $name . '")>' . $installed_lights . '</button>';

                $installation_date = isset($r['installed_date']) && $r['installed_date'] instanceof MongoDB\BSON\UTCDateTime
                    ? $r['installed_date']->toDateTime()->modify('+5 hours 30 minutes')->format('Y-m-d')
                    : '--';

                $send[] = [
                    "D_ID" => $device_id,
                    "D_NAME" => $name,
                    "INSTALLED_STATUS" => $installation_status,
                    "INSTALLED_DATE" => $installation_date,
                    "KW" => $r['unit_capacity'] ?? "--",
                    "ACTIVE_STATUS" => $active_device,
                    "DATE_TIME" => $date,
                    "WORKING_STATUS" => $device_status,
                    "ON_OFF_STATUS" => $on_off_status,
                    "OPERATION_MODE" => $r['operation_mode'] ?? "--",
                    "LMARK" => $address,
                    "INSTALLED_LIGHTS" => $installed_lights_btn,
                    "REMOVE" => $device_id,
                    "IS_INSTALLED" => ($r['installed_status'] ?? 0) // Add this for frontend sorting
                ];
            }
        }

        // Add not available devices for current page
        foreach ($current_not_available as $device_id) {
            if ($device_id !== "") {
                $name = htmlspecialchars(getDeviceName($device_list, $device_id));
                $send[] = [
                    "D_ID" => $device_id,
                    "D_NAME" => $name,
                    "INSTALLED_STATUS" => "--",
                    "INSTALLED_DATE" => "--",
                    "KW" => "--",
                    "ACTIVE_STATUS" => 0,
                    "DATE_TIME" => "--",
                    "WORKING_STATUS" => "--",
                    "ON_OFF_STATUS" => "--",
                    "OPERATION_MODE" => "--",
                    "LMARK" => "--",
                    "INSTALLED_LIGHTS" => "--",
                    "REMOVE" => $device_id,
                    "IS_INSTALLED" => 0 // Not available devices are considered not installed
                ];
            }
        }

        // Final sort to ensure proper ordering: installed first (IS_INSTALLED desc), then by active status (ACTIVE_STATUS desc)
        usort($send, function ($a, $b) {
            // First sort by installation status (installed devices first)
            if ($a['IS_INSTALLED'] != $b['IS_INSTALLED']) {
                return $b['IS_INSTALLED'] - $a['IS_INSTALLED'];
            }
            // Then sort by active status (active devices first within same installation status)
            return $b['ACTIVE_STATUS'] - $a['ACTIVE_STATUS'];
        });

        sendResponse(true, $send, 'Success', $total_records, $total_pages, $page);

    } catch (Exception $e) {
        sendResponse(false, [], 'Error: ' . $e->getMessage());
    }
} else {
    sendResponse(false, [], "Invalid request method");
}
?>