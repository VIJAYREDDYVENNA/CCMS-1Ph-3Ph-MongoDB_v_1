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



// ---------------- PERMISSION CHECK ----------------
$permissionDoc = $user_db_conn->user_permissions->findOne(
    ["login_id" => (int)$user_login_id],
    ["projection" => ["threshold_settings" => 1]]
);


if (!$permissionDoc || (int)$permissionDoc["threshold_settings"] !== 1) {
    $response["message"] = "No Permission to change the voltage threshold settings.";
    echo json_encode($response);
    exit();
}

// ---------------- IF POST METHOD ----------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $device_ids    = sanitize_input($_POST['D_ID']);
    $r_lower_volt  = (int) $_POST['LR'];
    $y_lower_volt  = (int) $_POST['LY'];
    $b_lower_volt  = (int) $_POST['LB'];
    $r_upper_volt  = (int) $_POST['UR'];
    $y_upper_volt  = (int) $_POST['UY'];
    $b_upper_volt  = (int) $_POST['UB'];

    // ----------- Validate Voltages -----------
    $volts = [$r_lower_volt, $y_lower_volt, $b_lower_volt, $r_upper_volt, $y_upper_volt, $b_upper_volt];
    foreach ($volts as $v) {
        if ($v < 1 || $v > 750) {
            $response["message"] = "Invalid voltage values.";
            echo json_encode($response);
            exit();
        }
    }

    // ----------- Loop over Devices -----------
    $device_ids_array = explode(",", $device_ids);
    foreach ($device_ids_array as $device_id) {

        $device_id = trim(strtoupper($device_id));
        if (!preg_match('/^[a-z0-9A-Z_]+$/', $device_id)) {
            $response["message"] = "Invalid device ID format.";
            echo json_encode($response);
            exit();
        }
        $device_id_update = strtoupper($device_id);

        // 1. Insert voltage limits into per-device DB
        $devices_db_conn->limits_voltage->insertOne([
            "device_id"   => $device_id_update,
            "l_r"         => $r_lower_volt,
            "l_y"         => $y_lower_volt,
            "l_b"         => $b_lower_volt,
            "u_r"         => $r_upper_volt,
            "u_y"         => $y_upper_volt,
            "u_b"         => $b_upper_volt,
            "date_time"   => new MongoDB\BSON\UTCDateTime(),
            "user_mobile" => $mobile_no,
            "email"       => $user_email,
            "name"        => $user_name,
            "role"        => $role,
            "status" => "Initiated"
        ]);

        // 2. Update (or insert) central thresholds collection
        $devices_db_conn->thresholds->updateOne(
            ["device_id" => $device_id_update],
            ['$set' => [
                "l_r" => $r_lower_volt,
                "l_y" => $y_lower_volt,
                "l_b" => $b_lower_volt,
                "u_r" => $r_upper_volt,
                "u_y" => $y_upper_volt,
                "u_b" => $b_upper_volt
            ]],
            ["upsert" => true]
        );

        // 3. Device Settings (VOLTAGE + READ_SETTINGS)
        // Ensure compound unique index once per collection
        /*$devices_db_conn->device_settings->createIndex(
            ["device_id" => 1, "setting_type" => 1],
            ["unique" => true]
        );*/

        $devices_db_conn->device_settings->updateOne(
            ["device_id" => $device_id_update, "setting_type" => "VOLTAGE"],
            ['$set' => ["setting_flag" => 1]],
            ["upsert" => true]
        );

        $devices_db_conn->device_settings->updateOne(
            ["device_id" => $device_id_update, "setting_type" => "READ_SETTINGS"],
            ['$set' => ["setting_flag" => 1]],
            ["upsert" => true]
        );
        $dtIST = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
        $date_time = new MongoDB\BSON\UTCDateTime($dtIST->getTimestamp() * 1000);
        // 4. User Activity Log
        $devices_db_conn->user_activity_log->insertOne([
            "device_id"     => $device_id_update,
            "updated_field" => "Voltage thresholds updated",
            "date_time"     => $date_time,
            "user_mobile"   => $mobile_no,
            "email"         => $user_email,
            "name"          => $user_name,
            "role"          => $role
        ]);
    }

    $response["status"]  = "success";
    $response["message"] = "Voltage threshold settings updated successfully.";
    echo json_encode($response);
    exit();

} else {
    $response["message"] = "Invalid request method.";
    echo json_encode($response);
    exit();
}


// ---------------- Helper ----------------
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

?>
