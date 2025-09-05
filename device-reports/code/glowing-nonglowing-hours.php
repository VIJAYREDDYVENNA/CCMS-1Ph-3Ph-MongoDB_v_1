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
$phase="";

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
    $type = filter_input(INPUT_POST, 'TYPE', FILTER_SANITIZE_STRING); 
    $id = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING); 

    include_once("../../common-files/fetch-device-phase.php");
    $phase= $device_phase;

    // MongoDB connection (assuming $devices_db_conn is your MongoDB connection)
    $collection = $devices_db_conn->lighthours_bar;

    $type = sanitize_input($type);
    $id = sanitize_input($id);

    switch ($type) {
        case 'LAST_WEEK':
        $start_date = date("Y-m-d", strtotime("-1 week"));
        $end_date = date("Y-m-d");
        break;
        case 'CURRENT_WEEK':
        $start_date = date("Y-m-d", strtotime("last Sunday"));
        $end_date = date("Y-m-d");
        break;
        case 'LAST_MONTH':
        $start_date = date("Y-m-01", strtotime("first day of last month"));
        $end_date = date("Y-m-t", strtotime("last day of last month"));
        break;
        case 'PRESENT_MONTH':
        $start_date = date("Y-m-01");
        $end_date = date("Y-m-d");
        break;

        case 'LATEST':
        $start_date = date("Y-m-01");
        $end_date = date("Y-m-d");
        break;

        case 'CUSTOMRANGE':
        $start_date = $_POST['STARTDATE'];
        $end_date = $_POST['ENDDATE'];
       
        $start_date = sanitize_input($start_date);
        $end_date = sanitize_input($end_date);
        break;
        default:
        echo json_encode([]);
        exit();
    }

    $data = [];
    
    if($type === "LATEST") {
        // MongoDB query for LATEST (equivalent to LIMIT 10 with ORDER BY id DESC then ASC)
        $cursor = $collection->find(
            ['device_id' => $id],
            [
                'sort' => ['_id' => -1],
                'limit' => 10,
                'projection' => [
                    'date' => 1,
                    'r_up' => 1,
                    'r_down' => 1,
                    'y_up' => 1,
                    'y_down' => 1,
                    'b_up' => 1,
                    'b_down' => 1,
                    'total_active_time' => 1,
                    'total_inactive_hours' => 1
                ]
            ]
        );
        
        // Convert cursor to array and reverse to get ASC order
        $results = iterator_to_array($cursor);
        $results = array_reverse($results);
        
    } else {
        // MongoDB query for date range
        $cursor = $collection->find(
            [
                'device_id' => $id,
                'date' => [
                    '$gte' => $start_date,
                    '$lte' => $end_date
                ]
            ],
            [
                'projection' => [
                    'date' => 1,
                    'r_up' => 1,
                    'r_down' => 1,
                    'y_up' => 1,
                    'y_down' => 1,
                    'b_up' => 1,
                    'b_down' => 1,
                    'total_active_time' => 1,
                    'total_inactive_hours' => 1
                ]
            ]
        );
        
        $results = iterator_to_array($cursor);
    }

    // Process the results
    foreach ($results as $row) {
        // Convert MongoDB date to Y-m-d format
        $dateString = '';
        if (isset($row['date'])) {
            if ($row['date'] instanceof MongoDB\BSON\UTCDateTime) {
                // Convert MongoDB UTCDateTime to PHP DateTime and format as Y-m-d
                $dateString = $row['date']->toDateTime()->format('Y-m-d');
            } elseif (is_string($row['date'])) {
                // If it's already a string, try to format it
                $dateString = date('Y-m-d', strtotime($row['date']));
            } else {
                $dateString = $row['date']; // fallback
            }
        }
        
        $processedRow = [
            'day' => $dateString,
            'glowing_hours_phaseR' => convertMinutesToHours($row['r_up'] ?? 0),
            'non_glowing_hours_phaseR' => convertMinutesToHours($row['r_down'] ?? 0),
            'glowing_hours_phaseY' => convertMinutesToHours($row['y_up'] ?? 0),
            'non_glowing_hours_phaseY' => convertMinutesToHours($row['y_down'] ?? 0),
            'glowing_hours_phaseB' => convertMinutesToHours($row['b_up'] ?? 0),
            'non_glowing_hours_phaseB' => convertMinutesToHours($row['b_down'] ?? 0),
            'TotalActiveHours' => convertMinutesToHours($row['total_active_time'] ?? 0),
            'TotalInActiveHours' => convertMinutesToHours($row['total_inactive_hours'] ?? 0)
        ];
        
        $data[] = $processedRow;
    }
    
    echo json_encode(array($data, $phase));
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function convertMinutesToHours($totalMinutes) {
    $hours = floor($totalMinutes / 60); // Get the total hours
    $minutes = $totalMinutes % 60; // Get the remaining minutes
    return sprintf("%02d.%02d", $hours, $minutes); // Format as HH:MM
}
?>