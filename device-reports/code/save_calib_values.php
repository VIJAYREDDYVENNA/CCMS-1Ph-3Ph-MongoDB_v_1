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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID']) && isset($_POST['DATA'])) {

    $device_ids = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);
    $data_input = filter_input(INPUT_POST, 'DATA', FILTER_SANITIZE_STRING);

    if ($role !== "SUPERADMIN") {
        echo json_encode(["message" => "This account is not allowed to make changes or modifications.."]);
        exit;
    }

    date_default_timezone_set('Asia/Kolkata');
    $date = new MongoDB\BSON\UTCDateTime((new DateTime("now", new DateTimeZone("Asia/Kolkata")))->getTimestamp()*1000);

    $frame_arr = explode(";", $data_input);

    $frame_data = $frame_arr[0] . ";" . $frame_arr[3] . ";" . $frame_arr[6] . ";" . $frame_arr[9] . ";" . $frame_arr[12] . ";" . $frame_arr[15] . ";" . $frame_arr[18] . ";" . $frame_arr[21] . ";";
    $frame_data .= $frame_arr[1] . ";" . $frame_arr[4] . ";" . $frame_arr[7] . ";" . $frame_arr[10] . ";" . $frame_arr[13] . ";" . $frame_arr[16] . ";" . $frame_arr[19] . ";" . $frame_arr[22] . ";";
    $frame_data .= $frame_arr[2] . ";" . $frame_arr[5] . ";" . $frame_arr[8] . ";" . $frame_arr[11] . ";" . $frame_arr[14] . ";" . $frame_arr[17] . ";" . $frame_arr[20] . ";" . $frame_arr[23] . ";";

    $calib_frame = $frame_data;
    $frame_data = strtolower($device_ids) . ";SET_VALUES;0;0;" . $frame_data;

    $savedSettingsCollection = $devices_db_conn->saved_settings_on_device;
    $calibrationCollection = $devices_db_conn->iot_calibration_values;
    $deviceSettingsCollection = $devices_db_conn->device_settings;

    try {
        // Insert into saved_settings_on_device
        $insertResult = $savedSettingsCollection->insertOne([
            'device_id' => $device_ids,
            'frame' => $frame_data
        ]);
        if (!$insertResult->isAcknowledged()) {
            throw new Exception("Error inserting loaded settings");
        }

        // Insert into iot_calibration_values
        $insertResult = $calibrationCollection->insertOne([
            'device_id' => $device_ids,
            'frame' => $calib_frame,
            'date_time' => $date,
            'user_mobile' => $mobile_no,
            'email' => $user_email,
            'name' => $user_name,
            'role' => $role
        ]);
        if (!$insertResult->isAcknowledged()) {
            throw new Exception("Error inserting calibration values");
        }

        // Update or Insert device_settings for CALIB_VALUES
        $updateResult = $deviceSettingsCollection->updateOne(
            ['device_id' => $device_ids, 'setting_type' => 'CALIB_VALUES'],
            ['$set' => ['setting_flag' => 1]],
            ['upsert' => true]
        );

        if (!$updateResult->isAcknowledged()) {
            throw new Exception("Error updating calibration settings");
        }

        // Update or Insert device_settings for READ_SETTINGS
        $updateResult = $deviceSettingsCollection->updateOne(
            
            ['device_id' => $device_ids,'setting_type' => 'READ_SETTINGS'],
            ['$set' => ['setting_flag' => 1]],
            ['upsert' => true]
        );

        if (!$updateResult->isAcknowledged()) {
            throw new Exception("Error updating read settings");
        }

        echo json_encode(["message" => "Updated Successfully"]);

    } catch (Exception $e) {
        echo json_encode(["message" => $e->getMessage()]);
    }
}
?>
