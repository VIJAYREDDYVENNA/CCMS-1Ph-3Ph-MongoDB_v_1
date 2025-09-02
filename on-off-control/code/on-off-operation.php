<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1.'config_db/config.php';
require_once BASE_PATH_1.'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];
$user_name = $sessionVars['user_name'];
$user_email = $sessionVars['user_email'];

$response = ["status" => "error", "message" => ""];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['D_ID']) && isset($_POST['ON_OFF_MODE']) && isset($_POST['TIME'])) {

	$device_ids = htmlspecialchars($_POST['D_ID']);
	$mode = htmlspecialchars($_POST['ON_OFF_MODE']);
	$time = htmlspecialchars($_POST['TIME']);
	$device_ids_array = explode(",", $device_ids);

    // Check permission from user_permissions collection
	$permissionDoc = $user_db_conn->user_permissions->findOne(
		['login_id' => (int)$user_login_id],
		['projection' => ['on_off_control' => 1]]
	);
	$permission_check = (int)$permissionDoc['on_off_control'] ?? 0;

	if ($permission_check != 1) {
		$response["message"] = "No permission to turn on/off devices";
		echo json_encode($response);
		exit();
	}

	foreach ($device_ids_array as $device_id) {
		$device_id=trim(strtoupper($device_id));
        // Insert on_off_activities document
		$insertResult = $devices_db_conn->on_off_activities->insertOne([
			'device_id' => $device_id,
			'on_off' => $mode,
			'time' => $time,
			'date_time' => new MongoDB\BSON\UTCDateTime(),
			'user_mobile' => $mobile_no,
			'email' => $user_email,
			'name' => $user_name,
			'role' => $role,
			'status'=>"Initiated"
		]);

		if (!$insertResult->isAcknowledged()) {
			$response["message"] = "Failed to insert on/off activity for device $device_id";
			echo json_encode($response);
			exit();
		}

        // Update or insert device_settings
		$updateResult = $devices_db_conn->device_settings->updateOne(
			['device_id' => $device_id, 'setting_type' => 'ONOFF'],
			['$set' => ["setting_flag" => 1]],
			["upsert" => true]
		); 

		

        // Insert user activity log
			$logActivity = "Initiated lights " . $mode;
			$logResult = $devices_db_conn->user_activity_log->insertOne([
				'device_id' => trim(strtoupper($device_id)),
				'updated_field' => $logActivity,
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
		$response["message"] = $mode . " updated successfully";
		echo json_encode($response);
	} else {
		$response["message"] = "Invalid request method or missing required POST parameters";
		echo json_encode($response);
	}
	?>
