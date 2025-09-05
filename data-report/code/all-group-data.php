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
$permission_check = 0;

$selection="ALL";

$d_name = "";
$data = "";
$phase = "3PH";
$selected_phase ;
$count = 0;
$device_list = json_decode($_SESSION["DEVICES_LIST"]);
$selected_phase1 ="3PH";
$phase_row="ALL";
$send = array();
$send = "";
$user_devices = "";
foreach ($device_list as $key => $value) {
	$id = $value->D_ID;
	$user_devices = $user_devices . "'" . $id . "',";
}
if ($user_devices != "") {
	$user_devices = substr($user_devices, 0, -1);
}

$device_ids_array = [];
foreach ($device_list as $device) {
	if (isset($device->D_ID)) {
		$device_ids_array[] = $device->D_ID;
	}
}

$collection = $devices_db_conn->live_data_updates;

    // Distinct phases among devices
$phases = $collection->distinct('phase', ['device_id' => ['$in' => $device_ids_array]]);


if (count($phases) == 2) {
	$phase_row = "ALL";
	$selected_phase1 = "ALL";
} elseif (count($phases) == 1) {
	$phase_row = ($phases[0] === "3PH") ? "3PH" : "1PH";
	$selected_phase1 = $phase_row;
} else {
	$selected_phase1 = "ALL";
}

    // Fetch devices sorted by phase desc and device_id lexically (cannot replicate MySQL substring)



$cursor = $collection->find(
	['device_id' => ['$in' => $device_ids_array]],
	['sort' => ['phase' => -1, 'device_id' => 1]]
);

$device_ids = array_column($device_list, 'D_ID');

foreach ($cursor as $r) {
	$index = array_search($r['device_id'], $device_ids);
	if ($index !== false) {



		if ($phase_row== "1PH") {
			$phase="1PH";
			$selection = "1PH";
			$selected_phase = "1PH";
		} else {
			$phase = $r['phase'];

			$selection = "ALL";
			$selected_phase = $_SESSION["SELECTED_PHASE"];
		}



		
		/*if ($phase_row == "1PH") {
			$phase = "1PH";
			$selected_phase = "1PH";
		} else {
			$selection = "ALL";
			$phase = $r['phase'] ?? "ALL";
			$selected_phase = $_SESSION["SELECTED_PHASE"] ?? "ALL";
		}*/

		

		$device_id = strtoupper($device_list[$index]->D_ID);
		$d_name = " (" . ($device_list[$index]->D_NAME ?? '') . ")";

            // Include parameters
		include("set_parameters.php");

            // Include table cells logic or integrate it here
		include("table_cells.php");
	}
}


$selected_phase = $selected_phase1;






echo json_encode(array($data, $selected_phase));

?>
