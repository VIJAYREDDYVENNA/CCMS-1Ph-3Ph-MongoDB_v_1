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

$selection = "ALL";
$d_name = "";
$data = "";
$phase = "3PH";
$selected_phase;
$count = 0;
$device_list = json_decode($_SESSION["DEVICES_LIST"]);
$selected_phase1 = "3PH";
$phase_row = "ALL";
$send = array();
$send = "";
$user_devices = "";

// Build device arrays and IDs string
foreach ($device_list as $key => $value) {
    $id = $value->D_ID;
    $user_devices = $user_devices . "'" . $id . "',";
}

if ($user_devices != "") {
    $user_devices = substr($user_devices, 0, -1);
}

$device_ids_array = [];
foreach ($device_list as $device) {
    if (isset($device->D_ID)) {
        $device_ids_array[] = $device->D_ID;
    }
}

// Input validation and pagination setup
$recordsPerPage = max(1, min(100, filter_input(INPUT_POST, 'recordsPerPage', FILTER_VALIDATE_INT) ?: 20));
$pageNumber = max(1, filter_input(INPUT_POST, 'pageNumber', FILTER_VALIDATE_INT) ?: 1);
$skip = ($pageNumber - 1) * $recordsPerPage;

try {
    $collection = $devices_db_conn->live_data_updates;

    // Get distinct phases among devices
    $phases = $collection->distinct('phase', ['device_id' => ['$in' => $device_ids_array]]);

    if (count($phases) == 2) {
        $phase_row = "ALL";
        $selected_phase1 = "ALL";
    } elseif (count($phases) == 1) {
        $phase_row = ($phases[0] === "3PH") ? "3PH" : "1PH";
        $selected_phase1 = $phase_row;
    } else {
        $selected_phase1 = "ALL";
    }

    // Get total count for pagination
    $totalRecords = $collection->countDocuments(['device_id' => ['$in' => $device_ids_array]]);
    $totalPages = ceil($totalRecords / $recordsPerPage);

    // Fetch the data with pagination and sorting
    $cursor = $collection->find(
        ['device_id' => ['$in' => $device_ids_array]],
        [
            'sort' => ['phase' => -1, 'device_id' => 1],
            'skip' => $skip,
            'limit' => $recordsPerPage
        ]
    );

    $device_ids = array_column($device_list, 'D_ID');
    $recordsOnThisPage = 0;

    foreach ($cursor as $r) {
        $index = array_search($r['device_id'], $device_ids);
        if ($index !== false) {
            $recordsOnThisPage++;
            
            if ($phase_row == "1PH") {
                $phase = "1PH";
                $selection = "1PH";
                $selected_phase = "1PH";
            } else {
                $phase = isset($r['phase']) ? $r['phase'] : "3PH";
                $selection = "ALL";
                $selected_phase = $_SESSION["SELECTED_PHASE"];
            }

            $device_id = strtoupper($device_list[$index]->D_ID);
            $d_name = " (" . ($device_list[$index]->D_NAME ?? '') . ")";

            // Include parameters and table cells logic
            include("set_parameters.php");
            include("table_cells.php");
        }
    }

    $selected_phase = $selected_phase1;

    // Enhanced response with comprehensive pagination metadata
    $response = [
        'data' => $data,
        'selected_phase' => $selected_phase,
        'rowCount' => $totalRecords,
        'totalPages' => $totalPages,
        'currentPage' => $pageNumber,
        'recordsPerPage' => $recordsPerPage,
        'recordsOnThisPage' => $recordsOnThisPage,
        'hasNextPage' => $pageNumber < $totalPages,
        'hasPreviousPage' => $pageNumber > 1
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Data Report Error: " . $e->getMessage());
    
    // Error response with proper structure
    echo json_encode([
        'data' => '<tr><td colspan="75" class="text-danger">Error loading data</td></tr>',
        'selected_phase' => '3PH',
        'rowCount' => 0,
        'totalPages' => 0,
        'currentPage' => 1,
        'recordsPerPage' => $recordsPerPage,
        'recordsOnThisPage' => 0,
        'error' => 'Database error occurred'
    ]);
}
?>