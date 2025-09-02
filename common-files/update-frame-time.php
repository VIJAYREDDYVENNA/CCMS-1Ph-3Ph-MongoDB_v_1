<?php
require_once '../base-path/config-path.php';
require_once BASE_PATH . 'config_db/config.php';
require_once BASE_PATH . 'session/session-manager.php';

SessionManager::checkSession();
$return_response = ["DATE_TIME" => "--", "PING_TIME" => "--"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $device_id = filter_input(INPUT_POST, 'DEVICE_ID', FILTER_SANITIZE_STRING);

    try {
        global $devices_db_conn; // MongoDB connection to ccms_data
        $collection = $devices_db_conn->live_data_updates;

        $document = $collection->findOne(['device_id' => $device_id]);

        if ($document !== null) {
            // Convert BSON dates to PHP DateTime and add +5:30 IST offset
            $frame_date_time = "--";
            $ping_date_time = "--";

            if (isset($document['date_time'])) {
                if ($document['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                    $dt = $document['date_time']->toDateTime();
                } else {
                    $dt = new DateTime($document['date_time']);
                }
                $dt->modify('+5 hours 30 minutes');
                $frame_date_time = $dt->format("H:i:s d-m-Y");
            }

            if (isset($document['ping_time'])) {
                if ($document['ping_time'] instanceof MongoDB\BSON\UTCDateTime) {
                    $dt2 = $document['ping_time']->toDateTime();
                } else {
                    $dt2 = new DateTime($document['ping_time']);
                }
                $dt2->modify('+5 hours 30 minutes');
                $ping_date_time = $dt2->format("H:i:s d-m-Y");
            }


            $return_response = [
                "DATE_TIME" => $frame_date_time,
                "PING_TIME" => $ping_date_time
            ];
        }
    } catch (Exception $e) {
        $return_response = ["DATE_TIME" => "--", "PING_TIME" => "--"];
    }
}

echo json_encode($return_response);
