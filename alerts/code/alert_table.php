<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$normal = 'class=""';
$red = 'class="text-danger "';
$orange = 'class="text-warning "';
$green = 'class="text-success "';

$class_r = $green;
$class_y = $green;
$class_b = $green;

$d_name = "";
$data = "";
$device_phase = "3PH";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID']) && isset($_POST['RECORDS'])) {
    $device_ids = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);
    $records = filter_input(INPUT_POST, 'RECORDS', FILTER_SANITIZE_STRING);
    $alert = filter_input(INPUT_POST, 'ALERT', FILTER_SANITIZE_STRING);

    $db = strtolower($device_ids);
    $data = "";
    $id = $device_ids;

    include_once("../../common-files/fetch-device-phase.php"); // Assumes $device_phase is set here

    try {
        

        switch ($alert) {
            case "POWER-ON/OFF":
                $collection = $devices_db_conn->alert_power_failure;
                break;
            case "BATTERY_STATUS":
                $collection = $devices_db_conn->alert_power_supply_check;
                break;
            case "DOOR":
                $collection = $devices_db_conn->alert_door;
                break;
            default:
                // If alert unknown, fallback or error
                echo json_encode('<tr><td class="text-danger" colspan="5">Unknown alert type.</td></tr>');
                exit();
        }

        $filter = ['device_id' => $device_ids];  
        $options = ['sort' => ['date_time' => -1]];

        if ($records === "LATEST") {
            $options['limit'] = 50;
        } elseif ($records === "DATE-RANGE") {
            if (isset($_POST['START_DATE']) && isset($_POST['END_DATE'])) {
                $start_date = trim(filter_input(INPUT_POST, 'START_DATE', FILTER_SANITIZE_STRING));
                $end_date = trim(filter_input(INPUT_POST, 'END_DATE', FILTER_SANITIZE_STRING));
                $start_obj = DateTime::createFromFormat('Y-m-d', $start_date);
                $end_obj = DateTime::createFromFormat('Y-m-d', $end_date);
                if (!$start_obj || !$end_obj) {
                    echo json_encode('<tr><td class="text-danger" colspan="5">Records not Found. Date format error</td></tr>');
                    exit();
                }
                

                $filter['date_time'] = [
                   
                    '$lte' => new MongoDB\BSON\UTCDateTime($end_obj->getTimestamp() * 1000)
                ];
            } else {
                echo json_encode('<tr><td class="text-danger" colspan="5">Records not Found. Empty date parameter sent</td></tr>');
                exit();
            }
        } else {
            echo json_encode('<tr><td class="text-danger" colspan="5">Records not Found</td></tr>');
            exit();
        }

        $cursor = $collection->find($filter, $options);

        // Compose HTML table header for each alert type
        if ($alert == "POWER-ON/OFF") {
            if ($device_phase == "3PH") {
                $data .= "<thead class='sticky-header text-center'>
                    <tr class='header-row-1'>
                        <th class='table-header-row-1'>Power status</th>
                        <th class='table-header-row-1'>Battery(mV)</th>
                        <th class='table-header-row-1'>System Voltage(mV)</th>
                        <th class='table-header-row-1'>Ph-R(V)</th>
                        <th class='table-header-row-1'>Ph-Y(V)</th>
                        <th class='table-header-row-1'>Ph-B(V)</th>
                        <th class='table-header-row-1'>Data & Time</th>
                    </tr></thead><tbody>";
            } else {
                $data .= "<thead class='sticky-header text-center'>
                    <tr class='header-row-1'>
                        <th class='table-header-row-1'>Power status</th>
                        <th class='table-header-row-1'>Battery(mV)</th>
                        <th class='table-header-row-1'>System Voltage(mV)</th>
                        <th class='table-header-row-1'>Phase (V)</th>
                        <th class='table-header-row-1'>Data & Time</th>
                    </tr></thead><tbody>";
            }
            $hasRecords = false;
            foreach ($cursor as $r) {
                $hasRecords = true;
                $status = $r['status'] ?? '';

                if (stripos($status, "OFF") !== false || stripos($status, "Disconnected") !== false) {
                    $class_r = $red;
                } elseif (stripos($status, "ON") !== false || stripos($status, "Restored") !== false) {
                    $class_r = $green;
                } else {
                    $class_r = $green;
                }

                $date_time_str = '--';
                if (isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                    $dt = $r['date_time']->toDateTime();
                    $dt->modify('+5 hours 30 minutes');
                    $date_time_str = $dt->format("Y-m-d H:i:s");
                }

                if ($device_phase == "3PH") {
                    $data .= "<tr>
                        <td $class_r>" . htmlspecialchars($status) . "</td>
                        <td $class_r>" . htmlspecialchars($r['battery_voltage'] ?? '') . "</td>
                        <td $class_r>" . htmlspecialchars($r['system_voltage'] ?? '') . "</td>
                        <td $class_r>" . htmlspecialchars($r['ph_r'] ?? '') . "</td>
                        <td $class_r>" . htmlspecialchars($r['ph_y'] ?? '') . "</td>
                        <td $class_r>" . htmlspecialchars($r['ph_b'] ?? '') . "</td>
                        <td $class_r>" . $date_time_str . "</td>
                    </tr>";
                } else {
                    $data .= "<tr>
                        <td $class_r>" . htmlspecialchars($status) . "</td>
                        <td $class_r>" . htmlspecialchars($r['battery_voltage'] ?? '') . "</td>
                        <td $class_r>" . htmlspecialchars($r['system_voltage'] ?? '') . "</td>
                        <td $class_r>" . htmlspecialchars($r['ph_r'] ?? '') . "</td>
                        <td $class_r>" . $date_time_str . "</td>
                    </tr>";
                }
            }
            if (!$hasRecords) {
                $data .= '<tr><td class="text-danger" colspan="7">Records are not Found</td></tr>';
            }
            $data .= "</tbody>";
        } elseif ($alert == "BATTERY_STATUS") {
            $data .= "<thead class='sticky-header text-center'>
                <tr class='header-row-1'>
                    <th class='table-header-row-1'>Battery Voltage(mV)</th>
                    <th class='table-header-row-1'>Data & Time</th>
                </tr></thead><tbody>";

            $hasRecords = false;
            foreach ($cursor as $r) {
                $hasRecords = true;
                $date_time_str = '--';
                if (isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                    $dt = $r['date_time']->toDateTime();
                    $dt->modify('+5 hours 30 minutes');
                    $date_time_str = $dt->format("Y-m-d H:i:s");
                }

                $data .= "<tr>
                    <td>" . htmlspecialchars($r['battery_voltage'] ?? '') . "</td>
                    <td>" . $date_time_str . "</td>
                </tr>";
            }
            if (!$hasRecords) {
                $data .= '<tr><td class="text-danger" colspan="2">Records are not Found</td></tr>';
            }
            $data .= "</tbody>";
        } elseif ($alert == "DOOR") {
            $data .= "<thead class='sticky-header text-center'>
                <tr class='header-row-1'>
                    <th class='table-header-row-1'>Door Alert</th>
                    <th class='table-header-row-1'>Data & Time</th>
                </tr></thead><tbody>";
            $hasRecords = false;
            foreach ($cursor as $r) {
                $hasRecords = true;
                $alert_val = strtoupper($r['alert'] ?? '');
                if (strpos($alert_val, "OPEN") !== false) {
                    $class_r = $red;
                } elseif (strpos($alert_val, "CLOSE") !== false) {
                    $class_r = $green;
                } else {
                    $class_r = $normal;
                }

                $date_time_str = '--';
                if (isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                    $dt = $r['date_time']->toDateTime();
                    $dt->modify('+5 hours 30 minutes');
                    $date_time_str = $dt->format("Y-m-d H:i:s");
                }

                $data .= "<tr>
                    <td $class_r>" . htmlspecialchars($r['alert'] ?? '') . "</td>
                    <td $class_r>" . $date_time_str . "</td>
                </tr>";
            }
            if (!$hasRecords) {
                $data .= '<tr><td class="text-danger" colspan="2">Records are not Found</td></tr>';
            }
            $data .= "</tbody>";
        } else {
            $data .= '<tr><td class="text-danger" colspan="5">Unknown alert type</td></tr>';
        }
    } catch (Exception $e) {
        $data = '<tr><td class="text-danger" colspan="12">Records are not Found</td></tr>';
    }
} else {
    $data = '<tr><td class="text-danger" colspan="12">Records are not Found</td></tr>';
}

echo json_encode($data);

?>
