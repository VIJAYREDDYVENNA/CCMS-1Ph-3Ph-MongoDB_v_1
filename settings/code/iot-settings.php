<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$mobile_no    = $sessionVars['mobile_no'];
$user_id      = $sessionVars['user_id'];
$role         = $sessionVars['role'];
$user_login_id= $sessionVars['user_login_id'];
$user_name    = $sessionVars['user_name'];
$user_email   = $sessionVars['user_email'];

$response = ["status" => "error", "message" => ""];



// Permission check for iot_settings
$permissionDoc = $user_db_conn->user_permissions->findOne(
    ["login_id" => (int)$user_login_id],
    ["projection" => ["iot_settings" => 1]]
);

if (!$permissionDoc || (int)$permissionDoc["iot_settings"] !== 1) {
    $response["message"] = "No Permission to change the Current settings of the device(s)";
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $device_ids = $parameter_value = $parameter = "";

    if (isset($_POST['D_ID']) && isset($_POST['PARAMETER_VALUE']) && isset($_POST['UPDATED_STATUS'])) {
        $device_ids      = sanitize_input($_POST['D_ID']);
        $parameter_value = sanitize_input($_POST['PARAMETER_VALUE']);
        $parameter       = sanitize_input($_POST['UPDATED_STATUS']);
    } elseif (isset($_POST['D_ID']) && isset($_POST['UPDATED_STATUS']) && $_POST['UPDATED_STATUS'] === "ANGLE_CHANGE") {
        $device_ids = sanitize_input($_POST['D_ID']);
        $parameter  = sanitize_input($_POST['UPDATED_STATUS']);
    } else {
        $response["message"] = "Required parameters missing.";
        echo json_encode($response);
        exit();
    }

    $device_ids_array = explode(",", $device_ids);

    foreach ($device_ids_array as $device_id_raw) {
        $device_id = trim(strtolower($device_id_raw));
        if (!preg_match('/^[a-z0-9_]+$/', $device_id)) {
            $response["message"] = "Invalid device ID";
            echo json_encode($response);
            exit();
        }
        $device_id_upper = strtoupper($device_id);

        $dtIST = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
        $date_time = new MongoDB\BSON\UTCDateTime($dtIST->getTimestamp() * 1000);

        $user_activity = "";
        $update_parameter = "";

        switch (trim($parameter)) {
            case 'CHANGE_DEVICE_ID':
            $user_activity = "New Device-Id updated";

            $devices_db_conn->iot_device_id_change->insertOne([
                "device_id"   => $device_id_upper,
                "new_device_id" => strtoupper(trim($parameter_value)),
                "date_time"   => $date_time,
                "user_mobile" => $mobile_no,
                "email"       => $user_email,
                "name"        => $user_name,
                "role"        => $role,
                "status" => "Initiated"
            ]);

            $devices_db_conn->device_settings->updateOne(
                ["device_id" => $device_id_upper, "setting_type" => "ID_CHANGE"],
                ['$set' => ["setting_flag" => 1]],
                ["upsert" => true]
            );
            break;

            case 'CHANGE_SERIAL_ID':
            $user_activity = "New Serial-Id updated";

            $devices_db_conn->iot_serial_id_change->insertOne([
                "device_id"   => $device_id_upper,
                "serial_id"   => strtoupper(trim($parameter_value)),
                "date_time"   => $date_time,
                "user_mobile" => $mobile_no,
                "email"       => $user_email,
                "name"        => $user_name,
                "role"        => $role,
                "status" => "Initiated"
                
            ]);

            $devices_db_conn->device_settings->updateOne(
                ["device_id" => $device_id_upper, "setting_type" => "SERIAL_ID"],
                ['$set' => ["setting_flag" => 1]],
                ["upsert" => true]
            );
            break;

            case 'HYSTERESIS':
            $user_activity = "Hysteresis setting updated";

            $devices_db_conn->iot_hysteresis->insertOne([
                "device_id"   => $device_id_upper,
                "value"       => strtoupper(trim($parameter_value)),
                "date_time"   => $date_time,
                "user_mobile" => $mobile_no,
                "email"       => $user_email,
                "name"        => $user_name,
                "role"        => $role,
                "status" => "Initiated"
            ]);

            $devices_db_conn->device_settings->updateOne(
                ["device_id" => $device_id_upper, "setting_type" => "HYSTERESIS"],
                ['$set' => ["setting_flag" => 1]],
                ["upsert" => true]
            );
            break;

            case 'ON_OFF_INTERVAL':
            $user_activity = "On-Off interval updated";

            $devices_db_conn->iot_on_off_interval->insertOne([
                "device_id"   => $device_id_upper,
                "value"       => strtoupper(trim($parameter_value)),
                "date_time"   => $date_time,
                "user_mobile" => $mobile_no,
                "email"       => $user_email,
                "name"        => $user_name,
                "role"        => $role,
                "status" => "Initiated"
            ]);

            $devices_db_conn->device_settings->updateOne(
                ["device_id" => $device_id_upper, "setting_type" => "LOOP_ON_OFF"],
                ['$set' => ["setting_flag" => 1]],
                ["upsert" => true]
            );
            break;

            case 'RESET_ENERGY':
            $user_activity = "Energy reset values updated";

            $parameter_values = explode(',', strtoupper(trim($parameter_value)));
            if (count($parameter_values) < 2) {
                $response["message"] = "Invalid RESET_ENERGY parameter values";
                echo json_encode($response);
                exit();
            }

            $devices_db_conn->iot_reset_energy->insertOne([
                "device_id"   => $device_id_upper,
                "kwh"        => (float)$parameter_values[0],
                "kvah"       => (float)$parameter_values[1],
                "date_time"  => $date_time,
                "user_mobile"=> $mobile_no,
                "email"      => $user_email,
                "name"       => $user_name,
                "role"       => $role,
                "status" => "Initiated"
            ]);

            $devices_db_conn->device_settings->updateOne(
                ["device_id" => $device_id_upper, "setting_type" => "ENERGY_RESET"],
                ['$set' => ["setting_flag" => 1]],
                ["upsert" => true]
            );
            break;

            case 'WIFI_CREDENTIALS':
            $user_activity = "WiFi credentials updated";

            $parameter_values = explode(',', $parameter_value);
            if (count($parameter_values) < 2) {
                $response["message"] = "Invalid WIFI_CREDENTIALS parameter values";
                echo json_encode($response);
                exit();
            }

            $devices_db_conn->iot_wifi_credentials->insertOne([
                "device_id"   => $device_id_upper,
                "ssid"       => $parameter_values[0],
                "password"   => $parameter_values[1],
                "date_time"  => $date_time,
                "user_mobile"=> $mobile_no,
                "email"      => $user_email,
                "name"       => $user_name,
                "role"       => $role,
                "status" => "Initiated"
            ]);

            $devices_db_conn->device_settings->updateOne(
                ["device_id" => $device_id_upper, "setting_type" => "WIFI_CREDENTIALS"],
                ['$set' => ["setting_flag" => 1]],
                ["upsert" => true]
            );
            break;

            case 'READ_SETTINGS':
            $user_activity = "Read saved settings from IoT initiated";

            $devices_db_conn->device_settings->updateOne(
                ["device_id" => $device_id_upper, "setting_type" => "READ_SETTINGS"],
                ['$set' => ["setting_flag" => 1]],
                ["upsert" => true]
            );
            break;

            case 'RESET_DEVICE':
            $user_activity = "IoT Device reset initiated";

            $devices_db_conn->iot_device_reset->insertOne([
                "device_id"   => $device_id_upper,
                "reset"      => strtoupper(trim($parameter_value)),
                "date_time"  => $date_time,
                "user_mobile"=> $mobile_no,
                "email"      => $user_email,
                "name"       => $user_name,
                "role"       => $role,
                "status" => "Initiated"
            ]);

            $devices_db_conn->device_settings->updateOne(
                ["device_id" => $device_id_upper, "setting_type" => "RESET"],
                ['$set' => ["setting_flag" => 1]],
                ["upsert" => true]
            );
            break;

            case 'ADDRESS':
            $user_activity = "Address updated";

                // Expect these POST parameters for address
            $street   = sanitize_input($_POST['STREET']);
            $town     = sanitize_input($_POST['AREA']);
            $city     = sanitize_input($_POST['CITY']);
            $district = sanitize_input($_POST['DISTRICT']);
            $state    = sanitize_input($_POST['STATE']);
            $pincode  = sanitize_input($_POST['PINCODE']);
            $landmark = sanitize_input($_POST['LANDMARK']);
            $country  = sanitize_input($_POST['PARAMETER']);

            $devices_db_conn->device_address->insertOne([
                "device_id" => $device_id_upper,
                "street"   => $street,
                "town"     => $town,
                "city"     => $city,
                "district" => $district,
                "state"    => $state,
                "pincode"  => $pincode,
                "country"  => $country,
                "landmark" => $landmark,
                "date_time"=> $date_time,
                "user_mobile" => $mobile_no,
                "email"    => $user_email,
                "name"     => $user_name,
                "role"     => $role
            ]);
            break;

            default:
            $response["message"] = "Something went wrong..";
            echo json_encode($response);
            exit();
        }

        // Insert user activity log entry (time series preferred if you like)
        $devices_db_conn->user_activity_log->insertOne([
            "date_time"     => $date_time,
            "updated_field" => $user_activity,            
            "device_id"   => $device_id_upper,
            "user_mobile" => $mobile_no,
            "email"       => $user_email,
            "name"        => $user_name,
            "role"        => $role
            
        ]);
    }

    $response["status"] = "success";
    $response["message"] = $user_activity . " successfully";
    echo json_encode($response);
    exit();

} else {
    $response["message"] = "Invalid request method.";
    echo json_encode($response);
    exit();
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>
