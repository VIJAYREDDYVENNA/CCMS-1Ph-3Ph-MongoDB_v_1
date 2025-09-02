<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

use MongoDB\BSON\ObjectId;

$mobile_no     = $sessionVars['mobile_no'];
$user_id       = $sessionVars['user_id'];
$role          = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];
$user_name     = $sessionVars['user_name'];
$user_email    = $sessionVars['user_email'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID']) && isset($_POST['ROW']) && isset($_POST['ROW_VIEW'])) {

	$device_ids = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);
    $row_id     = filter_input(INPUT_POST, 'ROW', FILTER_SANITIZE_STRING);  // keep as string!
    $row_search = filter_input(INPUT_POST, 'ROW_VIEW', FILTER_SANITIZE_STRING);

    $collection = $devices_db_conn->saved_settings_on_device;
    $send       = [];



    // Base filter by device_id
    $filter = ['device_id' => $device_ids];

    // Query options
    $options = [
    	'sort'  => ['_id' => -1],
    	'limit' => 1
    ];

    // Build filter conditions
    if (!empty($row_id) && preg_match('/^[a-f0-9]{24}$/i', $row_id)) {
    	$compareId = new ObjectId($row_id);

    	if ($row_search === "PREV") {
    		$filter['_id'] = ['$lt' => $compareId];
            $options['sort'] = ['_id' => -1]; // get immediate previous
        } elseif ($row_search === "NEXT") {
        	$filter['_id'] = ['$gt' => $compareId];
            $options['sort'] = ['_id' => 1];  // get immediate next
        }
    }

    if ($row_search === "LATEST") {
        // Reset filter just to latest doc for this device
    	$filter = ['device_id' => $device_ids];
    	$options['sort'] = ['_id' => -1];
    }

    // Run query
    $cursor = $collection->find($filter, $options);

    foreach ($cursor as $r) {
    	$frame_val = isset($r['frame']) ? $r['frame'] : "";
    	$id_val    = isset($r['_id']) ? (string)$r['_id'] : "";
        // Append _id to frame string
    	$send = explode(";", $frame_val . ";" . $id_val);
    	break;
    }

    echo json_encode($send);
}
?>
