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

$return_response = "";
$add_confirm = false;
$code = "";
$phase = "3PH";

if ($_SERVER["REQUEST_METHOD"] == "POST") {


	$device_id = htmlspecialchars($_POST['D_ID']);
	$device_name = htmlspecialchars($_POST['D_NAME']);
	$activation_code = htmlspecialchars($_POST['ACTIVATION_CODE']);

    // Check user permissions
	$permissionDoc = $user_db_conn->user_permissions->findOne(
		['login_id' => (int)$user_login_id],
		['projection' => ['device_add_remove' => 1]]
	);

	$device_add_remove = (int)$permissionDoc['device_add_remove'] ?? 0;

	if ($device_add_remove != 1) {
		echo json_encode("No permission to add device");
		exit();
	}

	if ($role !== "SUPERADMIN") {
		if (empty($device_id) || empty($device_name) || empty($activation_code)) {
			echo json_encode("All fields are required");
			exit();
		}
	} else {
		if (empty($device_id)) {
			echo json_encode("Please enter device ID");
			exit();
		}
		if (empty($device_name)) {
			$device_name = $device_id;
		}
	}

    // Check if device ID already exists
	$existingDevice = $user_db_conn->user_device_list->findOne(
		['device_id' => $device_id, 'login_id' => (int)$user_login_id]
	);

	if ($existingDevice !== null) {
		$return_response = "Device ID already exists.";
	} else {
        // Check activation code and phase from activation_codes collection
		$activationDoc = $user_db_conn->activation_codes->findOne(
			['device_id' => $device_id],
			['projection' => ['code' => 1, 'phase' => 1]]
		);

		if ($activationDoc !== null) {
			$code = $activationDoc['code'];
			$phase = $activationDoc['phase'] ?? $phase;

			if ($role !== "SUPERADMIN") {
				if ($activation_code === $code) {
					$add_confirm = true;
				} else {
					$return_response = "Incorrect activation code";
				}
			} else {
				$add_confirm = true;
			}
		} else {
			$return_response = "Device ID / Activation code is not Available in the list";
		}

		if ($add_confirm) {
            // If not SUPERADMIN, try to get user_alternative_name from device database collection
			if ($role != "SUPERADMIN") {
				try {
					$device_db = strtolower(trim($device_id));
					$deviceDbConn = $client->selectDatabase($device_db);
					$nameDoc = $deviceDbConn->device_name_update_log->findOne(
						[],
						['sort' => ['id' => -1], 'projection' => ['user_alternative_name' => 1]]
					);
					if ($nameDoc !== null && !empty($nameDoc['user_alternative_name'])) {
						$device_name = $nameDoc['user_alternative_name'];
					}
				} catch (Exception $e) {
                    // Ignore and proceed with existing $device_name
				}
			}

            // Insert into user_device_list collection
			$insertResult = $user_db_conn->user_device_list->updateOne(
				[
					'device_id' => $device_id,
					'login_id'  => (int)$user_login_id
				], 
				[
					'$set' => [
						'c_device_name' => $device_name,
						's_device_name' => $device_name,
						'role'          => $role,
						'phase'         => $phase
					]
				],
				[
					'upsert' => true
				]
			);

			if ($insertResult->isAcknowledged()) {
				try {
                    // Upsert phase in live_data_updates collection in central_db
					$devices_db_conn->live_data_updates->updateOne(
						['device_id' => $device_id],
						['$set' => ['phase' => $phase]],
						['upsert' => true]
					);
				} catch (Exception $e) {
                    // Ignore exceptions here
				}

				$return_response = "New device added successfully.";
			} else {
				$return_response = "Error: Failed to add new device";
			}
		}
	}
} else {
	$return_response = "Data not Available";
}

echo json_encode($return_response);
?>
