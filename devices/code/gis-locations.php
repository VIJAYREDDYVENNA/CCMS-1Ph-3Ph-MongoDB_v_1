
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
$client = $sessionVars['client'];
//=================================================
$return_response = "";
$total_switch_point=0;
$user_devices="";
//=================================================
$send=array();
$lat=0.0;
$long=0.0;
//$group_id = "ALL";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
	$group_id = $_POST['GROUP_ID'];

	include_once(BASE_PATH_1."common-files/selecting_group_device.php");

	if($user_devices!="")
	{
		$user_devices= substr($user_devices, 0, -1);
	}



	$date = "";
	$signal = "";
	$address = "";
	$land_mark = "";
	$rated_kva = "";
	$installation_date = "";
	$device_status = "";
	$on_off_status = "";
	$unit_capacity = "";
	$operation_mode = "";
	$installation_status = "";
	$installed_lights = 0;

// explode user_devices string into array
	$user_devices_arr = array_map(function($id){
		return trim($id, "'");
	}, explode(",", $user_devices));

// query live_data_updates
	$cursor = $devices_db_conn->live_data_updates->find(
		[
			'device_id' => ['$in' => $user_devices_arr]
		],
		[
			'sort' => [
            'device_id' => 1   // basic ascending order
        ]
    ]
);

	foreach ($cursor as $r) {
		$installation_date = "";
		if (isset($r['installed_date']) && $r['installed_date'] instanceof MongoDB\BSON\UTCDateTime) {
			$installation_date = $r['installed_date']->toDateTime()->format("d-m-Y");
		}

		$frame_date_time = "";
		if (isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
			$frame_date_time = $r['date_time']->toDateTime()->modify('+5 hours 30 minutes')->format("H:i:s d-m-Y");
		}
		$device_id = $r['device_id'];
		$status = $r['active_device'] ?? 0;
		$unit_capacity = $r['unit_capacity'] ?? 0;
		$operation_mode = $r['operation_mode'] ?? "";
		$installed_lights = $r['total_lights'] ?? 0;

		$v1 = $r['voltage_ph1'] ?? 0;
		$v2 = $r['voltage_ph2'] ?? 0;
		$v3 = $r['voltage_ph3'] ?? 0;
		$c1 = $r['current_ph1'] ?? 0;
		$c2 = $r['current_ph2'] ?? 0;
		$c3 = $r['current_ph3'] ?? 0;
		$kwh = $r['energy_kwh_total'] ?? 0;
		$kvah = $r['energy_kvah_total'] ?? 0;
		$on_off_status = $r['on_off_status'] ?? 0;
		$phase = $r['phase'] ?? "";
		$google_location = "";

    // --- location handling ---
		$lat = 0.0;
		$long = 0.0;
		if (isset($r['location']) && $r['location'] != '0,0' && strpos($r['location'], "0000000,000000") === false) {
			$coordinates = $r['location'];
			$co_array = explode(',', $coordinates);

			$lat = (double)$co_array[0];
			$long = (double)$co_array[1];

			/*if (!empty($co_array[0]) && !empty($co_array[1])) {
				try {
					$lat = (double)(trim($co_array[0]));
					$long = (double)(trim($co_array[1]));
					$coordinates = $lat . "," . $long;
				} catch (Exception $e) {}
			}*/
			$google_location = "https://www.google.co.in/maps?q=" . $coordinates;
		}

    // --- on/off logic ---
		if ($on_off_status == 1 || $on_off_status == 3 || $on_off_status == 4) {
			$on_off_status = "ON";
			$status = 1;
		} elseif ($on_off_status == 5) {
			$on_off_status = "MANUAL ON";
			$status = 1;
		} else {
			$on_off_status = "OFF";
			$status = 0;
		}
		if (($r['power_failure'] ?? 0) == 1) {
			$status = 3;
		} elseif (($r['poor_network'] ?? 0) == 1) {
			$status = 2;
		} elseif (($r['faulty'] ?? 0) == 1) {
			$status = 4;
		}

    // --- resolve device name ---
		$name = $device_id;
		$device_ids = array_column($device_list, 'D_ID');
		$index = array_search($device_id, $device_ids);
		if ($index !== false) {
			$name = $device_list[$index]['D_ID'];
		}

    // --- window content ---
		$window = "";
		if ($phase == "3PH") {
			$window = '<h5 class="text-primary mb-0">CCMS Info</h5> <hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black"><label class="fw-bold p-0">Device ID: </label><label> ' . $name . ' </label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black "><label class="fw-bold">Last Updated at: </label><label>' . $frame_date_time . '</label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black"><label class="fw-bold">On/Off Status: </label><label>' . $on_off_status . '</label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black"><label class="fw-bold">Voltage(V):</label><label><b> R =</b>' . $v1 . '<b> , Y =</b>' . $v2 . '<b> , B =</b>' . $v3 . '</label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black"/>' .
			'<div class="font-small text-black"><label class="fw-bold">Current(A): </label><label><b> R =</b>' . $c1 . '<b> , Y =</b>' . $c2 . '<b> , B =</b>' . $c3 . '</label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black"><label class="fw-bold">Energy(Units): </label><label><b> kWh =</b>' . $kwh . ' </label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black"><label class="fw-bold">Energy(Units): </label><label><b> kVAh =</b>' . $kvah . ' </label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black"><label class="fw-bold">Location: </label><a href=' . $google_location . ' target="_blank"> Google Map</a> </div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">';
		} elseif ($phase == "1PH") {
			$window = '<h5 class="text-primary mb-0">CCMS Info</h5> <hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black"><label class="fw-bold p-0">Device ID: </label><label> ' . $name . ' </label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black "><label class="fw-bold">Last Updated at: </label><label>' . $frame_date_time . '</label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black"><label class="fw-bold">On/Off Status: </label><label>' . $on_off_status . '</label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black"><label class="fw-bold">Voltage(V):</label><label>' . $v1 . '</label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black"/>' .
			'<div class="font-small text-black"><label class="fw-bold">Current(A): </label><label>' . $c1 . '</label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black"><label class="fw-bold">Energy(Units): </label><label><b> kWh =</b>' . $kwh . ' </label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black"><label class="fw-bold">Energy(Units): </label><label><b> kVAh =</b>' . $kvah . ' </label></div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">' .
			'<div class="font-small text-black"><label class="fw-bold">Location: </label><a href=' . $google_location . ' target="_blank"> Google Map</a> </div>' .
			'<hr class="m-0 pt-2 mt-2 text-black">';
		}

		$send[] = ["va" => $window, 'l1' => $lat, "l2" => $long, "icon" => $status, "id" => $name];
	}

	echo json_encode([$send, strtoupper($client_name ?? $client)]);
	//echo json_encode(array($send, strtoupper($client)));
}
else
{
	$return_response="Data not Available";
}


function convert_DMS_DD($coordinate)
{
	$array_split=explode('.', $coordinate);
	$deg=(int)($array_split[0]/100);
	$time=((float)$coordinate-$deg*100)/60;
	return $decimal=round($deg+$time, 7);	
}

?>