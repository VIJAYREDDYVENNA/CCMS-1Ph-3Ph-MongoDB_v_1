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

// MongoDB Client initialization assumed done elsewhere
// For example: $client = new MongoDB\Client('mongodb://localhost:27017');
// and $devices_db_conn = $client->ccms_data;

$normal = 'class="text-secondary-emphasis"';
$red = 'class="text-danger-emphasis fw-bold"';
$orange = 'class="text-warning-emphasis fw-bold"';
$green = 'class="text-success-emphasis fw-bold"';
$primary = 'class="text-info-emphasis fw-bold"';

$d_name = "";
$data = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID']) && isset($_POST['RECORDS'])) 
{
	$device_ids = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);
	$records = filter_input(INPUT_POST, 'RECORDS', FILTER_SANITIZE_STRING);
	$module = filter_input(INPUT_POST, 'MODULE', FILTER_SANITIZE_STRING) ?: "MODULES";

    // Date range filtering
	$filterDate = [];
	if ($records === "DATE-RANGE" && isset($_POST['START_DATE']) && isset($_POST['END_DATE'])) {
		$start_date = new MongoDB\BSON\UTCDateTime(
			(new DateTime($_POST['START_DATE'] . " 00:00:00", new DateTimeZone("Asia/Kolkata")))->getTimestamp() * 1000
		);
		$end_date = new MongoDB\BSON\UTCDateTime(
			(new DateTime($_POST['END_DATE'] . " 23:59:59", new DateTimeZone("Asia/Kolkata")))->getTimestamp() * 1000
		);
		$filterDate = ['date_time' => ['$gte' => $start_date, '$lte' => $end_date]];
	}

	$collectionMap = [
		"MODULES" => "system_status",
		"SIM-DIAGNOSIS" => "simcom_status",
		"SIM-MODULE-FAIL" => "sim_module_communication",
		"SIM-MODULE-REMOVAL" => "sim_module_removal",
		"BOX-TOP-COVER-OPEN-CLOSE" => "box_top_cover_activity",
	];

	if (!isset($collectionMap[$module])) {
		echo json_encode('<tr><td class="text-danger" colspan="12">Invalid module specified</td></tr>');
		exit;
	}

	$collectionName = $collectionMap[$module];
	$collection = $devices_db_conn->{$collectionName};

	$filter = ['device_id' => $device_ids];
	if ($records === "LATEST") {
		$options = ['sort' => ['id' => -1], 'limit' => 50];
	} else {
		$filter = $filterDate;
		$options = ['sort' => ['id' => -1]];
	}

	$cursor = $collection->find($filter, $options);

    // Now process each module separately
	if ($module === "MODULES") 
	{
		$data = "<thead class='sticky-header text-center'> 
		<tr class='header-row-1'>
		<th class='table-header-row-1'>Device ID</th><th class='table-header-row-1'>Updated On</th><th class='table-header-row-1'>RTC</th><th class='table-header-row-1'>FLASH</th><th class='table-header-row-1'>WIFI</th><th class='table-header-row-1'>ADE</th><th class='table-header-row-1'>DC Supply</th><th class='table-header-row-1'>GPS</th><th class='table-header-row-1'>ON/OFF Control</th><th class='table-header-row-1'>R-Contactor</th><th class='table-header-row-1'>Y-Contactor</th><th class='table-header-row-1'>B-Contactor</th></tr></thead><tbody>";

		$found = false;
		foreach ($cursor as $r) {
			$found = true;            
            // Convert date_time and prev_date_time to IST string
			$date_time_str = $r['date_time'] instanceof MongoDB\BSON\UTCDateTime ? $r['date_time']->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('H:i:s d-m-Y') : $r['date_time'];
			$prev_date_time_str = $r['prev_date_time'] instanceof MongoDB\BSON\UTCDateTime ? $r['prev_date_time']->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('H:i:s d-m-Y') : $r['prev_date_time'];
			$period = $prev_date_time_str . " <span class='text-primary'> To </span> " . $date_time_str;

			$statuslist = explode(",", $r['status'] ?? "");

			$class_1 = $normal; $sts_1 = ($statuslist[0] ?? '1') == "1" ? "Normal" : ($class_1 = $red) && "FAIL";
			$class_2 = $normal; $sts_2 = ($statuslist[1] ?? '1') == "1" ? "Normal" : ($class_2 = $red) && "FAIL";
			$class_3 = $normal; $sts_3 = ($statuslist[2] ?? '1') == "1" ? "Normal" : ($class_3 = $red) && "FAIL";
			$class_4 = $normal; $sts_4 = ($statuslist[3] ?? '1') == "1" ? "Normal" : ($class_4 = $red) && "FAIL";

			$class_5 = $normal;
			$sts_5 = "Normal";
			if (isset($statuslist[4])) {
				if (strpos($statuslist[4], ":") !== false) {
					switch ($statuslist[4]) {
						case "0:0": $sts_5 = "SMPS-1 & SMPS-2 FAIL"; $class_5 = $red; break;
						case "1:0": $sts_5 = "SMPS-1 FAIL"; $class_5 = $red; break;
						case "0:1": $sts_5 = "SMPS-2 FAIL"; $class_5 = $red; break;
						case "1:1": $sts_5 = "Normal"; break;
					}
				} else {
					if ($statuslist[4] == "1") $sts_5 = "Normal"; else { $sts_5 = "FAIL"; $class_5 = $red; }
				}
			}

			$class_6 = $normal; $sts_6 = ($statuslist[5] ?? '1') == "1" ? "Normal" : ($class_6 = $red) && "FAIL";

			$class_8 = $normal; $sts_8 = ($statuslist[7] ?? '0') == "1" ? "ON" : ($class_8 = $red) && "OFF";
			$class_9 = $normal; $sts_9 = ($statuslist[8] ?? '0') == "1" ? "ON" : ($class_9 = $red) && "OFF";
			$class_10 = $normal; $sts_10 = ($statuslist[9] ?? '0') == "1" ? "ON" : ($class_10 = $red) && "OFF";

			$class_7 = $normal;
			$sts_7 = "Auto OFF";
			switch ($statuslist[6] ?? "0") {
				case "0": $sts_7 = "Auto OFF"; $class_7 = $red; break;
				case "1": $sts_7 = "Auto ON"; $class_7 = $green; break;
				case "2": $sts_7 = "Power Fail"; $class_7 = $red; break;
				case "3": $sts_7 = "Server ON"; $class_7 = $green; break;
				case "4": $sts_7 = "APP ON"; $class_7 = $green; break;
				case "5": $sts_7 = "Manual ON"; $class_7 = $green; break;
				case "6": $sts_7 = "Server OFF"; $class_7 = $red; break;
				case "7": $sts_7 = "APP OFF"; $class_7 = $red; break;
			}

			$data .= "<tr>
			<td>" .  safe($r['device_id'] ?? '') . "</td>
			<td $normal>{$period}</td>
			<td $class_1>{$sts_1}</td>
			<td $class_2>{$sts_2}</td>
			<td $class_3>{$sts_3}</td>
			<td $class_4>{$sts_4}</td>
			<td $class_5>{$sts_5}</td>
			<td $class_6>{$sts_6}</td>
			<td $class_7>{$sts_7}</td>
			<td $class_8>{$sts_8}</td>
			<td $class_9>{$sts_9}</td>
			<td $class_10>{$sts_10}</td>
			</tr>";
		}

		if (!$found) {
			$data .= '<tr><td class="text-danger" colspan="12">Records are not Found</td></tr>';
		}
		$data .= "</tbody>";
	}
	elseif ($module == "SIM-DIAGNOSIS") {
        // Similar logic repeated for simcom_status collection with adapted status parsing...
		$collection = $devices_db_conn->simcom_status;

		$cursor = $collection->find($filterDate ?: [], ['sort' => ['id' => -1], 'limit' => ($records === "LATEST" ? 50 : 0)]);

		$data = "<thead class='sticky-header text-center'><tr>
		<th class='table-header-row-1'>Device ID</th><th class='table-header-row-1'>Updated On</th><th class='table-header-row-1'>SIM Detection</th><th class='table-header-row-1'>Network Status</th><th class='table-header-row-1'>GPRS Status</th>
		<th class='table-header-row-1'>Posting Status</th><th class='table-header-row-1'>Power Dip</th><th class='table-header-row-1'>Status Code</th></tr></thead><tbody>";

		$found = false;
		foreach ($cursor as $r) {
			$found = true;
			$date_time_str = $r['date_time'] instanceof MongoDB\BSON\UTCDateTime ? $r['date_time']->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('H:i:s d-m-Y') : $r['date_time'];
			$statuslist = explode(",", $r['status'] ?? "");

			$class_6 = $normal;
			$sts_6 = $r['status_code'] ?? '';
			if ($sts_6 == "202" || $sts_6 == "201") {
				$class_6 = $primary;
			}

			$statusLabels = ['Detected', 'Connected', 'Connected', 'Connected', 'DIP-Detected'];
			$statusColors = [$normal, $normal, $normal, $normal, $normal];

			for ($i = 0; $i < 5; $i++) {
				if (!isset($statuslist[$i]) || $statuslist[$i] != "1") {
					$statusLabels[$i] = ($i == 4) ? "No Issue" : "Failed";
					$statusColors[$i] = $red;
				} else {
					$statusColors[$i] = $green;
				}
			}

			$data .= "<tr>
			<td>" .  safe($r['device_id'] ?? '') . "</td>
			<td>{$date_time_str}</td>
			<td {$statusColors[0]}>{$statusLabels[0]}</td>
			<td {$statusColors[1]}>{$statusLabels[1]}</td>
			<td {$statusColors[2]}>{$statusLabels[2]}</td>
			<td {$statusColors[3]}>{$statusLabels[3]}</td>
			<td {$statusColors[4]}>{$statusLabels[4]}</td>
			<td {$class_6}>{$sts_6}</td>
			</tr>";
		}

		if (!$found) {
			$data .= '<tr><td class="text-danger" colspan="7">Records are not Found</td></tr>';
		}
		$data .= "</tbody>";
	}

    // SIM-MODULE-FAIL
	else if ($module == "SIM-MODULE-FAIL") {
		$collection = $devices_db_conn->sim_module_communication;

		$filter = ['device_id' => $device_ids]; 
		if ($records === "DATE-RANGE" && isset($start_date) && isset($end_date)) {
			$filter['date_time'] = ['$gte' => $start_date, '$lte' => $end_date];
		}

		$options = ['sort' => ['_id' => -1]]; 
		if ($records === "LATEST") {
			$options['limit'] = 50;
		}

		$cursor = $collection->find($filter, $options);

		$data = "<thead class='sticky-header text-center'><tr>
		<th class='table-header-row-1'>Device ID</th>
		<th class='table-header-row-1'>SIMCOM Fail Time</th>
		<th class='table-header-row-1'>Server Time</th>
		</tr></thead><tbody>";

		$found = false;
		foreach ($cursor as $r) {
			$found = true;

        // Convert times if stored as UTCDateTime
			$failTime = $r['date_time'] instanceof MongoDB\BSON\UTCDateTime
			? $r['date_time']->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('H:i:s d-m-Y')
			: $r['date_time'];
			$serverTime = $r['server_time'] instanceof MongoDB\BSON\UTCDateTime
			? $r['server_time']->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('H:i:s d-m-Y')
			: $r['server_time'];

			$data .= "<tr>
			<td>" .  safe($r['device_id'] ?? '') . "</td>
			<td>" . safe($failTime) . "</td>
			<td>" . safe($serverTime) . "</td>
			</tr>";
		}
		if (!$found) {
			$data .= '<tr><td class="text-danger" colspan="2">Records are not Found</td></tr>';
		}
		$data .= "</tbody>";
	}

