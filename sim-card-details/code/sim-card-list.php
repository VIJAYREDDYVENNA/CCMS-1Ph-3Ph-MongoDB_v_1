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

$send = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $group_id = $_POST['GROUP_ID'];
    include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");

    // Convert user_devices (comma string) → array
    $user_devices_arr = array_filter(explode(",", $user_devices));

      if ($user_devices != "") {
        $user_devices = rtrim($user_devices, ',');
    } else {
        echo json_encode(["error" => "No devices found for the group"]);
        exit;
    }

    // Prepare device ids array
    $user_devices_arr = array_filter(array_map(function ($item) {
        return trim(trim($item, "'"));
    }, explode(',', $user_devices)));

    // Pagination params
    $items_per_page = isset($_POST['items_per_page']) ? (int)$_POST['items_per_page'] : 100;
    $page           = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $offset         = ($page - 1) * $items_per_page;

    // Select collection
    $collection = $devices_db_conn->sim_card_details;

    // Filter
    $filter = ['device_id' => ['$in' => $user_devices_arr]];

    // Count total records
    $total_records = $collection->countDocuments($filter);

    // Fetch paginated results
    $options = [
        'sort'  => ['sim_ccid' => 1],   // ASC
        'skip'  => $offset,
        'limit' => $items_per_page
    ];

    $cursor = $collection->find($filter, $options);

    foreach ($cursor as $r) {
        $device_id = $r['device_id'] ?? '';
        $sim_ccid  = $r['sim_ccid'] ?? '';
        $imei_no   = $r['imei_no'] ?? '';
        $fw_no     = $r['fw_no'] ?? '';
        $pcb_no    = $r['pcb_no'] ?? '';
        $date_time = isset($r['date_time']) && $r['date_time'] instanceof MongoDB\BSON\UTCDateTime
            ? $r['date_time']->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('H:i:s d-m-Y')
            : '';

        $send[] = [
            "D_ID"      => $device_id,
            "CCID"      => $sim_ccid,
            "IMEI"      => $imei_no,
            "FW"        => $fw_no,
            "PCB"       => $pcb_no,
            "DATE_TIME" => $date_time
        ];
    }

    echo json_encode(['data' => $send, 'total_records' => $total_records]);
} else {
    echo json_encode(['error' => 'Data not Available']);
}
