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

// Early exit for non-POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Input validation and sanitization
$group_id = filter_input(INPUT_POST, 'GROUP_ID', FILTER_SANITIZE_STRING);
$items_per_page = max(1, min(1000, filter_input(INPUT_POST, 'items_per_page', FILTER_VALIDATE_INT) ?: 100));
$page = max(1, filter_input(INPUT_POST, 'page', FILTER_VALIDATE_INT) ?: 1);
$offset = ($page - 1) * $items_per_page;

if (!$group_id) {
    echo json_encode(['error' => 'Group ID is required']);
    exit;
}

try {
    // Include device selection logic
    include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");

    // Validate user_devices
    if (empty($user_devices)) {
        echo json_encode([
            'data' => [],
            'total_records' => 0,
            'current_page' => $page,
            'items_per_page' => $items_per_page,
            'total_pages' => 0
        ]);
        exit;
    }

    // Process user devices array
    $user_devices = rtrim($user_devices, ',');
    $user_devices_arr = array_filter(array_map(function ($item) {
        return trim(trim($item, "'"));
    }, explode(',', $user_devices)));

    if (empty($user_devices_arr)) {
        echo json_encode([
            'data' => [],
            'total_records' => 0,
            'current_page' => $page,
            'items_per_page' => $items_per_page,
            'total_pages' => 0
        ]);
        exit;
    }

    // Database operations
    $collection = $devices_db_conn->sim_card_details;
    $filter = ['device_id' => ['$in' => $user_devices_arr]];

    // Get total count for pagination
    $total_records = $collection->countDocuments($filter);
    $total_pages = ceil($total_records / $items_per_page);

    // Fetch paginated results with optimizations
    $options = [
        'sort' => ['sim_ccid' => 1],
        'skip' => $offset,
        'limit' => $items_per_page,
        'projection' => [
            'device_id' => 1,
            'sim_ccid' => 1,
            'imei_no' => 1,
            'fw_no' => 1,
            'pcb_no' => 1,
            'date_time' => 1,
            '_id' => 0
        ]
    ];

    $cursor = $collection->find($filter, $options);

    $data = [];
    foreach ($cursor as $doc) {
        // Format date with timezone
        $date_time = '';
        if (isset($doc['date_time']) && $doc['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
            try {
                $date_time = $doc['date_time']
                    ->toDateTime()
                    ->setTimezone(new DateTimeZone('Asia/Kolkata'))
                    ->format('H:i:s d-m-Y');
            } catch (Exception $e) {
                $date_time = 'Invalid Date';
            }
        }

        $data[] = [
            "D_ID" => $doc['device_id'] ?? '',
            "CCID" => $doc['sim_ccid'] ?? '',
            "IMEI" => $doc['imei_no'] ?? '',
            "FW" => $doc['fw_no'] ?? '',
            "PCB" => $doc['pcb_no'] ?? '',
            "DATE_TIME" => $date_time
        ];
    }

    // Enhanced response with pagination metadata
    $response = [
        'data' => $data,
        'total_records' => $total_records,
        'current_page' => $page,
        'items_per_page' => $items_per_page,
        'total_pages' => $total_pages,
        'records_on_page' => count($data)
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    error_log("SIM Card Details Error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Database error occurred',
        'data' => [],
        'total_records' => 0
    ]);
}
?>