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

// Function to fetch data for bar and pie charts
function fetchData($devices_db_conn, $startDate, $endDate, $device_id) {
    $collectionBar = $devices_db_conn->devicehours_bar;
    $collectionPie = $devices_db_conn->devicehours_pie;

    $start = new MongoDB\BSON\UTCDateTime(strtotime($startDate . " 00:00:00") * 1000);
    $end   = new MongoDB\BSON\UTCDateTime(strtotime($endDate . " 23:59:59") * 1000);

    // Fetch data for the bar chart
    $cursorBar = $collectionBar->find([
        "device_id" => $device_id,
        "date" => [
            '$gte' => $start,
            '$lte' => $end
        ]
    ], [
        "sort" => ["date" => 1]
    ]);

    $dates = [];
    $uptimeHours = [];

    foreach ($cursorBar as $doc) {
        $dates[] = $doc["date"]->toDateTime()->format("Y-m-d");
        $uptimeHours[] = convertMinutesToHours($doc["uptime_hours"] ?? 0);
    }

    // Fetch data for the pie chart
    $cursorPie = $collectionPie->find([
        "device_id" => $device_id,
        "date" => [
            '$gte' => $start,
            '$lte' => $end
        ]
    ], [
        "sort" => ["date" => 1]
    ]);

    $pieData = [];
    foreach ($cursorPie as $doc) {
        $dateStr = $doc["date"]->toDateTime()->format("Y-m-d");
        $pieData[$dateStr] = [
            'power_failure'  => convertMinutesToHours($doc["power_failure"] ?? 0),
            'device_failure' => convertMinutesToHours($doc["device_failure"] ?? 0)
        ];
    }

    return [
        'dates' => $dates,
        'uptimeHours' => $uptimeHours,
        'pieData' => $pieData
    ];
}

// Handle AJAX request for specific date range or single date
if (isset($_GET['startDate']) && isset($_GET['endDate']) && isset($_GET['D_ID'])) {
    $startDate = filter_input(INPUT_GET, 'startDate', FILTER_SANITIZE_STRING);
    $endDate   = filter_input(INPUT_GET, 'endDate', FILTER_SANITIZE_STRING);
    $device_id = filter_input(INPUT_GET, 'D_ID', FILTER_SANITIZE_STRING);

    $data = fetchData($devices_db_conn, $startDate, $endDate, $device_id);

    echo json_encode($data);
    exit;
}

if (isset($_GET['date']) && isset($_GET['D_ID'])) {
    $date      = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING);
    $device_id = filter_input(INPUT_GET, 'D_ID', FILTER_SANITIZE_STRING);

    $data = fetchData($devices_db_conn, $date, $date, $device_id);

    echo json_encode($data);
    exit;
}

function convertMinutesToHours($totalMinutes) {
    $hours = floor($totalMinutes / 60); // Get the total hours
    $minutes = $totalMinutes % 60; // Get the remaining minutes
    return sprintf("%02d.%02d", $hours, $minutes); // Format as HH:MM
}
?>
