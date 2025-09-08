<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();
$user_login_id = $sessionVars['user_login_id'];

// Early exit if not GET request
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    exit;
}

// Optimized input handling
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$limit = max(1, min(200, filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 20));
$search_item = trim(filter_input(INPUT_GET, 'search_item', FILTER_SANITIZE_STRING) ?: '');
$user_devices = filter_input(INPUT_GET, 'user_devices', FILTER_VALIDATE_INT) ?: 0;

$skip = ($page - 1) * $limit;

try {
    $collection = $user_db_conn->user_device_group_view;
    
    // Build optimized filter
    $filter = ['login_id' => ['$ne' => $user_devices]];
    
    if ($search_item !== '') {
        $regex = new MongoDB\BSON\Regex(preg_quote($search_item), 'i');
        $filter['$or'] = [
            ['device_id' => $regex],
            ['c_device_name' => $regex],
            ['device_group_or_area' => $regex]
        ];
    }

    // MAJOR OPTIMIZATION: Single aggregation pipeline with facet for count + data
    $pipeline = [
        ['$match' => $filter],
        ['$group' => [
            '_id' => [
                'device_id' => '$device_id',
                'c_device_name' => '$c_device_name', 
                'device_group_or_area' => '$device_group_or_area'
            ]
        ]],
        ['$sort' => ['_id.device_id' => 1]],
        ['$facet' => [
            'data' => [
                ['$skip' => $skip],
                ['$limit' => $limit],
                ['$project' => [
                    '_id' => 0,
                    'device_id' => '$_id.device_id',
                    'c_device_name' => '$_id.c_device_name',
                    'device_group_or_area' => '$_id.device_group_or_area'
                ]]
            ],
            'count' => [
                ['$count' => 'total']
            ]
        ]]
    ];

    // Single database call with optimizations
    $options = [
        'allowDiskUse' => true,
        'maxTimeMS' => 30000, // 30 second timeout
        'batchSize' => $limit
    ];

    $result = $collection->aggregate($pipeline, $options)->toArray()[0];
    
    $data = [];
    foreach ($result['data'] as $doc) {
        $data[] = [
            'device_id' => $doc['device_id'] ?? '',
            'device_name' => $doc['c_device_name'] ?? '',
            'device_group_or_area' => $doc['device_group_or_area'] ?? ''
        ];
    }

    $totalRecords = $result['count'][0]['total'] ?? 0;
    $totalPages = $totalRecords > 0 ? ceil($totalRecords / $limit) : 0;

    // Optimized JSON response
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo json_encode([
        'data' => $data,
        'totalPages' => $totalPages,
        'totalRecords' => $totalRecords,
        'currentPage' => $page,
        'itemsPerPage' => $limit
    ], JSON_UNESCAPED_UNICODE);

} catch (MongoDB\Driver\Exception\CommandException $e) {
    // MongoDB specific errors
    error_log("MongoDB command error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'data' => [],
        'totalPages' => 0,
        'totalRecords' => 0,
        'error' => 'Database query failed'
    ]);
} catch (Exception $e) {
    // General errors
    error_log("Admin devices error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'data' => [],
        'totalPages' => 0,
        'totalRecords' => 0,
        'error' => 'Server error occurred'
    ]);
}
exit;
?>