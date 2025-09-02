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

$return_response = "";
$user_devices = "";
$device_list = array();
$total_switch_point = 0;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["GROUP_ID"])) {
	$group_id = $_POST['GROUP_ID'];

	//$group_id ="ALL";

	include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");
	$_SESSION["DEVICES_LIST"] = json_encode($device_list);

	if ($user_devices != "") {
		$user_devices = substr($user_devices, 0, -1);
	}

	$device_ids = explode(",", $user_devices);

    // explode string into array
	$device_ids = array_map('trim', explode(",", $user_devices));



	$device_ids = array_map(function($id) {
		return trim($id, "'");  
	}, $device_ids);

	if (empty($device_ids)) {
		echo json_encode(['error' => 'No devices found for this group']);
		exit;
	}

	try {
		$alertsCollection = $devices_db_conn->alerts_and_updates;

		$filter = ['device_id' => ['$in' => $device_ids]];

		$options = [
			'sort' => ['date_time' => -1],   
			'limit' => 100
		];

		$cursor = $alertsCollection->find($filter, $options);

		foreach ($cursor as $rl) {
			$device_id_name = $rl['device_id_name'] ?? '';
			$update = $rl['update'] ?? '';
			/*$date_time = $rl['date_time'] ?? ''; */
			$date_time = $rl['date_time']->toDateTime()->modify('+5 hours 30 minutes')->format("Y-m-d H:i:s");

			$return_response .= '<a href="#" class="list-group-item list-group-item-action" aria-current="true">
			<div class="d-flex w-100 justify-content-between">
			<small class="mb-1 sub-sup-font-size fw-medium text-primary d-flex align-content-center">
			<i class="bi bi-cpu pe-2"></i><span id="alert_id">' . htmlspecialchars($device_id_name) . '</span>
			</small>
			</div>
			<small class="mb-1 font-small text-info-emphasis">' . htmlspecialchars($update) . '</small>
			<div class="d-flex w-100 justify-content-end">
			<small class="mb-1 font-x-small text-primary d-flex align-content-center">
			<i class="bi bi-clock pe-1"></i><span id="alert_date_time">' . htmlspecialchars($date_time) . '</span>
			</small>
			</div>
			</a>';
		}
	} catch (Exception $e) {
		error_log('Error fetching alerts: ' . $e->getMessage());
		$return_response = '<div class="alert alert-danger p-2" style="font-size:0.8rem;">
		Error loading alerts. Please try again.
		</div>';
	}

	echo json_encode($return_response);
}
	?>
