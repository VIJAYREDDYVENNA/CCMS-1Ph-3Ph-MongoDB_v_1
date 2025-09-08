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

// Initialize response with totalRecords
$response = ["data" => [], "totalPages" => 0, "totalRecords" => 0];

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(500, intval($_GET['limit']))) : 20;
    $search_item = isset($_GET['search_item']) ? trim($_GET['search_item']) : '';
    $user_devices = isset($_GET['user_devices']) ? intval($_GET['user_devices']) : 0;

    $offset = ($page - 1) * $limit;

    try {
        $collection = $user_db_conn->user_device_group_view;

        // First, fetch device_ids of user_devices to exclude
        $excludedDevicesCursor = $collection->find(['login_id' => $user_devices], ['projection' => ['device_id' => 1]]);
        $excludedDevices = [];
        foreach ($excludedDevicesCursor as $doc) {
            if (isset($doc['device_id'])) {
                $excludedDevices[] = $doc['device_id'];
            }
        }

        // Build main filter: devices with login_id = logged in user, excluding devices in excludedDevices
        $filter = [
            'login_id' => $user_login_id,
            'device_id' => ['$nin' => $excludedDevices],
        ];

        if ($search_item !== '') {
            $searchRegex = new MongoDB\BSON\Regex(preg_quote($search_item), 'i');
            $filter['$or'] = [
                ['device_id' => $searchRegex],
                ['c_device_name' => $searchRegex],
                ['device_group_or_area' => $searchRegex]
            ];
        }

        // Count total matching documents - THIS WAS MISSING!
        $totalRecords = $collection->countDocuments($filter);
        $totalPages = ceil($totalRecords / $limit);

        // Projection and sort options
        $options = [
            'limit' => $limit,
            'skip' => $offset,
            'projection' => ['device_id' => 1, 'c_device_name' => 1, 'device_group_or_area' => 1, '_id' => 0],
            'sort' => ['device_id' => 1],
        ];

        $cursor = $collection->find($filter, $options);

        $data = [];
        foreach ($cursor as $doc) {
            $data[] = [
                'device_id' => $doc['device_id'] ?? '',
                'device_name' => $doc['c_device_name'] ?? '',
                'device_group_or_area' => $doc['device_group_or_area'] ?? '',
            ];
        }

        // Enhanced response with all required fields
        $response = [
            'data' => $data, 
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords, // THIS WAS MISSING!
            'currentPage' => $page,
            'itemsPerPage' => $limit
        ];

    } catch (Exception $e) {
        error_log("Admin devices error: " . $e->getMessage());
        $response = [
            'data' => [],
            'totalPages' => 0,
            'totalRecords' => 0,
            'error' => 'Database error occurred'
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>