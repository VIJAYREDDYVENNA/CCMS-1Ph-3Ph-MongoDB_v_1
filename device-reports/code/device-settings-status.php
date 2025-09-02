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

$normal='class=""';
$red='class="text-danger-emphasis fw-bold"'; 
$orange='class="text-warning-emphasis fw-bold"'; 
$green='class="text-success-emphasis fw-bold"';  
$primary='class="text-info-emphasis fw-bold"'; 
$class_r=$normal;
$class_y=$green;
$class_b=$green;

$d_name = "";
$data = "";

function safe($str) {
	return htmlspecialchars((string)$str, ENT_QUOTES);
}

function convertToIST($dateTimeValue) {
    if (empty($dateTimeValue)) return '';
    try {
        if ($dateTimeValue instanceof MongoDB\BSON\UTCDateTime) {
            $dt = $dateTimeValue->toDateTime();
        } else {
            $dt = new DateTime($dateTimeValue);
        }
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $ex) {
        return (string)$dateTimeValue;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID']) && isset($_POST['RECORDS'])) {
	$device_id = strtoupper(trim(filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING)));
	$records = filter_input(INPUT_POST, 'RECORDS', FILTER_SANITIZE_STRING);
	$alert = filter_input(INPUT_POST, 'ALERT', FILTER_SANITIZE_STRING);


//	$alert= "SAVED-SETTINGS"

	$start_date = null;
	$end_date = null;
	if ($records === "DATE-RANGE" && isset($_POST['START_DATE']) && isset($_POST['END_DATE'])) {
		$start_date = new MongoDB\BSON\UTCDateTime((new DateTime($_POST['START_DATE']." 00:00:00", new DateTimeZone('Asia/Kolkata')))->getTimestamp()*1000);
		$end_date = new MongoDB\BSON\UTCDateTime((new DateTime($_POST['END_DATE']." 23:59:59", new DateTimeZone('Asia/Kolkata')))->getTimestamp()*1000);
	}

	// MongoDB connection and selection of DB
	// Assuming $mongoClient is your MongoDB\Client instance connected already
	

	if ($alert == "PING-UPDATES") {
		$collection =  $devices_db_conn->device_check_report;
		$filter = ['device_id' => $device_id];
		$options = ['sort' => ['date_time' => -1]];
		if ($records === "LATEST") {
			$options['limit'] = 100;
		} elseif ($records === "DATE-RANGE" && $start_date && $end_date) {
			$filter['date_time'] = ['$gte' => $start_date, '$lte' => $end_date];
		}

		$cursor = $collection->find($filter, $options);

		$data = "<thead class='sticky-header text-center'>
    		<tr class='header-row-1'>                                    
    		<th class='table-header-row-1'>Parameter</th>                                
    		<th class='table-header-row-1'>Date&Time</th>                                
    		<th class='table-header-row-1'>Status</th>
    		</tr></thead><tbody>";

		$found = false;
		foreach ($cursor as $r) {
			$found = true;
			$date_time_ist = convertToIST($r['date_time'] ?? '');
			$data .= "<tr>
				<td>" . safe($r['parameter'] ?? '') . "</td>
				<td>" . safe($date_time_ist) . "</td>
				<td>" . safe($r['status'] ?? '') . "</td>
			</tr>";
		}
		if (!$found) {
			$data .= '<tr><td class="text-danger" colspan="3">Records are not Found</td></tr>';
		}
		$data .= "</tbody>";
	}

	if ($alert == "SAVED-SETTINGS") {
		$collection = $devices_db_conn->saved_settings_on_device;
		$filter = ['device_id' => $device_id];
		$options = ['sort' => ['_id' => -1]];
		if ($records === "LATEST") {
			$options['limit'] = 50;
		} /*elseif ($records === "DATE-RANGE" && $start_date && $end_date) {
			$filter['date_time'] = ['$gte' => $start_date, '$lte' => $end_date];
		}*/

		$cursor = $collection->find($filter, $options);

		$row_count = 45;
		$data = "<thead class='sticky-header text-center'><tr class='header-row-1'>";
		for ($i = 1; $i <= $row_count; $i++) {
			$data .= "<th class='table-header-row-1'>Value " . $i . "</th>";
		}
		$data .= "</tr></thead><tbody>";

		$found = false;
		foreach ($cursor as $r) {
			$found = true;
			if (!empty($r['frame'])) {
				$frame_array = explode(";", $r['frame']);
				$data .= "<tr>";
				foreach ($frame_array as $value) {
					$data .= "<td>" . safe($value) . "</td>";
				}
				$data .= "</tr>";
			}
		}
		if (!$found) {
			$data .= '<tr><td class="text-danger" colspan="' . $row_count . '">Records are not Found</td></tr>';
		}
		$data .= "</tbody>";
	}

	echo json_encode($data);
}

function sanitize_input($data, $conn) {
	$data = trim($data);
	$data = stripslashes($data);
	$data = htmlspecialchars($data);
	return mysqli_real_escape_string($conn, $data);
}
?>
