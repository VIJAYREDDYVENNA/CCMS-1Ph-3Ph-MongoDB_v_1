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
$device_list = array();
$user_devices = "";
$total_switch_point = 0;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["GROUP_ID"])) {
	$group_id = $_POST['GROUP_ID'];


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
        // MongoDB collection connection
		//$devices_db_conn = $client->ccms_devices_db;

        // 1. Aggregate total wattage, lights, poor_network, power_failure, faulty, energy_kwh_total, installed_status
		$lightsPipeline = [
			[
				'$match' => [
					'device_id' => ['$in' => $device_ids]
				]
			],
			[
				'$group' => [
					'_id' => null,
					'total_wattage' => ['$sum' => ['$ifNull' => ['$lights_wattage', 0]]],
					'lights' => ['$sum' => ['$ifNull' => ['$total_lights', 0]]],
					'poor_network' => ['$sum' => ['$ifNull' => ['$poor_network', 0]]],
					'power_failure' => ['$sum' => ['$ifNull' => ['$power_failure', 0]]],
					'faulty' => ['$sum' => ['$ifNull' => ['$faulty', 0]]],
					'energy_kwh_total' => ['$sum' => ['$ifNull' => ['$energy_kwh_total', 0]]],
					'installed_status' => ['$sum' => ['$ifNull' => ['$installed_status', 0]]]
				]
			]
		];




        $lightsResult = $devices_db_conn->live_data_updates->aggregate($lightsPipeline);
        $lightsData = iterator_to_array($lightsResult);
        $installed_lights = 0;
        $installed_load = 0;
        $installed_switch_points = 0;
        $kwh = 0;

        if (!empty($lightsData)) {
        	$data = $lightsData[0];
        	$installed_load = $data['total_wattage'] ?? 0;
        	$installed_lights = $data['lights'] ?? 0;
        	$installed_switch_points = $data['installed_status'] ?? 0;
        	$kwh = $data['energy_kwh_total'] ?? 0;
        	$poor_network = $data['poor_network'] ?? 0;
        	$power_failure_count = $data['power_failure'] ?? 0;
        	$inactive_switch_points = $data['faulty'] ?? 0;
        } else {
        	$poor_network = $power_failure_count = $inactive_switch_points = 0;
        }

        // 2. Aggregate active_device, poor_network, power_failure, faulty where installed_status = 1
        $installedStatusPipeline = [
        	[
        		'$match' => [
        			'device_id' => ['$in' => $device_ids],
        			'installed_status' => 1
        		]
        	],
        	[
        		'$group' => [
        			'_id' => null,
        			'active_device' => ['$sum' => ['$ifNull' => ['$active_device', 0]]],
        			'poor_network' => ['$sum' => ['$ifNull' => ['$poor_network', 0]]],
        			'power_failure' => ['$sum' => ['$ifNull' => ['$power_failure', 0]]],
        			'faulty' => ['$sum' => ['$ifNull' => ['$faulty', 0]]]
        		]
        	]
        ];

        $installedStatusResult = $devices_db_conn->live_data_updates->aggregate($installedStatusPipeline);
        $installedStatusData = iterator_to_array($installedStatusResult);

        $active_switch_points = 0;
        if (!empty($installedStatusData)) {
        	$data = $installedStatusData[0];
        	$active_switch_points = $data['active_device'] ?? 0;
            // Overwrite poor_network, power_failure_count, inactive_switch_points if needed (optional)
        	$poor_network = $data['poor_network'] ?? $poor_network;
        	$power_failure_count = $data['power_failure'] ?? $power_failure_count;
        	$inactive_switch_points = $data['faulty'] ?? $inactive_switch_points;
        }

        // 3. Aggregate kw_total, kva_total where active_device = 1
        $activeStatusPipeline = [
        	[
        		'$match' => [
        			'device_id' => ['$in' => $device_ids],
        			'active_device' => 1
        		]
        	],
        	[
        		'$group' => [
        			'_id' => null,
        			'kw_total' => ['$sum' => ['$ifNull' => ['$kw_total', 0]]],
        			'kva_total' => ['$sum' => ['$ifNull' => ['$kva_total', 0]]]
        		]
        	]
        ];

        $activeStatusResult = $devices_db_conn->live_data_updates->aggregate($activeStatusPipeline);
        $activeStatusData = iterator_to_array($activeStatusResult);

        $kw = 0;
        $kva = 0;
        if (!empty($activeStatusData)) {
        	$data = $activeStatusData[0];
        	$kw = $data['kw_total'] ?? 0;
        	$kva = $data['kva_total'] ?? 0;
        }

        // 4. Count on_off_status grouped counts where device active_device=1
        $onOffStatusPipeline = [
        	[
        		'$match' => [
        			'device_id' => ['$in' => $device_ids],
        			'active_device' => 1
        		]
        	],
        	[
        		'$group' => [
        			'_id' => '$on_off_status',
        			'count' => ['$sum' => 1]
        		]
        	]
        ];

        $onOffStatusResult = $devices_db_conn->live_data_updates->aggregate($onOffStatusPipeline);
        $onOffStatusData = iterator_to_array($onOffStatusResult);

        $auto_system_on = 0;
        $manual_on = 0;
        $off = 0;

        foreach ($onOffStatusData as $row) {
        	$on_off_status = $row['_id'];
        	$count = $row['count'] ?? 0;

        	switch ($on_off_status) {
        		case 1:
        		case 3:
        		case 4:
        		$auto_system_on += $count;
        		break;
        		case 5:
        		$manual_on = $count;
        		break;
        		default:
        		$off += $count;
        		break;
        	}
        }

        // Calculation for uninstalled devices (assuming $total_switch_point is total devices count)
        $total_switch_point = count($device_ids);
        $uninstalled_devices = $total_switch_point - $installed_switch_points;

        // Additional calculations
        $kw = round($kw * 1000, 3); // Convert kw to watts?
        $total_load = 0;
        $off_percentage = 0;

        if ($installed_lights <= 0 || $installed_lights === null) {
        	$off_percentage = "--";
        	$total_load = "--";
        	$installed_lights = "--";
        }

        if ($installed_load > 0 && $installed_load !== null && $installed_load !== "") {
        	if ($kw != 0) {
        		$total_load = round(($kw / $installed_load) * 100, 2);
        	}
        } else {
        	$installed_load = 0;
        }

        if ($total_load > 0 && $total_load <= 100) {
        	$off_percentage = round((100 - $total_load), 2);
        } else if ($total_load > 100) {
        	$total_load = 100;
        	$off_percentage = 0;
        } else {
        	$off_percentage = 100;
        	if ($installed_lights <= 0 || $installed_lights === null) {
        		$off_percentage = "--";
        		$total_load = "--";
        		$installed_lights = "--";
        	}
        }

        // Calculations for power consumption savings
        $TotalPowerConsumed = $kwh;
        $TotalPowerConsumedHPSV = $TotalPowerConsumed * 10 / 7;
        $unitsSaved = $TotalPowerConsumedHPSV - $TotalPowerConsumed;
        $amountSaved = $unitsSaved * 6.25;
        $amountCo2 = $unitsSaved * 0.82;

        // Prepare response array
        $return_response = array(
        	"TOTAL_UNITS" => $total_switch_point,
        	"SWITCH_POINTS" => $installed_switch_points,
        	"UNISTALLED_UNITS" => $uninstalled_devices,
        	"ACTIVE_SWITCH" => $active_switch_points,
        	"POOR_NW" => $poor_network,
        	"POWER_FAILURE" => $power_failure_count,
        	"FAULTY_SWITCH" => $inactive_switch_points,
        	"TOTAL_LIGHTS" => $installed_lights,
        	"ON_LIGHTS" => $total_load,
        	"OFF_LIGHT" => $off_percentage,
        	"FAULTY_LIGHT" => "0",
        	"INSTALLED_LOAD" => $installed_load,
        	"ACTIVE_LOAD" => $kw,
        	"KWH" => round($kwh, 2),
        	"KVAH" => round($kva, 2),
        	"SAVED_UNITS" => round($unitsSaved, 2),
        	"SAVED_AMOUNT" => round($amountSaved, 2),
        	"SAVED_CO2" => round($amountCo2, 2),
        	"ON_UNITS" => $auto_system_on,
        	"MANUAL_ON" => $manual_on,
        	"OFF" => $off
        );
    } catch (Exception $e) {
    	error_log("Error fetching device data: " . $e->getMessage());
    	$return_response = array(
    		"TOTAL_UNITS" => 0,
    		"SWITCH_POINTS" => 0,
    		"UNISTALLED_UNITS" => 0,
    		"ACTIVE_SWITCH" => 0,
    		"POOR_NW" => 0,
    		"POWER_FAILURE" => 0,
    		"FAULTY_SWITCH" => 0,
    		"TOTAL_LIGHTS" => 0,
    		"ON_LIGHTS" => 0,
    		"OFF_LIGHT" => 0,
    		"FAULTY_LIGHT" => "0",
    		"INSTALLED_LOAD" => 0,
    		"ACTIVE_LOAD" => 0,
    		"KWH" => 0,
    		"KVAH" => 0,
    		"SAVED_UNITS" => 0,
    		"SAVED_AMOUNT" => 0,
    		"SAVED_CO2" => 0,
    		"ON_UNITS" => 0,
    		"MANUAL_ON" => 0,
    		"OFF" => 0
    	);
    }
} else {
    // Handle if POST data is not set
	$return_response = array(
		"SWITCH_POINTS" => "--",
		"ACTIVE_SWITCH" => "--",
		"FAULTY_SWITCH" => "--",
		"TOTAL_LIGHTS" => "--",
		"ON_LIGHTS" => "--",
		"OFF_LIGHT" => "--",
		"FAULTY_LIGHT" => "--",
		"INSTALLED_LOAD" => "--",
		"ACTIVE_LOAD" => "--",
		"KWH" => "--",
		"KVAH" => "--",
		"SAVED_UNITS" => "--",
		"SAVED_AMOUNT" => "--",
		"SAVED_CO2" => "--",
		"POOR_NW" => 0,
		"TOTAL_UNITS" => 0,
		"UNISTALLED_UNITS" => 0,
		"POWER_FAILURE" => 0,
		"ON_UNITS" => 0,
		"MANUAL_ON" => 0,
		"OFF" => 0
	);
}

// Output JSON response
echo json_encode($return_response);
?>
