<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$mobile_no    = $sessionVars['mobile_no'];
$user_id      = $sessionVars['user_id'];
$role         = $sessionVars['role'];
$user_login_id= $sessionVars['user_login_id'];
$user_name    = $sessionVars['user_name'];
$user_email   = $sessionVars['user_email'];

$normal = 'class=""';
$red = 'class="text-danger-emphasis fw-bold"';
$orange = 'class="text-warning-emphasis fw-bold"';
$green = 'class="text-success-emphasis fw-bold"';
$primary = 'class="text-info-emphasis fw-bold"';
$class = $normal;
$data = "";



if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID'])) {
    $device_ids = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);
    $parameter = isset($_POST['KEY']) ? filter_input(INPUT_POST, 'KEY', FILTER_SANITIZE_STRING) : "";

    update_data($device_ids, $parameter);
} else {
    $data = '<tr><td class="text-danger" colspan="5">Invalid request</td></tr>';
    echo json_encode($data);
    exit();
}

function update_data($device_ids, $parameter)
{
    global $role, $devices_db_conn, $normal, $red, $orange, $green, $primary;

    $class = $normal;
    $data = "";

    $device_id_upper = strtoupper(trim($device_ids));

    // Handle cancellation parameter update_flag=3
    if (isset($_POST['CANCEL_PARAMTER'])) {
        $cancel_param = filter_input(INPUT_POST, 'CANCEL_PARAMTER', FILTER_SANITIZE_STRING);
        $cancel_param = trim($cancel_param);

        // Update all matching device_settings documents for this device + setting_type
        $updateResult = $devices_db_conn->device_settings->updateMany(
            [
                "device_id"    => $device_id_upper,
                "setting_type" => $cancel_param
            ],
            ['$set' => ["setting_flag" => 3]]
        );
    }

    $data .= "<thead class='sticky-header text-center'>
    	<tr class='header-row-1'>                                    
    	<th class='table-header-row-1'>Configuration</th>                                
    	<th class='table-header-row-1'>Status</th>                                
    	<th class='table-header-row-1'>Date&Time</th>                                
    	<th class='table-header-row-1'>Action</th>
    	</tr></thead><tbody>";

    // Build filter criteria depending on role and parameter
    $filter = ["device_id" => $device_id_upper];

    // Allowed settings types for non-superadmin
    $allowedSettingsForRole = ['ONOFF', 'VOLTAGE', 'CURRENT', 'SCHEDULE_TIME', 'ON_OFF_MODE'];

    if ($role === "SUPERADMIN") {
        if ($parameter === "") {
            // No parameter filter, fetch max 100 ordered by setting_type
            $filter = ["device_id" => $device_id_upper];
        } else {
            $filter = ["device_id" => $device_id_upper, "setting_type" => strtoupper($parameter)];
        }
    } else {
        if ($parameter === "") {
            $filter = [
                "device_id" => $device_id_upper,
                "setting_type" => ['$in' => $allowedSettingsForRole]
            ];
        } elseif (in_array(strtoupper($parameter), $allowedSettingsForRole, true)) {
            $filter = [
                "device_id" => $device_id_upper,
                "setting_type" => strtoupper($parameter)
            ];
        } else {
            $data .= '<tr><td class="text-danger" colspan="5">Records are not Found</td></tr></tbody>';
            echo json_encode($data);
            exit();
        }
    }

    // Query device_settings collection with filter and limit
    $options = [
        "sort" => ["setting_type" => 1],
        "limit" => 100
    ];

    $cursor = $devices_db_conn->device_settings->find($filter, $options);

    $found = false;

    foreach ($cursor as $r) {
        $found = true;

        $flag_status = "";
        $configuration = $r['setting_type'];
        $cancel_btn = '';

        switch ($r['setting_flag']) {
            case 0:
                $class = $green;
                $flag_status = "Updated";
                break;
            case 1:
                $class = $red;
                $flag_status = "Pending";
                $cancel_btn = '<button class="btn btn-danger pt-0 pb-0" onclick=cancel_update("' . htmlspecialchars($configuration) . '")>Cancel</button>';
                break;
            case 2:
                $class = $primary;
                $flag_status = "In-progress and waiting for Ack...";
                $cancel_btn = '<button class="btn btn-danger pt-0 pb-0" onclick=cancel_update("' . htmlspecialchars($configuration) . '")>Cancel</button>';
                break;
            case 3:
                $class = $normal;
                $flag_status = "Cancelled";
                break;
            default:
                $class = $normal;
                $flag_status = "Unknown";
                break;
        }

        // Convert MongoDB UTCDateTime to string for display (in IST timezone)
        if (isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
            $dt = $r['date_time']->toDateTime();
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            $dateTimeStr = $dt->format('Y-m-d H:i:s');
        } elseif (isset($r['date_time'])) {
            $dateTimeStr = (string)$r['date_time'];
        } else {
            $dateTimeStr = '';
        }

        $data .= "<tr>
                    <td>" . htmlspecialchars($configuration) . "</td>
                    <td $class> " . htmlspecialchars($flag_status) . "</td>
                    <td>" . htmlspecialchars($dateTimeStr) . "</td>
                    <td>" . $cancel_btn . "</td>
                  </tr>";
    }

    if (!$found) {
        $data .= '<tr><td class="text-danger" colspan="5">Records are not Found</td></tr>';
    }

    $data .= "</tbody>";

    echo json_encode($data);
}

function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
