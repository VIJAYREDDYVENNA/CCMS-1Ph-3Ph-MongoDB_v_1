<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

// Check session and retrieve session variables
SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();
$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];

// Initialize variables
$return_response = "";
$user_devices = "";

$update_data = false;

$voltage_ph1 = "--";
$voltage_ph2 = "--";
$voltage_ph3 = "--";
$current_ph1 = "--";
$current_ph2 = "--";
$current_ph3 = "--";
$energy_kwh_total = "--";
$energy_kvah_total = "--";
$kw_total = "--";
$kva_total = "--";
$total_light = "--";
$on_light = "--";
$off_light = "--";
$on_off_status = "--";
$frame_date_time = "--";
$kw_1 = "--";
$kw_2 = "--";
$kw_3 = "--";
$phase = "3PH";

$total_lights = 0;
$on_lights = 0;
$off_lights = 0;

// Handle POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $device_id = filter_input(INPUT_POST, 'DEVICE_ID', FILTER_SANITIZE_STRING);

    try {
        // MongoDB collection connection
        $collection = $devices_db_conn->live_data_updates;

        $document = $collection->findOne(['device_id' => (string)$device_id]);

        if ($document !== null) {
            $update_data = true;

            // Convert BSON dates to PHP DateTime and format
            if (isset($document['date_time']) && $document['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                
                $frame_date_time = $document['date_time']->toDateTime()->modify('+5 hours 30 minutes')->format("Y-m-d H:i:s");
            }
            if (isset($document['ping_time']) && $document['ping_time'] instanceof MongoDB\BSON\UTCDateTime) {
                
                $ping_date_time = $document['date_time']->toDateTime()->modify('+5 hours 30 minutes')->format("Y-m-d H:i:s");
            }

            $voltage_ph1 = $document['voltage_ph1'] ?? "--";
            $voltage_ph2 = $document['voltage_ph2'] ?? "--";
            $voltage_ph3 = $document['voltage_ph3'] ?? "--";
            $current_ph1 = $document['current_ph1'] ?? "--";
            $current_ph2 = $document['current_ph2'] ?? "--";
            $current_ph3 = $document['current_ph3'] ?? "--";
            $energy_kwh_total = $document['energy_kwh_total'] ?? "--";
            $energy_kvah_total = $document['energy_kvah_total'] ?? "--";
            $kw_total = $document['kw_total'] ?? "--";
            $kva_total = $document['kva_total'] ?? "--";
            $lights_wattage = $document['lights_wattage'] ?? 0;
            $total_lights = $document['total_lights'] ?? 0;
            $on_off_status_raw = $document['on_off_status'] ?? 0;
            $kw_1 = $document['kw_1'] ?? "--";
            $kw_2 = $document['kw_2'] ?? "--";
            $kw_3 = $document['kw_3'] ?? "--";
            $phase = $document['phase'] ?? "3PH";

            // Handle on_off_status display
            switch ((string)$on_off_status_raw) {
                case "1":
                    $on_off_status = "<span class='text-success fw-semibold'>Auto ON</span>";
                    break;
                case "3":
                    $on_off_status = "<span class='text-success fw-semibold'>Server ON</span>";
                    break;
                case "4":
                    $on_off_status = "<span class='text-success fw-semibold'>WiFi ON</span>";
                    break;
                case "5":
                    $on_off_status = "<span class='text-info-emphasis fw-semibold'>Manual ON</span>";
                    break;
                case "6":
                    $on_off_status = "<span class='text-danger fw-semibold'>SERVER OFF</span>";
                    break;
                case "7":
                    $on_off_status = "<span class='text-danger fw-semibold'>WiFi OFF</span>";
                    break;
                case "0":
                default:
                    $on_off_status = "<span class='text-danger fw-semibold'>OFF</span>";
                    break;
            }

            // Calculate lights on/off percentage
            $kw_total_calc = is_numeric($kw_total) ? $kw_total * 1000 : 0;

            if ($lights_wattage > 0 && $kw_total_calc > 100 && $total_lights > 0) {
                $load = $kw_total_calc;
                $on_lights = ($load / $lights_wattage) * 100;
                $on_lights = min(100, round($on_lights, 2));
                $off_lights = round(100 - $on_lights, 2);
                if ($off_lights < 0) {
                    $off_lights = 0;
                }
            } else {
                if ($total_lights > 0) {
                    $off_lights = 100;
                    $on_lights = 0;
                } else {
                    $off_lights = $on_lights = 0;
                }
            }

            $return_response = array(
                "V_PH1" => $voltage_ph1,
                "V_PH2" => $voltage_ph2,
                "V_PH3" => $voltage_ph3,
                "I_PH1" => $current_ph1,
                "I_PH2" => $current_ph2,
                "I_PH3" => $current_ph3,
                "KWH" => $energy_kwh_total,
                "KVAH" => $energy_kvah_total,
                "KW" => $kw_total,
                "KVA" => $kva_total,
                "LIGHTS" => $total_lights,
                "LIGHTS_ON" => $on_lights,
                "LIGHTS_OFF" => $off_lights,
                "ON_OFF_STATUS" => $on_off_status,
                "DATE_TIME" => $frame_date_time,
                "KW_R" => $kw_1,
                "KW_Y" => $kw_2,
                "KW_B" => $kw_3,
                "PHASE" => $phase
            );
        } else {
            $return_response = "Device Not Found";
        }
    } catch (Exception $e) {
        error_log("Error fetching device data: " . $e->getMessage());
        $return_response = "Error fetching device data";
    }
} else {
    $return_response = "Invalid request method";
}

echo json_encode($return_response);
?>