// SIM-MODULE-REMOVAL
	elseif ($module == "SIM-MODULE-REMOVAL") 
	{
		$collection = $devices_db_conn->sim_module_removal;

		$filter = ['device_id' => $device_ids];
		if ($records === "DATE-RANGE" && isset($start_date) && isset($end_date)) {
			$filter['date_time'] = ['$gte' => $start_date, '$lte' => $end_date];
		}

		$options = ['sort' => ['_id' => -1]];
		if ($records === "LATEST") {
			$options['limit'] = 50;
		}

		$cursor = $collection->find($filter, $options);

		$data = "<thead class='sticky-header text-center'><tr>
		<th class='table-header-row-1'>Device_ID</th>
		<th class='table-header-row-1'>SIM MODULE ACTIVITY</th>
		<th class='table-header-row-1'>DATE Time</th>
		</tr></thead><tbody>";

		$found = false;
		foreach ($cursor as $r) {
			$found = true;

			$class_act = $normal;
			if (!empty($r['activity'])) {
				$status = $r['activity'];
				$class_act = ($status === 'Removed') ? $red : $green;
			}

			$date_time_str = $r['date_time'] instanceof MongoDB\BSON\UTCDateTime
			? $r['date_time']->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('H:i:s d-m-Y')
			: $r['date_time'];

			$data .= "<tr>
			<td>" . safe($r['device_id'] ?? '') . "</td>
			<td $class_act>" . safe($r['activity'] ?? '') . "</td>
			<td>" . safe($date_time_str) . "</td>
			</tr>";
		}
		if (!$found) {
			$data .= '<tr><td class="text-danger" colspan="3">Records are not Found</td></tr>';
		}
		$data .= "</tbody>";
	}
	else if ($module == "BOX-TOP-COVER-OPEN-CLOSE") 
	{
		$collection = $devices_db_conn->box_top_cover_activity;


		$filter = ['device_id' => $device_ids];

		if ($records === "DATE-RANGE" && isset($start_date) && isset($end_date)) {
			$filter['date_time'] = ['$gte' => $start_date, '$lte' => $end_date];
		}

		$options = ['sort' => ['_id' => -1]];
		if ($records === "LATEST") {
			$options['limit'] = 50;
		}

		$cursor = $collection->find($filter, $options);


		$data = "<thead class='sticky-header text-center'>
		<tr>
		<th class='table-header-row-1'>Device_ID</th>
		<th class='table-header-row-1'>Box Top Cover Activity</th>
		<th class='table-header-row-1'>Date Time</th>
		</tr>
		</thead><tbody>";



		$found = false;
		foreach ($cursor as $r) {
			$found = true;

        // Default class
			$class_act = $normal;

			if (!empty($r['activity'])) {
				$status = $r['activity'];
				if ($status === 'Opened') {
					$class_act = $red;
				} elseif ($status === 'Closed') {
					$class_act = $green;
				}
			}



        // Handle date_time properly
			if (isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
				$date_time_str = $r['date_time']->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('H:i:s d-m-Y');
			} else {
				$date_time_str = "";
			}


			$data .= "<tr>
			<td>" . safe($r['device_id'] ?? '') . "</td>
			<td $class_act>" . safe($r['activity'] ?? '') . "</td>
			<td>" . safe($date_time_str) . "</td>
			</tr>";
			echo json_encode($data);
			exit;
		}

		if (!$found) {
			$data .= '<tr><td class="text-danger" colspan="3">No Records Found</td></tr>';
		}

		$data .= "</tbody>";
	}


	echo json_encode($data);
}
function safe($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
