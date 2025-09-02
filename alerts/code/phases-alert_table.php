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
$user_name = $sessionVars['user_name'];
$user_email = $sessionVars['user_email'];

$normal = 'class=""';
$red = 'class="text-danger "';
$orange = 'class="text-warning "';
$green = 'class="text-success "';

$class_r = $green;
$class_y = $green;
$class_b = $green;
$class_primary = 'class="text-primary fw-bold "';
$class_danger = 'class="text-danger fw-bold "';
$class_info = 'class="text-info fw-bold "';
$class_warning = 'class="text-warning fw-bold "';

$d_name = "";
$data = "";
$device_phase = "3PH";
$phase = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID']) && isset($_POST['RECORDS'])) {
    $device_ids = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);
    $records = filter_input(INPUT_POST, 'RECORDS', FILTER_SANITIZE_STRING);
    $alert = filter_input(INPUT_POST, 'ALERT', FILTER_SANITIZE_STRING);

   /* $records="LATEST";
     $device_ids="SPIOT_3";
     $alert="ALL";*/
    $data = "";
    $id = $device_ids;

    include_once("../../common-files/fetch-device-phase.php");

    try {
     
        $collection = $devices_db_conn->alert_phases;

        $filter = ['device_id' => $device_ids];
        $options = ['sort' => ['date_time' => -1]];

        if ($records === "LATEST") {
            $options['limit'] = 50;
            if ($alert !== "ALL") {
                $filter['alert_name'] = $alert;
            }
        } elseif ($records === "DATE-RANGE") {
            if (isset($_POST['START_DATE']) && isset($_POST['END_DATE'])) {
                $start_date_str = trim(filter_input(INPUT_POST, 'START_DATE', FILTER_SANITIZE_STRING));
                $end_date_str = trim(filter_input(INPUT_POST, 'END_DATE', FILTER_SANITIZE_STRING));
                $start_date = DateTime::createFromFormat('Y-m-d', $start_date_str, new DateTimeZone('Asia/Kolkata'));
                $end_date = DateTime::createFromFormat('Y-m-d', $end_date_str, new DateTimeZone('Asia/Kolkata'));

                if (!$start_date || !$end_date) {
                    $data = '<tr><td class="text-danger" colspan="12">Records are not Found. Empty or invalid date parameter sent</td></tr>';
                    echo json_encode($data);
                    exit();
                }

                $end_date->modify('+1 day');

                $start_utc = clone $start_date;
                $start_utc->setTimezone(new DateTimeZone('UTC'));
                $end_utc = clone $end_date;
                $end_utc->setTimezone(new DateTimeZone('UTC'));

                $filter['date_time'] = [
                    '$gte' => new MongoDB\BSON\UTCDateTime($start_utc->getTimestamp() * 1000),
                    '$lt' => new MongoDB\BSON\UTCDateTime($end_utc->getTimestamp() * 1000)
                ];

                if ($alert !== "ALL") {
                    $filter['alert_name'] = $alert;
                }
            } else {
                $data = '<tr><td class="text-danger" colspan="12">Records are not Found. Empty date parameter sent</td></tr>';
                echo json_encode($data);
                exit();
            }
        } else {
            $data = '<tr><td class="text-danger" colspan="12">Records are not Found</td></tr>';
            echo json_encode($data);
            exit();
        }

        $cursor = $collection->find($filter, $options);



        $hasRecords = false;
        foreach ($cursor as $r) {
        	
            $hasRecords = true;

            $class_r = $green;
            $class_y = $green;
            $class_b = $green;

            $ph_r = strtoupper($r['ph_r'] ?? '');
            $ph_y = strtoupper($r['ph_y'] ?? '');
            $ph_b = strtoupper($r['ph_b'] ?? '');

            if (in_array($ph_r, ["LOW", "HIGH", "OVERLOAD", "TRIPPED"])) {
                $class_r = $red;
            }
            if (in_array($ph_y, ["LOW", "HIGH", "OVERLOAD", "TRIPPED"])) {
                $class_y = $red;
            }
            if (in_array($ph_b, ["LOW", "HIGH", "OVERLOAD", "TRIPPED"])) {
                $class_b = $red;
            }

            $alert_name_upper = strtoupper($r['alert_name'] ?? '');
            if ($alert_name_upper == "VOLTAGE") {
                $class_param = $class_primary;
            } elseif (in_array($alert_name_upper, ["CURRENT", "OVERLOAD"])) {
                $class_param = $class_info;
            } elseif ($alert_name_upper == "CONTACTOR/MCB") {
                $class_param = $class_warning;
            } else {
                $class_param = $class_danger;
            }

            $date_time_str = '--';
            if (isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                $dt = $r['date_time']->toDateTime();
                $dt->modify('+5 hours 30 minutes');
                $date_time_str = $dt->format("Y-m-d H:i:s");
            }

            if ($device_phase == "3PH") {
                $data .= "<tr>
                    <td $class_param>" . htmlspecialchars($r['alert_name'] ?? '') . "</td>
                    <td $class_r>" . htmlspecialchars($r['ph_r'] ?? '') . "</td>
                    <td $class_y>" . htmlspecialchars($r['ph_y'] ?? '') . "</td>
                    <td $class_b>" . htmlspecialchars($r['ph_b'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['v_r'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['v_y'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['v_b'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['i_r'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['i_y'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['i_b'] ?? '') . "</td>
                    <td>$date_time_str</td>
                </tr>";
            } elseif ($device_phase == "1PH") {
                $data .= "<tr>
                    <td $class_param>" . htmlspecialchars($r['alert_name'] ?? '') . "</td>
                    <td $class_r>" . htmlspecialchars($r['ph_r'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['v_r'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['i_r'] ?? '') . "</td>
                    <td>$date_time_str</td>
                </tr>";
            }
        }

        if (!$hasRecords) {
            $data .= '<tr><td class="text-danger" colspan="12">Records are not Found</td></tr>';
        }

      
    } catch (Exception $e) {
        $data = '<tr><td class="text-danger" colspan="12">Records are not Found</td></tr>';
    }
} else {
    $data = '<tr><td class="text-danger" colspan="12">Records are not Found</td></tr>';
}

echo json_encode([$data, $phase]);
?>
