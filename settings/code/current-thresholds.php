<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars  = SessionManager::SessionVariables();
$mobile_no    = $sessionVars['mobile_no'];
$user_id      = $sessionVars['user_id'];
$role         = $sessionVars['role'];
$user_login_id= $sessionVars['user_login_id'];
$user_name    = $sessionVars['user_name'];
$user_email   = $sessionVars['user_email'];

$response = ["status" => "error", "message" => ""];



// Permission Check
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
    $device_ids  = sanitize_input($_POST['D_ID']);
    $r_current   = (int) $_POST['IR'];
    $y_current   = (int) $_POST['IY'];
    $b_current   = (int) $_POST['IB'];

    // Validate Current limits
    $currents = [$r_current, $y_current, $b_current];
    foreach ($currents as $current) {
        if ($current < 1 || $current > 5000) {
            $response["message"] = "Invalid Current Limits.";
            echo json_encode($response);
            exit();
        }
    }

    $device_ids_array = explode(",", $device_ids);
    foreach ($device_ids_array as $device_id) {
        $device_id = trim(strtoupper($device_id));
        if (!preg_match('/^[a-z0-9A-Z_]+$/', $device_id)) {
            $response["message"] = "Invalid device ID.";
            echo json_encode($response);
            exit();
        }
        $device_id_update = strtoupper($device_id);

        // Insert current limits into per-device limits_current collection
        $devices_db_conn->limits_current->insertOne([
            "device_id"   => $device_id_update,
            "i_r"         => $r_current,
            "i_y"         => $y_current,
            "i_b"         => $b_current,
            "date_time"   => new MongoDB\BSON\UTCDateTime(),
            "user_mobile" => $mobile_no,
            "email"       => $user_email,
            "name"        => $user_name,
            "role"        => $role,
            "status" => "Initiated"
        ]);

        // Upsert central_db.thresholds current values
        $devices_db_conn->thresholds->updateOne(
            ["device_id" => $device_id_update],
            ['$set' => [
                "i_r" => $r_current,
                "i_y" => $y_current,
                "i_b" => $b_current
            ]],
            ["upsert" => true]
        );

        $devices_db_conn->device_settings->updateOne(
            ["device_id" => $device_id, "setting_type" => "CURRENT"],
            ['$set' => ["setting_flag" => 1]],
            ["upsert" => true]
        );

        $devices_db_conn->device_settings->updateOne(
            ["device_id" => $device_id, "setting_type" => "READ_SETTINGS"],
            ['$set' => ["setting_flag" => 1]],
            ["upsert" => true]
        );

        // User activity log
        $dtIST = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
        $date_time = new MongoDB\BSON\UTCDateTime($dtIST->getTimestamp() * 1000);
        // 4. User Activity Log
        $devices_db_conn->user_activity_log->insertOne([
            "device_id"     => $device_id_update,
            "updated_field" => "Current thresholds updated",
            "date_time"     => $date_time,
            "user_mobile"   => $mobile_no,
            "email"         => $user_email,
            "name"          => $user_name,
            "role"          => $role
        ]);
    }

    $response["status"]  = "success";
    $response["message"] = "Current Limits updated successfully.";
    echo json_encode($response);
    exit();
} else {
    $response["message"] = "Invalid request method.";
    echo json_encode($response);
    exit();
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>
