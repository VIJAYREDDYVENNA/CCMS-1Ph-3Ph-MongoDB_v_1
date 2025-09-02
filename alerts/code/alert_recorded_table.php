<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$normal = 'class=""';
$red = 'class="text-danger"';
$orange = 'class="text-warning"';
$green = 'class="text-success"';

$return_response = "";
$data = "";
$device_phase = "3PH";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID']) && isset($_POST['RECORDS'])) {
    $device_ids = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);
    $records = filter_input(INPUT_POST, 'RECORDS', FILTER_SANITIZE_STRING);
    $alert = filter_input(INPUT_POST, 'ALERT', FILTER_SANITIZE_STRING);

    $db = strtolower($device_ids);
    $data = "";

    try {
        $collection = $devices_db_conn->messges_frame;

        $filter = ['device_id' => $device_ids];  // Filter by device_id always

        if ($records === "DATE-RANGE" && isset($_POST['START_DATE']) && isset($_POST['END_DATE'])) {
            $start_date = trim(filter_input(INPUT_POST, 'START_DATE', FILTER_SANITIZE_STRING));
            $end_date = trim(filter_input(INPUT_POST, 'END_DATE', FILTER_SANITIZE_STRING));

            $start_obj = DateTime::createFromFormat('Y-m-d', $start_date);
            $end_obj = DateTime::createFromFormat('Y-m-d', $end_date);
            if (!$start_obj || !$end_obj) {
                $data = '<tr><td class="text-danger" colspan="5">Records not Found. Date format error</td></tr>';
                echo json_encode($data);
                exit();
            }

            

            $filter['date_time'] = [
               /* '$gte' => new MongoDB\BSON\UTCDateTime($start_obj->getTimestamp() * 1000),*/
                '$lte' => new MongoDB\BSON\UTCDateTime($end_obj->getTimestamp() * 1000)
            ];
        } elseif ($records === "LATEST") {
            // No additional date filters needed
        } else {
            $data = '<tr><td class="text-danger" colspan="5">Records are not Found</td></tr>';
            echo json_encode($data);
            exit();
        }

        $options = ['sort' => ['date_time' => -1]];
        if ($records === "LATEST") {
            $options['limit'] = 50;
        }

        $cursor = $collection->find($filter, $options);

        $data .= "<thead class='sticky-header text-center'>
        <tr class='header-row-1'>        
          <th class='table-header-row-1'>Type</th>                              
          <th class='table-header-row-1'>Message</th>                              
          <th class='table-header-row-1'>Sent Status</th>                              
          <th class='table-header-row-1'>Date & Time</th>
        </tr></thead><tbody>";

        $hasRecords = false;
        foreach ($cursor as $r) {
            $hasRecords = true;

            $alert_type = htmlspecialchars($r['alert_type'] ?? '');
            $frame = htmlspecialchars($r['frame'] ?? '');
            $sent_status = htmlspecialchars($r['sent_status'] ?? '');

            $date_time = '--';
            if (isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                $dt = $r['date_time']->toDateTime();
                $dt->modify('+5 hours 30 minutes');  // IST offset
                $date_time = $dt->format("Y-m-d H:i:s");
            }

            $data .= "<tr>
                <td>$alert_type</td>
                <td>$frame</td>
                <td>$sent_status</td>
                <td>$date_time</td>
            </tr>";
        }

        if (!$hasRecords) {
            $data .= '<tr><td class="text-danger" colspan="5">Records not Found</td></tr>';
        }
        $data .= "</tbody>";

    } catch (Exception $e) {
        $data = '<tr><td class="text-danger" colspan="5">Error: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
    }
} else {
    $data = '<tr><td class="text-danger" colspan="5">Invalid Request</td></tr>';
}

echo json_encode($data);
