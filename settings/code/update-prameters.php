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

$response = ["status" => "error", "message" => ""];


// Permission check (cast login_id to int if stored like that)
$permissionDoc = $user_db_conn->user_permissions->findOne(
    ["login_id" => (int)$user_login_id],
    ["projection" => ["threshold_settings" => 1]]
);

if (!$permissionDoc || (int)$permissionDoc["threshold_settings"] !== 1) {
    $response["message"] = "No Permission to change the Current settings of the device(s).";
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $device_ids    = sanitize_input($_POST['D_ID']);
    $parameter     = sanitize_input($_POST['PARAMETER']);
    $updated_value = sanitize_input($_POST['UPDATED_VALUE']);

    $validParameter = ['PF', 'CAPACITY', 'FRAME_TIME', 'CT_RATIO'];
    if (!in_array($parameter, $validParameter)) {
        $response["message"] = "Invalid parameter.";
        echo json_encode($response);
        exit();
    }

    // Validation for values
    if ($parameter === "PF") {
        if (!is_numeric($updated_value) || $updated_value < 0.0 || $updated_value > 1.0) {
            $response["message"] = "Invalid Updated Value";
            echo json_encode($response);
            exit();
        }
        $updated_value = (float)$updated_value;
    } else {
        if (!filter_var($updated_value, FILTER_VALIDATE_INT, ["options" => ["min_range"=>1, "max_range"=>84600]])) {
            $response["message"] = "Invalid Updated Value";
            echo json_encode($response);
            exit();
        }
        $updated_value = (int)$updated_value;
    }

    $device_ids_array = explode(",", $device_ids);
    foreach ($device_ids_array as $device_id_raw) {
        $device_id = trim(strtolower($device_id_raw));
        if (!preg_match('/^[a-z0-9_]+$/', $device_id)) {
            $response["message"] = "Invalid device ID";
            echo json_encode($response);
            exit();
        }
        $device_id_update = strtoupper($device_id);

        $dtIST = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
        $date_time = new MongoDB\BSON\UTCDateTime($dtIST->getTimestamp() * 1000);

        switch ($parameter) {
            case 'PF':
            $user_activity = "PF Limit updated";

            $devices_db_conn->limits_pf->insertOne([
                "device_id"   => $device_id_update,
                "pf"          => $updated_value,
                "date_time"   => $date_time,
                "user_mobile" => $mobile_no,
                "email"       => $user_email,
                "name"        => $user_name,
                "role"        => $role,
                "status" => "Initiated"
            ]);

                // Upsert into central thresholds collection
            $devices_db_conn->thresholds->updateOne(
                ["device_id" => $device_id_update],
                ['$set' => ["pf" => $updated_value]],
                ["upsert" => true]
            );

            break;

            case 'CAPACITY':
            $user_activity = "Unit Capacity updated";

            $devices_db_conn->unit_capacity->insertOne([
                "device_id"   => $device_id_update,
                "capacity"    => $updated_value,
                "date_time"   => $date_time,
                "user_mobile" => $mobile_no,
                "email"       => $user_email,
                "name"        => $user_name,
                "role"        => $role,
                "status" => "Initiated"
            ]);
            break;

            case 'FRAME_TIME':
            $user_activity = "Frame-Time interval updated";

            $devices_db_conn->frame_time->insertOne([
                "device_id"   => $device_id_update,
                "frame_time"  => $updated_value,
                "date_time"   => $date_time,
                "user_mobile" => $mobile_no,
                "email"       => $user_email,
                "name"        => $user_name,
                "role"        => $role,
                "status" => "Initiated"
            ]);

                // Update device_settings for FRAME_TIME and READ_SETTINGS
            $devices_db_conn->device_settings->updateOne(
                ["device_id" => $device_id_update, "setting_type" => "FRAME_TIME"],
                ['$set' => ["setting_flag" => 1]],
                ["upsert" => true]
            );

            $devices_db_conn->device_settings->updateOne(
                ["device_id" => $device_id_update, "setting_type" => "READ_SETTINGS"],
                ['$set' => ["setting_flag" => 1]],
                ["upsert" => true]
            );

            break;

            case 'CT_RATIO':
            $user_activity = "CT-Ratio updated";

            $devices_db_conn->limits_ct_ratio->insertOne([
                "device_id"   => $device_id_update,
                "ct_ratio"    => $updated_value,
                "date_time"   => $date_time,
                "user_mobile" => $mobile_no,
                "email"       => $user_email,
                "name"        => $user_name,
                "role"        => $role,
                "status" => "Initiated"
            ]);
            break;
        }

        // User activity log insert (time series preferred)
        $devices_db_conn->user_activity_log->insertOne([
            "date_time"     => $date_time,
            "updated_field" => $user_activity,

            "device_id"   => $device_id_update,
            "user_mobile" => $mobile_no,
            "email"       => $user_email,
            "name"        => $user_name,
            "role"        => $role,
            
            
        ]);
    }

    $response["status"] = "success";
    $response["message"] = $user_activity . " successfully";
    echo json_encode($response);
    exit();

} else {
    $response["message"] = "Invalid request method.";
    echo json_encode($response);
    exit();
}

// Sanitize input helper
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>
