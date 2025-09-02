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

    include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");

    if ($user_devices != "") {
        $user_devices = rtrim($user_devices, ',');
    }

    $user_devices_array = array_filter(array_map(function ($item) {
        return trim(trim($item, "'"));
    }, explode(',', $user_devices)));

    if (empty($user_devices_array)) {
        echo json_encode('<tr><td colspan="6" class="text-danger">No devices found for this group</td></tr>');
        exit;
    }

    $limit = 200;
    $offset = 0;

    if (isset($_POST['FETCH_MORE']) && $_POST['FETCH_MORE'] === "MORE") {
        if (empty($_SESSION['FETCH_DEVICES_LIST']) || $_SESSION['FETCH_DEVICES_LIST'] == 0) {
            echo json_encode("");
            exit();
        }
        $page = intval($_SESSION['FETCH_DEVICES_LIST']);
        $offset = ($page - 1) * $limit;
        $_SESSION['FETCH_DEVICES_LIST'] = $page + 1;
    } else {
        $_SESSION['FETCH_DEVICES_LIST'] = 2;
    }

    try {
        $collection = $devices_db_conn->live_data_updates;

        $filter = ['device_id' => ['$in' => $user_devices_array]];
        $options = [
            'sort' => ['device_id' => 1],  // Lexical sort by device_id
            'limit' => $limit,
            'skip' => $offset,
        ];

        $cursor = $collection->find($filter, $options);
        $docs = iterator_to_array($cursor);

        if (empty($docs)) {
            $_SESSION['FETCH_DEVICES_LIST'] = 0;
            $return_response .= '<tr><td colspan="6" class="text-danger">Devices Not Found</td></tr>';
        } else {
            $device_map = [];
            foreach ($device_list as $device) {
                if (isset($device['D_ID'])) {
                    $device_map[$device['D_ID']] = $device['D_NAME'] ?? $device['D_ID'];
                }
            }

            foreach ($docs as $r) {
                $device_id = $r['device_id'] ?? '';
                $operation_mode = $r['operation_mode'] ?? '';

                if (isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                    $dt = $r['date_time']->toDateTime();
                    $dt->modify('+5 hours 30 minutes');
                    $date = $dt->format("H:i:s d-m-Y");
                } else {
                    $date = '--';
                }

                $on_off = (string)($r['on_off_status'] ?? '0');
                switch ($on_off) {
                    case "1":
                        $on_off_status = "<span class='text-success fw-bold'>Auto ON</span>";
                        break;
                    case "3":
                        $on_off_status = "<span class='text-success fw-bold'>Server ON</span>";
                        break;
                    case "4":
                        $on_off_status = "<span class='text-success fw-bold'>WiFi ON</span>";
                        break;
                    case "5":
                        $on_off_status = "<span class='text-info-emphasis fw-bold'>Manual ON</span>";
                        break;
                    case "6":
                        $on_off_status = "<span class='text-danger fw-bold'>SERVER OFF</span>";
                        break;
                    case "7":
                        $on_off_status = "<span class='text-danger fw-bold'>WiFi OFF</span>";
                        break;
                    case "0":
                    default:
                        $on_off_status = "<span class='text-danger fw-bold'>OFF</span>";
                        break;
                }

                $name = $device_map[$device_id] ?? htmlspecialchars($device_id);

                $return_response .= '<tr>
                    <td>' . htmlspecialchars($device_id) . '</td>
                    <td>' . htmlspecialchars($name) . '</td>
                    <td>' . htmlspecialchars($operation_mode) . '</td>
                    <td>' . $on_off_status . '</td>
                    <td>' . $date . '</td>
                </tr>';
            }
        }
    } catch (Exception $e) {
        error_log('Error fetching devices: ' . $e->getMessage());
        $return_response .= '<tr><td colspan="6" class="text-danger">Error loading devices</td></tr>';
    }
} else {
    $return_response .= '<tr><td colspan="6" class="text-danger">Input Data Not Valid</td></tr>';
}

echo json_encode($return_response);
