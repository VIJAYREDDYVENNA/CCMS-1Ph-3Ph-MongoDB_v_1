<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';  // ✅ use MongoDB config
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$mobile_no     = $sessionVars['mobile_no'];
$user_id       = $sessionVars['user_id'];
$role          = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];
$user_name     = $sessionVars['user_name'];
$user_email    = $sessionVars['user_email'];

$response = ["status" => "error", "message" => ""];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID']) && isset($_POST['ON_OFF_MODE'])) {

	$device_ids = trim($_POST['D_ID']);
	$mode       = trim($_POST['ON_OFF_MODE']);
	$device_ids_array = explode(",", $device_ids);

    
	$user_permissions = $user_db_conn->user_permissions;
	$user_permission = $user_permissions->findOne(['login_id' => (int)$user_login_id]);

	if (!$user_permission || ((int)$user_permission['on_off_mode'] ?? 0) != 1) {
		$response["message"] = "No Permission to change the Operation Mode of the device(s)";
		echo json_encode($response);
		exit();
	}

    // ✅ 2. Decide mode description
	$on_off_activity = $mode . " mode Initiated";
	if ($mode === "AMBIENT_ASTRO") {
		$on_off_activity = "Ambient & Astronomical mode Initiated";
	} elseif ($mode === "AMBIENT") {
		$on_off_activity = "Ambient mode Initiated";
	} elseif ($mode === "ASTRO") {
		$mode = "ASTRONOMICAL";
		$on_off_activity = "Astronomical mode Initiated";
	}

    // ✅ 3. Loop through devices
	foreach ($device_ids_array as $device_id) {
		$device_id = trim(strtoupper($device_id));

        // (a) Insert into on_off_modes
		$devices_db_conn->on_off_modes->insertOne([
			'device_id' => $device_id,
			'on_off_mode' => $mode,
			'status'      => 'Initiated',
			'date_time'   => new MongoDB\BSON\UTCDateTime(),
			'user_mobile' => $mobile_no,
			'email'       => $user_email,
			'name'        => $user_name,
			'role'        => $role,
			

		]);

        // (b) Update device_settings (like ON DUPLICATE KEY UPDATE in MySQL → use updateOne with upsert)
		$devices_db_conn->device_settings->updateOne(
			['device_id' => $device_id, 'setting_type' => 'SCHEDULE_TIME'],			
			['$set' => ['setting_flag' => 0]],
			['upsert' => true]
		);

		$devices_db_conn->device_settings->updateOne(
			['device_id' => $device_id, 'setting_type' => 'ON_OFF_MODE'],
			['$set' => ['setting_flag' => 1]],
			['upsert' => true]
		);

		$devices_db_conn->device_settings->updateOne(
			['device_id' => $device_id, 'setting_type' => 'READ_SETTINGS'],		
			['$set' => ['setting_flag' => 1]],
			['upsert' => true]
		);

        // (c) Insert into user_activity_log
		$devices_db_conn->user_activity_log->insertOne([
			'device_id' => $device_id,
			'updated_field' => $on_off_activity,
			'date_time'     => new MongoDB\BSON\UTCDateTime(),
			'user_mobile'   => $mobile_no,
			'email'         => $user_email,
			'name'          => $user_name,
			'role'          => $role,
			'status'        => "Initiated"
		]);
	}

    // ✅ Success response
	$response["status"]  = "success";
	$response["message"] = $mode . " mode Initiated";
	echo json_encode($response);

} else {
	$response["message"] = "Invalid request method or missing required POST parameters";
	echo json_encode($response);
}
?>
