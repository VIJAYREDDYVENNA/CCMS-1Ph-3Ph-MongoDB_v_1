<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = (int)$sessionVars['user_login_id'];
$user_name = $sessionVars['user_name'];
$user_email = $sessionVars['user_email'];

$response = ["data" => [], "totalPages" => 0];

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $search_item = isset($_GET['search_item']) ? trim($_GET['search_item']) : '';
    $user_devices = (int)isset($_GET['user_devices']) ? trim($_GET['user_devices']) : 0;

    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 20;

    $offset = ($page - 1) * $limit;

    $collection = $user_db_conn->user_device_group_view;

    // First, fetch device_ids of user_devices to exclude
    $excludedDevicesCursor = $collection->find(['login_id' => (int)$user_devices], ['projection' => ['device_id' => 1]]);
    $excludedDevices = [];
    foreach ($excludedDevicesCursor as $doc) {
        if (isset($doc['device_id'])) {
            $excludedDevices[] = $doc['device_id'];
        }
    }

    // Build main filter: devices with login_id = logged in user, excluding devices in excludedDevices
    $filter = [
        'login_id' => (int)$user_login_id,
        'device_id' => ['$nin' => $excludedDevices],
    ];

    if ($search_item !== '') {
        $searchRegex = new MongoDB\BSON\Regex($search_item, 'i'); // case-insensitive search
        $filter['$and'] = [
            [
                '$or' => [
                    ['device_id' => $searchRegex],
                    ['c_device_name' => $searchRegex]
                ]
            ]
        ];
    }

    // Count total matching documents
    $totalRecords = $collection->countDocuments($filter);
    $totalPages = ceil($totalRecords / $limit);

    // Projection and sort options
    $options = [
        'limit' => $limit,
        'skip' => $offset,
        'projection' => ['device_id' => 1, 'c_device_name' => 1, 'device_group_or_area' => 1, '_id' => 0],
        'sort' => [
            // MongoDB doesn't have LENGTH() function; sorting by string length requires aggregation pipeline.
            // As an approximation, sort by device_id ascending here:
            'device_id' => 1
        ],
    ];

    // If exact sorting by string length is needed, aggregation pipeline would be required.
    // For simplicity, using find with sort by device_id only.

    $cursor = $collection->find($filter, $options);

    $data = [];
    foreach ($cursor as $doc) {
     $data[] = [
        'device_id'           => isset($doc['device_id']) ? $doc['device_id'] : null,
        'device_name'         => isset($doc['c_device_name']) ? $doc['c_device_name'] : null,
        'device_group_or_area'=> isset($doc['device_group_or_area']) ? $doc['device_group_or_area'] : null,
    ];

}

$response = ['data' => $data, 'totalPages' => $totalPages];

header('Content-Type: application/json');
echo json_encode($response);
exit();
}
?>
