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

function safe($str) { return htmlspecialchars((string)$str, ENT_QUOTES); }

$data = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID']) && isset($_POST['RECORDS'])) {
	$device_ids = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);
	$records = filter_input(INPUT_POST, 'RECORDS', FILTER_SANITIZE_STRING);
	$alert = filter_input(INPUT_POST, 'ALERT', FILTER_SANITIZE_STRING);


   /* $device_ids="SM1PH_3";
    $records="LATEST";
    $alert="CURRENT";*/
    $device_id = strtoupper(trim($device_ids));
    $start_date = $end_date = "";
    $start_dt = $end_dt = null;

    if ($records === "DATE-RANGE" && isset($_POST['START_DATE']) && isset($_POST['END_DATE'])) {
    	$start_date = trim(filter_input(INPUT_POST, 'START_DATE', FILTER_SANITIZE_STRING));
    	$end_date = trim(filter_input(INPUT_POST, 'END_DATE', FILTER_SANITIZE_STRING));
    	$start_dt = new MongoDB\BSON\UTCDateTime((new DateTime($start_date . " 00:00:00", new DateTimeZone("Asia/Kolkata")))->getTimestamp()*1000);
    	$end_dt   = new MongoDB\BSON\UTCDateTime((new DateTime($end_date . " 23:59:59", new DateTimeZone("Asia/Kolkata")))->getTimestamp()*1000);
    }

    $filter = ["device_id" => $device_id];
    if ($start_dt && $end_dt) $filter['date_time'] = ['$gte' => $start_dt, '$lte' => $end_dt];
    
    $options = ["sort" => ["date_time" => -1], "limit" => $records === "LATEST" ? 50 : 500];

    // Main Alert Types Configuration
    $alerts = [
    	"ALL" => [
    		"collection" => "user_activity_log",
    		"fields" => ["updated_field","date_time","name","role","user_mobile","email"],
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-1'><th>Acivity</th><th>Date&Time</th><th>User Name</th><th>User Role</th><th>User Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) {
    			return "<td>".safe($r['updated_field']??'')."</td><td>".safe(convertToIST($r['date_time'] ?? '')??'')."</td><td>".safe($r['name']??'')."</td><td>".safe($r['role']??'')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 5
    	],
    	"ON_OFF" => [
    		"collection" => "on_off_activities",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-1'><th>Acivity</th><th>Time(Mins)</th><th>Status</th><th>Date&Time</th><th>User Name</th><th>User Role</th><th>User Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) use($green, $red, $normal) {
    			$c = $normal; $time = $r['time'] ?? '';
    			if (($r['on_off']??'') === "ON") { $c = $green; if ($time==0) $time = "--"; }
    			elseif(($r['on_off']??'') === "OFF") { $c = $red; if ($time==0) $time = "--"; }
    			return "<td $c>".safe($r['on_off']??'')."</td><td>".safe($time)."</td><td>".safe($r['status']??'')."</td><td>".safe(convertToIST($r['date_time'] ?? '')??'')."</td><td>".safe($r['name']??'')."</td><td>".safe($r['role']??'')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 7
    	],
    	"ON_OFF_MODES" => [
    		"collection" => "on_off_modes",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-1'><th>On-Off Mode</th><th>Status</th><th>Date&Time</th><th>User Name</th><th>User Role</th><th>User Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) use($green, $red, $orange, $primary, $normal) {
    			$c = $normal;
    			$status = $r['status'] ?? '';
    			if ($status === "In-Progress") $c = $orange;
    			else if ($status === "Pending") $c = $red;
    			else if ($status === "Updated") $c = $green;
    			else if ($status === "Initiated") $c = $primary;
    			return "<td>".safe($r['on_off_mode'] ?? '')."</td><td $c>".safe($status)."</td><td>".safe(convertToIST($r['date_time'] ?? '') ?? '')."</td><td>".safe($r['name'] ?? '')."</td><td>".safe($r['role'] ?? '')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 6
    	],
    	"ON_OFF_SCHEDULE" => [
    		"collection" => "on_off_schedule_time",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-1'><th>On-Time</th><th>Off-Time</th><th>Status</th><th>Date&Time</th><th>User Name</th><th>User Role</th><th>User Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) use($orange, $red, $green, $primary, $normal) {
    			$status = strtoupper($r['status'] ?? '');
    			$c = $normal;
    			if ($status === "IN-PROGRESS") $c = $orange;
    			else if ($status === "PENDING" || $status === "DISABLED") $c = $red;
    			else if ($status === "UPDATED" || $status === "ENABLED") $c = $green;
    			else if ($status === "INITIATED") $c = $primary;
    			return "<td>".safe($r['on_time'] ?? '')."</td><td>".safe($r['off_time'] ?? '')."</td><td $c>".safe($r['status'] ?? '')."</td><td>".safe(convertToIST($r['date_time'] ?? '') ?? '')."</td><td>".safe($r['name'] ?? '')."</td><td>".safe($r['role'] ?? '')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 7
    	],
    	"LOCATION" => [
    		"collection" => "coordinates_list",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-2'><th>Latitude</th><th>Longitude</th><th>Location</th><th>Date&Time</th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) {
    			$loc = "https://www.google.com/maps?q=".($r['latitude']??'').",".($r['longitude']??'');
    			$location = '<a href="'.safe($loc).'" target="_blank" class="link-underline link-underline-opacity-0 text-primary">Location</a>';
    			return "<td>".safe($r['latitude'] ?? '')."</td><td>".safe($r['longitude'] ?? '')."</td><td>".$location."</td><td>".safe(convertToIST($r['date_time'] ?? '') ?? '')."</td><td>".safe($r['name'] ?? '')."</td><td>".safe($r['role'] ?? '')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 7
    	],
    	"ADDRESS" => [
    		"collection" => "device_address",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-2'><th>Street</th><th>Town</th><th>City</th><th>District</th><th>State</th><th>Pincode</th><th>Country</th><th>Landmark</th><th>Date&Time</th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) {
    			return "<td>".safe($r['street'] ?? '')."</td><td>".safe($r['town'] ?? '')."</td><td>".safe($r['city'] ?? '')."</td><td>".safe($r['district'] ?? '')."</td><td>".safe($r['state'] ?? '')."</td><td>".safe($r['pincode'] ?? '')."</td><td>".safe($r['country'] ?? '')."</td><td>".safe($r['landmark'] ?? '')."</td><td>".safe(convertToIST($r['date_time'] ?? '') ?? '')."</td><td>".safe($r['name'] ?? '')."</td><td>".safe($r['role'] ?? '')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 12
    	],
    	"RESET-IOT" => [
    		"collection" => "iot_device_reset",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-2'><th>Command</th><th>Date&Time</th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) {
    			return "<td>".safe($r['reset']??'')."</td><td>".safe(convertToIST($r['date_time'] ?? '')??'')."</td><td>".safe($r['name']??'')."</td><td>".safe($r['role']??'')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 5
    	],
    	"RESET-ENERGY" => [
    		"collection" => "iot_reset_energy",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-2'><th>kWh</th><th>kVAh</th><th>Date&Time</th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) {
    			return "<td>".safe($r['kwh']??'')."</td><td>".safe($r['kvah']??'')."</td><td>".safe(convertToIST($r['date_time'] ?? '')??'')."</td><td>".safe($r['name']??'')."</td><td>".safe($r['role']??'')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 6
    	],
    	"HYSTERESIS" => [
    		"collection" => "iot_hysteresis",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-2'><th>Update Value</th><th>Date&Time</th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) {
    			return "<td>".safe($r['value']??'')."</td><td>".safe(convertToIST($r['date_time'] ?? '')??'')."</td><td>".safe($r['name']??'')."</td><td>".safe($r['role']??'')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 5
    	],
    	"WIFI-DETAILS" => [
    		"collection" => "iot_wifi_credentials",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-2'><th>SSID</th><th>Password</th><th>Date&Time</th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) {
    			return "<td>".safe($r['ssid']??'')."</td><td>".safe($r['password']??'')."</td><td>".safe(convertToIST($r['date_time'] ?? '')??'')."</td><td>".safe($r['name']??'')."</td><td>".safe($r['role']??'')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 6
    	],
    	"ID-UPDATE" => [
    		"collection" => "iot_device_id_change",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-2'><th>Old Device ID</th><th>Transferred To</th><th>Date&Time</th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) {
    			return "<td>".safe($r['device_id']??'')."</td><td>".safe($r['new_device_id']??'')."</td><td>".safe(convertToIST($r['date_time'] ?? '')??'')."</td><td>".safe($r['name']??'')."</td><td>".safe($r['role']??'')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 6
    	],
    	"SERIAL-ID-UPDATE" => [
    		"collection" => "iot_serial_id_change",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-2'><th>Updated New Serial No/ID</th><th>Date&Time</th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) {
    			return "<td>".safe($r['serial_id']??'')."</td><td>".safe(convertToIST($r['date_time'] ?? '')??'')."</td><td>".safe($r['name']??'')."</td><td>".safe($r['role']??'')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 5
    	],
    	"ON-OFF-INTERVAL" => [
    		"collection" => "iot_on_off_interval",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-2'><th>Interval Time(min)</th><th>Date&Time</th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) {
    			return "<td>".safe($r['value']??'')."</td><td>".safe(convertToIST($r['date_time'] ?? '')??'')."</td><td>".safe($r['name']??'')."</td><td>".safe($r['role']??'')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 5
    	],
    	"UNIT-CAPACITY" => [
    		"collection" => "unit_capacity",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-2'><th>Unit Capacity</th><th>Date&Time</th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) {
    			return "<td>".safe($r['capacity']??'')."</td><td>".safe(convertToIST($r['date_time'] ?? '')??'')."</td><td>".safe($r['name']??'')."</td><td>".safe($r['role']??'')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 5
    	],
    	"FRAME-TIME" => [
    		"collection" => "frame_time",
    		"header" => "<thead class='sticky-header text-center'><tr class='header-row-2'><th>Frame Update Time(Mins)</th><th>Status</th><th>Date&Time</th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>",
    		"row" => function($r) use($green, $red, $orange, $primary, $normal) {
    			$status = $r['status'] ?? '';
    			$class_r = $normal;
    			if ($status === "In-Progress") $class_r = $orange;
    			else if ($status === "Pending") $class_r = $red;
    			else if ($status === "Updated") $class_r = $green;
    			else if ($status === "Initiated") $class_r = $primary;
    			return "<td>".safe($r['frame_time']??'')."</td><td $class_r>".safe($status)."</td><td>".safe(convertToIST($r['date_time'] ?? '')??'')."</td><td>".safe($r['name']??'')."</td><td>".safe($r['role']??'')."</td><td>".safe(($r['user_mobile']??'')."/".($r['email']??''))."</td>";
    		},
    		"colspan" => 6
    	]
    ];

    // PHASE-based tables
    if ($alert === "VOLTAGE" || $alert === "CURRENT") {
    	$phase = get_phase($device_id, $user_db_conn);
    	if ($alert === "VOLTAGE") {
    		$data .= ($phase === "3PH")
    		? "<thead class='sticky-header text-center'><tr class='header-row-1'><th colspan='3'>Lower Threshold Voltage(V)</th><th colspan='3'>Upper Threshold Voltage(V)</th><th>Data & Time</th><th colspan='3'>User Details</th></tr>
    		<tr class='table-header-row-2'><th>R</th><th>Y</th><th>B</th><th>R</th><th>Y</th><th>B</th><th></th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>"
    		: "<thead class='sticky-header text-center'><tr class='header-row-2'><th>Lower Threshold</th><th>Upper Threshold</th><th>Data & Time</th><th colspan='3'>User Details</th></tr>
    		<tr class='table-header-row-2'><th>Voltage(V)</th><th>Voltage(V)</th><th></th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>";
    		$collection = "limits_voltage";
    		$fields = ($phase === "3PH")
    		? ["l_r","l_y","l_b","u_r","u_y","u_b","date_time","name","role","user_mobile","email"]
    		: ["l_r","u_r","date_time","name","role","user_mobile","email"];
    		$colspan = ($phase === "3PH") ? 10 : 6;
        } else { // CURRENT
        	$data .= ($phase === "3PH")
        	? "<thead class='sticky-header text-center'><tr class='header-row-1'><th colspan='3'>Current Limits(Amps)</th><th>Data & Time</th><th colspan='3'>User Details</th></tr>
        	<tr class='header-row-2'><th>R</th><th>Y</th><th>B</th><th></th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>"
        	: "<thead class='sticky-header text-center'><tr class='header-row-1'><th>Upper Threshold</th><th>Data & Time</th><th colspan='3'>User Details</th></tr>
        	<tr class='header-row-2'><th>Current(Amps)</th><th></th><th>Name</th><th>Role</th><th>Mobile/e-mail</th></tr></thead><tbody>";
        	$collection = "limits_current";
        	$fields = ($phase === "3PH")
        	? ["i_r","i_y","i_b","date_time","name","role","user_mobile","email"]
        	: ["i_r","date_time","name","role","user_mobile","email"];
        	$colspan = ($phase === "3PH") ? 7 : 5;
        }
    } elseif (isset($alerts[$alert])) {
    	$data .= $alerts[$alert]['header'];
    	$collection = $alerts[$alert]['collection'];
    	$fields = $alerts[$alert]['fields'] ?? null;
    	$colspan = $alerts[$alert]['colspan'] ?? 7;
    } else {
        // Unknown alert
    	$data .= "<thead><tr><th>Records</th></tr></thead><tbody>";
    	$collection = strtolower($alert);
    	$fields = null;
    	$colspan = 1;
    }

    $cursor = $devices_db_conn->$collection->find($filter, $options);
    $found = false;

    foreach ($cursor as $r) {
    	$found = true;
    	if ($alert === "VOLTAGE" || $alert === "CURRENT") {
    		$data .= "<tr>";
    		foreach ($fields as $f) {
    			if ($f === "user_mobile") {
    				$data .= "<td>".safe(($r['user_mobile'] ?? '') . "/" . ($r['email'] ?? ''))."</td>";
    			} else {
    				if ($f === "date_time") 
    				{
    					$data .= "<td>".safe(convertToIST($r['date_time'] ?? '')??'')."</td>";;
    				}
    				else
    				{
    					$data .= "<td>".safe($r[$f] ?? '')."</td>";
    				}
    			}
    		}
    		$data .= "</tr>";
    	} elseif (isset($alerts[$alert])) {
    		$data .= "<tr>".$alerts[$alert]['row']($r)."</tr>";
    	} else {
    		$data .= "<tr>";
            // If fields unknown, just print all string fields available
    		if ($fields) {
    			foreach ($fields as $f) {
    				$data .= "<td>".safe((string)($r[$f] ?? ''))."</td>";
    			}
    		} else {
    			foreach ($r as $k => $v) {
    				if (!is_array($v) && !is_object($v)) $data .= "<td>".safe((string)$v)."</td>";
    			}
    		}
    		$data .= "</tr>";
    	}
    }

    if (!$found) {
    	$data .= '<tr><td class="text-danger" colspan="'.$colspan.'">Records are not Found</td></tr>';
    }
    $data .= "</tbody>";

    echo json_encode($data);
}

function get_phase($id, $user_db_conn) {
	include_once("../../common-files/fetch-device-phase.php");

	return $device_phase;
}
function convertToIST($dateTimeValue) {
	if (empty($dateTimeValue)) return '';
	if ($dateTimeValue instanceof MongoDB\BSON\UTCDateTime) {
		$dt = $dateTimeValue->toDateTime();
		$dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
		return $dt->format('Y-m-d H:i:s');
	} else {
		try {
			$dt = new DateTime($dateTimeValue);
			$dt->modify('+5 hours 30 minutes');
			return $dt->format('Y-m-d H:i:s');
		} catch (Exception $e) {
			return '';
		}
	}
}

?>
