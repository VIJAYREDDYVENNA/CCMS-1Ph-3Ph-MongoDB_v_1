<?php
require_once '../base-path/config-path.php';
require_once BASE_PATH . 'config_db/config.php';
require_once BASE_PATH . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];
$user_name = $sessionVars['user_name'];
$user_email = $sessionVars['user_email'];
$permission_check = 0;
ini_set('max_execution_time', 0);

if (isset($_POST["date-range"]) && isset($_POST['device_id'])) {
    $Deviceid = trim($_POST['device_id']);
    $dateRange = $_POST['date-range'];

    if (empty($dateRange)) {
        echo "<script>alert('Please select a valid date range'); window.history.back();</script>";
        exit;
    }

    $id = $Deviceid;
    include_once("../common-files/fetch-device-phase.php");
    $phase = $device_phase;

    // Parse the date range
    list($from, $to) = explode(' to ', $dateRange);
    $from = new MongoDB\BSON\UTCDateTime(strtotime($from . " 00:00:01") * 1000);
    $to   = new MongoDB\BSON\UTCDateTime(strtotime($to   . " 23:59:59") * 1000);

    // Ensure date range does not exceed 30 days
    $dateDiff = (strtotime($to) - strtotime($from)) / (60 * 60 * 24);
    if ($dateDiff > 30) {
        die("Date range cannot exceed 30 days.");
    }

    // Get the device name
    $device_name = $Deviceid;
    $device_list = json_decode($_SESSION["DEVICES_LIST"]);
    foreach ($device_list as $key => $value) {
        if ($value->D_ID == strtoupper($Deviceid)) {
            $device_name = $value->D_NAME;
        }
    }

    $filename = $device_name;

    try {
        // MongoDB connection (already available in config.php)
        global $devices_db_conn;
        $collection = $devices_db_conn->live_data;

        // Projection (similar to SELECT columns)
        if ($phase == "1PH") {
            $projection = [
                'id' => 1, 'device_id' => 1, 'date_time' => 1,
                'voltage_ph1' => 1, 'current_ph1' => 1, 'kw_total' => 1, 'kva_total' => 1,
                'energy_kwh_total' => 1, 'energy_kvah_total' => 1,
                'frequency_ph1' => 1, 'powerfactor_ph1' => 1,
                'on_off_status' => 1, 'contactor_status' => 1,
                'location' => 1, 'signal_level' => 1
            ];
        } else {
            $projection = [
                'id' => 1, 'device_id' => 1, 'date_time' => 1,
                'voltage_ph1' => 1, 'voltage_ph2' => 1, 'voltage_ph3' => 1,
                'current_ph1' => 1, 'current_ph2' => 1, 'current_ph3' => 1,
                'kw_1' => 1, 'kw_2' => 1, 'kw_3' => 1, 'kw_total' => 1,
                'kva_1' => 1, 'kva_2' => 1, 'kva_3' => 1, 'kva_total' => 1,
                'energy_kwh_ph1' => 1, 'energy_kwh_ph2' => 1, 'energy_kwh_ph3' => 1, 'energy_kwh_total' => 1,
                'energy_kvah_ph1' => 1, 'energy_kvah_ph2' => 1, 'energy_kvah_ph3' => 1, 'energy_kvah_total' => 1,
                'lag_kvarh_ph1' => 1, 'lag_kvarh_ph2' => 1, 'lag_kvarh_ph3' => 1, 'lag_kvarh_total' => 1,
                'lead_kvarh_ph1' => 1, 'lead_kvarh_ph2' => 1, 'lead_kvarh_ph3' => 1, 'lead_kvarh_total' => 1,
                'frequency_ph1' => 1, 'frequency_ph2' => 1, 'frequency_ph3' => 1,
                'powerfactor_ph1' => 1, 'powerfactor_ph2' => 1, 'powerfactor_ph3' => 1,
                'on_off_status' => 1, 'contactor_status' => 1,
                'location' => 1, 'signal_level' => 1
            ];
        }

        // MongoDB Query
        $cursor = $collection->find(
            ['date_time' => ['$gte' => $from, '$lte' => $to]],
            ['projection' => $projection, 'sort' => ['id' => 1]]
        );

        // File Download Headers
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=$filename.xls");
        header("Pragma: no-cache");
        header("Expires: 0");

        // Column headers
        if ($phase == "1PH") {
            $col_headers = "S_NO\tDEVICE_ID\tDATE_TIME\tVOLTAGE\tCURRENT\tKW_TOTAL\tKVA_TOTAL\tENERGY_KWH_TOTAL\tENERGY_KVAH_TOTAL\tFREQUENCY_PH1\tPOWERFACTOR_PH1\tON_OFF_STATUS\tLOAD_STATUS\tLOCATION\tBATTERY VOLTAGE/SIGNAL_LEVEL";
        } else {
            $col_headers = "S_NO\tDEVICE_ID\tDATE_TIME\tVOLTAGE_PH1\tVOLTAGE_PH2\tVOLTAGE_PH3\tCURRENT_PH1\tCURRENT_PH2\tCURRENT_PH3\tKW_1\tKW_2\tKW_3\tKW_TOTAL\tKVA_1\tKVA_2\tKVA_3\tKVA_TOTAL\tENERGY_KWH_PH1\tENERGY_KWH_PH2\tENERGY_KWH_PH3\tENERGY_KWH_TOTAL\tENERGY_KVAH_PH1\tENERGY_KVAH_PH2\tENERGY_KVAH_PH3\tENERGY_KVAH_TOTAL\tLAG_KVARH_PH1\tLAG_KVARH_PH2\tLAG_KVARH_PH3\tLAG_KVARH_TOTAL\tLEAD_KVARH_PH1\tLEAD_KVARH_PH2\tLEAD_KVARH_PH3\tLEAD_KVARH_TOTAL\tFREQUENCY_PH1\tFREQUENCY_PH2\tFREQUENCY_PH3\tPOWERFACTOR_PH1\tPOWERFACTOR_PH2\tPOWERFACTOR_PH3\tON_OFF_STATUS\tLOAD_STATUS\tLOCATION\tBATTERY VOLTAGE/SIGNAL_LEVEL";
        }
        echo $col_headers . "\n";

        // Output rows
        $sno = 1;
        foreach ($cursor as $row) {
            $schema_insert = $sno++ . "\t";
            foreach ($projection as $field => $_) {
                if ($field === "id") continue; // skip internal id if needed
                $value = isset($row[$field]) ? $row[$field] : "NULL";
                if ($value instanceof MongoDB\BSON\UTCDateTime) {
                    $value = $value->toDateTime()->format("Y-m-d H:i:s");
                }
                $schema_insert .= $value . "\t";
            }
            $schema_insert = trim($schema_insert);
            echo preg_replace("/\r\n|\n\r|\n|\r/", " ", $schema_insert) . "\n";
        }
    } catch (Exception $e) {
        echo "An error occurred. Please try again.";
    }
} else {
    die("Invalid input. Please ensure all fields are filled out.");
}
