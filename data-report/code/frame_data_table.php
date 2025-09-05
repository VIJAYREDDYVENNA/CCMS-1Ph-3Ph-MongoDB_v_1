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

$d_name = "";
$data = "";
$selection = "";
$phase = "3PH";


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID']) && isset($_POST['RECORDS'])) {
	$device_ids = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);
	$records = filter_input(INPUT_POST, 'RECORDS', FILTER_SANITIZE_STRING);
/*
	$device_ids = "SPIOT_206";
	$records = "LATEST";*/


	$db = strtolower($device_ids);
	$send = "";
	$id = $device_ids;
	$device_id = $id;
	$selected_phase1 ="";


	include_once("../../common-files/fetch-device-phase.php");
	$phase = $device_phase;
	$selected_phase1 =$phase;

	try {
		$collection = $devices_db_conn->live_data;
		
		date_default_timezone_set('Asia/Kolkata');
		$filter = ["device_id" => $device_id];
		$options = [
			'sort' => ['date_time' => -1],
			'limit' => 20
		];

		if ($records === "LATEST") {

			$date= date("Y-m-d");
			$startIST = DateTime::createFromFormat('Y-m-d H:i:s', $date.' 00:00:00', new DateTimeZone('Asia/Kolkata'));
			$endIST   = DateTime::createFromFormat('Y-m-d H:i:s', $date.' 23:59:59', new DateTimeZone('Asia/Kolkata'));
			/*$startUTC = clone $startIST; $startUTC->setTimezone(new DateTimeZone('UTC'));*/
			$endUTC   = clone $endIST;   $endUTC->setTimezone(new DateTimeZone('UTC'));


			$filter = [

				'device_id' => $device_id, 
				'date_time' => [
					//'$gte' => new MongoDB\BSON\UTCDateTime($startUTC->getTimestamp() * 1000),
					'$lt' => new MongoDB\BSON\UTCDateTime($endUTC->getTimestamp() * 1000)
				]
			];
			$options = [
				'sort' => ['date_time' => -1], 
				'limit' => 20
			];
			
		} elseif ($records === "ADD") {
			if (isset($_POST['DATE_TIME'])) {
				$date = trim(filter_input(INPUT_POST, 'DATE_TIME', FILTER_SANITIZE_STRING));

				//$date="2025-08-25 11:12:12";
				$date_converted = DateTime::createFromFormat('Y-m-d H:i:s', $date, new DateTimeZone('Asia/Kolkata'));

				if ($date_converted !== false) {

					/*$startIST = DateTime::createFromFormat('Y-m-d H:i:s', $dateStr.' 00:00:00', new DateTimeZone('Asia/Kolkata'));*/
					$endIST   = DateTime::createFromFormat('Y-m-d H:i:s', $date, new DateTimeZone('Asia/Kolkata'));
				//$startUTC = clone $startIST; $startUTC->setTimezone(new DateTimeZone('UTC'));
					$endUTC   = clone $endIST;   $endUTC->setTimezone(new DateTimeZone('UTC'));


					$filter = [

						'device_id' => $device_id, 
						'date_time' => [
							/*'$gte' => new MongoDB\BSON\UTCDateTime($startUTC->getTimestamp() * 1000),*/
							'$lt' => new MongoDB\BSON\UTCDateTime($endUTC->getTimestamp() * 1000)
						]
					];
					$options = [
						'sort' => ['date_time' => -1], 
						'limit' => 200
					];

					

					
				} else {
					$data = '<tr><td class="text-danger" colspan="75">Records not found. Date-Time format error</td></tr>';
					echo json_encode($data);
					exit();
				}
			} else {
				$data = '<tr><td class="text-danger" colspan="75">Records not found. Missing DATE_TIME parameter</td></tr>';
				echo json_encode($data);
				exit();
			}
		} elseif ($records === "DATE") {
			if (isset($_POST['DATE'])) {
				$dateStr = $_POST['DATE'];    			
				$startIST = DateTime::createFromFormat('Y-m-d H:i:s', $dateStr.' 00:00:00', new DateTimeZone('Asia/Kolkata'));
				$endIST   = DateTime::createFromFormat('Y-m-d H:i:s', $dateStr.' 23:59:59', new DateTimeZone('Asia/Kolkata'));
				$startUTC = clone $startIST; $startUTC->setTimezone(new DateTimeZone('UTC'));
				$endUTC   = clone $endIST;   $endUTC->setTimezone(new DateTimeZone('UTC'));


				$filter = [

					'device_id' => $device_id, 
					'date_time' => [
						'$gte' => new MongoDB\BSON\UTCDateTime($startUTC->getTimestamp() * 1000),
						'$lte' => new MongoDB\BSON\UTCDateTime($endUTC->getTimestamp() * 1000)
					]
				];
				$options = [
					'sort' => ['date_time' => -1], 
					'limit' => 200
				];

			} else {
				$data = '<tr><td class="text-danger" colspan="75">Records not found. Missing DATE parameter</td></tr>';
				echo json_encode($data);
				exit();
			}
		}
		include("set_parameters.php");

		$cursor ;
		/*if ($records === "LATEST") {
			$cursor = $devices_db_conn->live_data_updates_20_new->find($filter, $options);
		}
		else
		{*/
			$cursor = $collection->find($filter, $options);
		//}



			$docs = iterator_to_array($cursor);



			if (empty($docs)) {
				$data = '<tr><td class="text-danger" colspan="75">Records not found</td></tr>';
			} 
			else 
			{
				foreach ($docs as $r) {
                // Use uppercase db name for device id display
					$device_id = strtoupper($db);

					include("table_cells.php");
				}
			}
		} catch (Exception $e) {
			$data = '<tr><td class="text-danger" colspan="75">Error: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
		}
	} else {
		$data = '<tr><td class="text-danger" colspan="75">Invalid request parameters</td></tr>';
	}

// Return JSON encoded data and phase
	echo json_encode(array($data, $phase));
	?>
