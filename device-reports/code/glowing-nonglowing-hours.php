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
$phase = "";
$id="";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = filter_input(INPUT_POST, 'TYPE', FILTER_SANITIZE_STRING); 
    $id   = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING); 

    include_once("../../common-files/fetch-device-phase.php");
    $phase = $device_phase;

    $collection = $devices_db_conn->lighthours_bar;

    switch ($type) {
        case 'LAST_WEEK':
            $start_date = date("Y-m-d", strtotime("-1 week"));
            $end_date   = date("Y-m-d");
            break;
        case 'CURRENT_WEEK':
            $start_date = date("Y-m-d", strtotime("last Sunday"));
            $end_date   = date("Y-m-d");
            break;
        case 'LAST_MONTH':
            $start_date = date("Y-m-01", strtotime("first day of last month"));
            $end_date   = date("Y-m-t", strtotime("last day of last month"));
            break;
        case 'PRESENT_MONTH':
            $start_date = date("Y-m-01");
            $end_date   = date("Y-m-d");
            break;
        case 'LATEST':
            $start_date = null;
            $end_date   = null;
            break;
        case 'CUSTOMRANGE':
            $start_date = $_POST['STARTDATE'];
            $end_date   = $_POST['ENDDATE'];
            break;
        default:
            echo json_encode([]);
            exit();
    }

    $data = [];

    if ($type === "LATEST") {
        // latest 10 docs by _id
        $cursor = $collection->find(
            ["device_id" => $id],
            [
                "sort"  => ["_id" => -1],
                "limit" => 10
            ]
        );

        $docs = iterator_to_array($cursor);
        // sort ascending again (like ORDER BY id ASC in subquery)
        $docs = array_reverse($docs);
    } else {
        $start = new MongoDB\BSON\UTCDateTime(strtotime($start_date . " 00:00:00") * 1000);
        $end   = new MongoDB\BSON\UTCDateTime(strtotime($end_date . " 23:59:59") * 1000);

        $cursor = $collection->find([
            "device_id" => $id,
            "date" => [
                '$gte' => $start,
                '$lte' => $end
            ]
        ], [
            "sort" => ["_id" => 1] // same as ORDER BY id ASC
        ]);

        $docs = iterator_to_array($cursor);
    }

    foreach ($docs as $doc) {
        $row = [
            "id"                      => (string)$doc["_id"], // map MongoDB _id to id
            "day"                     => $doc["date"]->toDateTime()->format("Y-m-d"),
            "glowing_hours_phaseR"    => convertMinutesToHours($doc["r_up"] ?? 0),
            "non_glowing_hours_phaseR"=> convertMinutesToHours($doc["r_down"] ?? 0),
            "glowing_hours_phaseY"    => convertMinutesToHours($doc["y_up"] ?? 0),
            "non_glowing_hours_phaseY"=> convertMinutesToHours($doc["y_down"] ?? 0),
            "glowing_hours_phaseB"    => convertMinutesToHours($doc["b_up"] ?? 0),
            "non_glowing_hours_phaseB"=> convertMinutesToHours($doc["b_down"] ?? 0),
            "TotalActiveHours"        => convertMinutesToHours($doc["total_active_time"] ?? 0),
            "TotalInActiveHours"      => convertMinutesToHours($doc["total_inactive_hours"] ?? 0),
        ];
        $data[] = $row;
    }

    echo json_encode([$data, $phase]);
}

function convertMinutesToHours($totalMinutes) {
    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;
    return sprintf("%02d.%02d", $hours, $minutes);
}
?>
