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



// Permission check for iot_settings


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['device_id'])) {
	$device_id = filter_input(INPUT_POST, 'device_id', FILTER_SANITIZE_STRING);
	$command = filter_input(INPUT_POST, 'command', FILTER_SANITIZE_STRING);

	if (!$command || !$device_id) {
		
		$response["message"] = "Invalid Command/Device-id.";
		echo json_encode($response);
		exit();
	} 

	$permissionDoc = $user_db_conn->user_permissions->findOne(
		["login_id" => (int)$user_login_id],
		["projection" => ["iot_settings" => 1]]
	);

	if (!$permissionDoc || (int)$permissionDoc["iot_settings"] !== 1) {
		$response["message"] = "No Permission to change the Current settings of the device(s)";
		echo json_encode($response);
		exit();
	}


	$device_id_upper = strtoupper($device_id);
	$dtIST = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
	$date_time = new MongoDB\BSON\UTCDateTime($dtIST->getTimestamp() * 1000);

	$devices_db_conn->custom_command->insertOne([
		"device_id"   => $device_id_upper,
		"command"      => strtoupper(trim($command)),
		"date_time"  => $date_time,
		"status" => "Initiated"
	]);

	$devices_db_conn->device_settings->updateOne(
		["device_id" => $device_id_upper, "setting_type" => "CUSTOM-CMD"],
		['$set' => ["setting_flag" => 1]],
		["upsert" => true]
	);

	$response["status"] = "success";
	$response["message"] = "Successfully Updated";
	echo json_encode($response);
	exit();
}


?>



