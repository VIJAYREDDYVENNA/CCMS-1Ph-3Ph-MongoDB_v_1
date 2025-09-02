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

$response = ["status" => "error", "message" => ""];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID']) && isset($_POST['ON_TIME']) && isset($_POST['OFF_TIME'])) {

    

    $device_ids = htmlspecialchars($_POST['D_ID']);
    $on_time = htmlspecialchars($_POST['ON_TIME']);
    $off_time = htmlspecialchars($_POST['OFF_TIME']);
    $device_ids_array = explode(",", $device_ids);

    // Check permission: user_permissions collection, field: on_off_mode
    $userPermissionDoc = $user_db_conn->user_permissions->findOne(
        ['login_id' => (int)$user_login_id],
        ['projection' => ['on_off_mode' => 1]]
    );
    $permission_check = (int)$userPermissionDoc['on_off_mode'] ?? 0;

    if ($permission_check != 1) {
        $response["message"] = "No Permission to change the Operation Mode of the device(s)";
        echo json_encode($response);
        exit();
    }

    foreach ($device_ids_array as $device_id) {
        $device_id = trim(strtoupper($device_id));

        // Insert into on_off_schedule_time collection
        $insertScheduleResult = $devices_db_conn->on_off_schedule_time->insertOne([
            'device_id' => $device_id,
            'on_time' => $on_time,
            'off_time' => $off_time,
            'status' => 'Initiated',
            'date_time' => new MongoDB\BSON\UTCDateTime(),
            'user_mobile' => $mobile_no,
            'email' => $user_email,
            'name' => $user_name,
            'role' => $role
        ]);

        if (!$insertScheduleResult->isAcknowledged()) {
            $response["message"] = "Failed to initiate schedule time for device $device_id";
            echo json_encode($response);
            exit();
        }

        // Insert on_off_modes document
        $insertModeResult = $devices_db_conn->on_off_modes->insertOne([
            'device_id' => $device_id,
            'on_off_mode' => 'SCHEDULE_TIME',
            'status' => 'Initiated',
            'date_time' => new MongoDB\BSON\UTCDateTime(),
            'user_mobile' => $mobile_no,
            'email' => $user_email,
            'name' => $user_name,
            'role' => $role
        ]);

        if (!$insertModeResult->isAcknowledged()) {
            $response["message"] = "Failed to set on/off mode for device $device_id";
            echo json_encode($response);
            exit();
        }

        // Update or insert device_settings documents
        // Cancel ON_OFF_MODE: setting_flag = '0'
        $devices_db_conn->device_settings->updateOne(
            ['device_id' => $device_id, 'setting_type' => 'ON_OFF_MODE'],
            ['$set' => ['setting_flag' => 0]],
            ['upsert' => true]
        );

        // Enable SCHEDULE_TIME: setting_flag = '1'
        $devices_db_conn->device_settings->updateOne(
            ['device_id' => $device_id, 'setting_type' => 'SCHEDULE_TIME'],
            ['$set' => ['setting_flag' => 1]],
            ['upsert' => true]
        );

        // Enable READ_SETTINGS: setting_flag = '1'
        $devices_db_conn->device_settings->updateOne(
            ['device_id' => $device_id, 'setting_type' => 'READ_SETTINGS'],
            ['$set' => ['setting_flag' => 1]],
            ['upsert' => true]
        );

        // Insert user activity log
        $on_off_activity = "Initiated Schedule mode ";
        $logResult = $devices_db_conn->user_activity_log->insertOne([
            'device_id' => $device_id,
            'updated_field' => $on_off_activity,
            'date_time' => new MongoDB\BSON\UTCDateTime(),
            'user_mobile' => $mobile_no,
            'email' => $user_email,
            'name' => $user_name,
            'role' => $role
        ]);

        if (!$logResult->isAcknowledged()) {
            $response["message"] = "Failed to log user activity for device $device_id";
            echo json_encode($response);
            exit();
        }
    }

    $response["status"] = "success";
    $response["message"] = "SCHEDULE TIME mode Initiated";
    echo json_encode($response);

} else {
    $response["message"] = "Invalid request method or missing required POST parameters";
    echo json_encode($response);
}
?>
