<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';
SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$mobile_no     = $sessionVars['mobile_no'];
$user_id       = $sessionVars['user_id'];
$role          = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];
$user_name     = $sessionVars['user_name'];
$user_email    = $sessionVars['user_email'];

$data = "<thead class='sticky-header text-center'>
<tr class='header-row-1'>                                    
<th class='table-header-row-1'>Status</th>                                
<th class='table-header-row-1'>Code</th>                                
<th class='table-header-row-1'>Date Time</th>
</tr>
</thead><tbody>";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID'])) {
    $device_ids = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);
    $dbName     = strtoupper($device_ids); // DB name based on device ID

    try {
        // filter optional - only if "device_id" field exists in collection
        $filter = [ "device_id" => $dbName ];
        // if not required, use: $filter = [];

        $options = [
            "sort"  => ["_id" => -1],
            "limit" => 20
        ];

        // Ensure correct DB & collection reference
        $cursor = $devices_db_conn->software_update_status->find($filter, $options);

        $hasData = false;
        foreach ($cursor as $doc) {
            $hasData = true;

            $status     = $doc['status']      ?? '';
            $statusCode = $doc['status_code'] ?? '';
            $dateTime   = '';

            if (!empty($doc['date_time']) && $doc['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
                $dt = $doc['date_time']->toDateTime();
                $dt->modify('+5 hours 30 minutes'); // UTC → IST
                $dateTime = $dt->format("Y-m-d H:i:s");
            } else {
                $dateTime = $doc['date_time'] ?? '';
            }

            $data .= "<tr>
                <td>{$status}</td>
                <td>{$statusCode}</td>
                <td>{$dateTime}</td>
            </tr>";
        }

        if (!$hasData) {
            $data .= '<tr><td class="text-danger" colspan="3">No records found</td></tr>';
        }

    } catch (Exception $e) {
        $data .= '<tr><td class="text-danger" colspan="3">Query execution failed: ' 
               . htmlspecialchars($e->getMessage()) . '</td></tr>';
    }

    $data .= "</tbody>";

    echo $data;
}
?>
