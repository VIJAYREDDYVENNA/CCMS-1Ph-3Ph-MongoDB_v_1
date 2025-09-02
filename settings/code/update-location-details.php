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

$response = ["status" => "error", "message" => ""];

// Assume $devices_db_conn is already initialized MongoDB database connection e.g. $manager->ccms_data

// Permission check
$permissionDoc = $user_db_conn->user_permissions->findOne(
    ["login_id" => (int)$user_login_id],
    ["projection" => ["device_info_update" => 1]]
);

if (!$permissionDoc || (int)$permissionDoc["device_info_update"] !== 1) {
    $response["message"] = "No Permission to change the Current settings of the device(s)";
    echo json_encode($response);
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $device_ids = trim($_POST['D_ID']);
    $parameter_value = isset($_POST['PARAMETER']) ? trim($_POST['PARAMETER']) : "";
    $parameter = isset($_POST['UPDATED_STATUS']) ? trim($_POST['UPDATED_STATUS']) : "";

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

        switch ($parameter) {
            case 'COORDINATES':
            $user_activity = "Coordinates updated";

            $latLongPattern = '/^-?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*-?((1[0-7]\d(\.\d+)?|180(\.0+)?)|((\d|[1-9]\d)(\.\d+)?))$/';
            if (!preg_match($latLongPattern, $parameter_value)) {
                $response["message"] = "Invalid coordinates. Please enter valid latitude and longitude in format 'latitude,longitude'.";
                echo json_encode($response);
                exit();
            }
            $lat_long = array_map('trim', explode(',', $parameter_value));

                // Convert latitude and longitude to ddm format
            function convertToDDM($coord) {
                $dec = (int)$coord;
                $deg = (int)(($coord - $dec) * 60);
                $min = ($coord - $dec - $deg/60) * 60;
                return round($dec * 100 + $deg + $min, 5);
            }
            $ddm_lat = convertToDDM($lat_long[0]);
            $ddm_long = convertToDDM($lat_long[1]);
            $ddm_lat_long = $ddm_lat . ',' . $ddm_long;

            $devices_db_conn->coordinates_list->insertOne([
                "device_id"         => $device_id_upper,
                "latitude"          => (float)$lat_long[0],
                "longitude"         => (float)$lat_long[1],
                "lat_long_ddm_format"=> $ddm_lat_long,
                "update_status"         => 0,
                "date_time"         => $date_time,
                "user_mobile"       => $mobile_no,
                "email"             => $user_email,
                "name"              => $user_name,
                "role"              => $role
            ]);
            break;

            case 'COORDINATES_CHANGE':
            $user_activity = $parameter_value ? 
            "Device GPS location update enabled updated" : 
            "Device GPS location update disabled updated";

                // Find latest record for device
            $latestDoc = $devices_db_conn->coordinates_list->findOne(
                ["device_id" => $device_id_upper],
                ["sort" => ["date_time" => -1], "projection" => ["_id" => 1]]
            );

            if ($latestDoc && isset($latestDoc->_id)) {
                $updateResult = $devices_db_conn->coordinates_list->updateOne(
                    ["_id" => $latestDoc->_id],
                    ['$set' => ["update_status" => (int)$parameter_value]]
                );
                if ($updateResult->isAcknowledged()) {
                    $response["status"] = "success";
                    $response["message"] = "Updated successfully";
                } else {
                    $response["message"] = "Error updating record";
                    echo json_encode($response);
                    exit();
                }
            } else {
                $response["message"] = "Error: Provide the coordinate to enable it.";
                echo json_encode($response);
                exit();
            }
            break;

            case 'ADDRESS':
            $user_activity = "Address updated";

            $street   = isset($_POST['STREET']) ? trim($_POST['STREET']) : "--";
            $town     = isset($_POST['AREA']) ? trim($_POST['AREA']) : "--";
            $city     = isset($_POST['CITY']) ? trim($_POST['CITY']) : "--";
            $district = isset($_POST['DISTRICT']) ? trim($_POST['DISTRICT']) : "--";
            $state    = isset($_POST['STATE']) ? trim($_POST['STATE']) : "--";
            $pincode  = isset($_POST['PINCODE']) ? trim($_POST['PINCODE']) : "--";
            $country  = isset($_POST['PARAMETER']) ? trim($_POST['PARAMETER']) : "--";
            $landmark = isset($_POST['LANDMARK']) ? trim($_POST['LANDMARK']) : "--";

            $devices_db_conn->device_address->insertOne([
                "device_id"   => $device_id_upper,
                "street"     => $street,
                "town"       => $town,
                "city"       => $city,
                "district"   => $district,
                "state"      => $state,
                "pincode"    => $pincode,
                "country"    => $country,
                "landmark"   => $landmark,
                "date_time"  => $date_time,
                "user_mobile"=> $mobile_no,
                "email"      => $user_email,
                "name"       => $user_name,
                "role"       => $role
            ]);
            break;

            default:
            $response["message"] = "Something went wrong..";
            echo json_encode($response);
            exit();
        }

        // Insert user activity log (time series style metadata possible)
        $devices_db_conn->user_activity_log->insertOne([
            "date_time"     => $date_time,
            "updated_field" => $user_activity,
            "device_meta"   => [
                "device_id"   => $device_id_upper,
                "user_mobile" => $mobile_no,
                "email"       => $user_email,
                "name"        => $user_name,
                "role"        => $role
            ]
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
?>
