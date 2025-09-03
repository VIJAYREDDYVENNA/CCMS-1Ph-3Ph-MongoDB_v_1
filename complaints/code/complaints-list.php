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

$d_name = "";
$data = "";
$count = 0;
$device_list = json_decode($_SESSION["DEVICES_LIST"]);

$send = array();
$send = "";
$user_devices = [];

$selected_device_id = isset($_POST['ID']) ? filter_input(INPUT_POST, 'ID', FILTER_SANITIZE_STRING) : '';
$selection = isset($_POST['SELECTION']) ? (int)$_POST['SELECTION'] : 0;
$group = isset($_POST['GROUP']) ? filter_input(INPUT_POST, 'GROUP', FILTER_SANITIZE_STRING) : '';
$complaint_status = isset($_POST['COMPLAINT_STATUS']) ? (int)$_POST['COMPLAINT_STATUS'] : 0;

$limit = 100;
$offset = 0;

// Handle fetch more pagination
if (isset($_POST['FETCH_MORE']) && $_POST['FETCH_MORE'] === "MORE") {
    if ($_SESSION['FETCH_COUNT'] == 0) {
        exit();
    }
    $page = $_SESSION['FETCH_COUNT'];
    $page = $page ? intval($page) : 1;
    $limit = $limit ? intval($limit) : 1;
    $offset = ($page - 1) * $limit;
    $_SESSION['FETCH_COUNT'] = $_SESSION['FETCH_COUNT'] + 1;
} else {
    $_SESSION['FETCH_COUNT'] = 2;
}

// Complaint status filter
$statusFilter = [];
switch ($complaint_status) {
    case 1: // All
        break;
    case 2:
        $statusFilter = ['status' => 'OPEN'];
        break;
    case 3:
        $statusFilter = ['status' => 'PROGRESS'];
        break;
    case 4:
        $statusFilter = ['status' => 'CLOSED'];
        break;
    default:
        ?>
        <tr><td class="text-danger" colspan="6">No records found</td></tr>
        <?php
        exit();
}

// Build device filter
foreach ($device_list as $value) {
    $user_devices[] = $value->D_ID;
}

// MongoDB connection
global $devices_db_conn;
$collection = $devices_db_conn->complaints;

// Build query filter
$filter = $statusFilter;
if ($selection == 1) {
    // Group devices
    $filter['device_id'] = ['$in' => $user_devices];
} else if ($selection == 2 && !empty($selected_device_id)) {
    $filter['device_id'] = $selected_device_id;
}

// Define sort
if ($selection == 1) {
    // In MySQL: ORDER BY LENGTH(device_id), device_id, registered_on DESC
    // MongoDB doesn’t support LENGTH() sort, so we do simple sort
    $sort = ['device_id' => 1, 'registered_on' => -1];
} else {
    // In MySQL: ORDER BY id DESC
    $sort = ['id' => -1];
}

// Fetch from MongoDB
try {
    $cursor = $collection->find(
        $filter,
        [
            'sort' => $sort,
            'skip' => $offset,
            'limit' => $limit
        ]
    );

    $found = false;
    foreach ($cursor as $r) {
        $found = true;
        $d_name = "";

        if ($selection == 1) {
            $device_ids = array_column($device_list, 'D_ID');
            $index = array_search($r['device_id'], $device_ids);
            $d_name = $r['device_id'];
            if ($index !== false) {
                $d_name = "(" . htmlspecialchars($device_list[$index]->D_NAME) . ")";
            }
        }

        // Format registered_on
        if (isset($r['registered_on']) && $r['registered_on'] instanceof MongoDB\BSON\UTCDateTime) {
            $r['registered_on'] = $r['registered_on']->toDateTime()->format("H:i:s d-m-Y");
        }

        $status = htmlspecialchars($r['status']);
        $sts_1 = htmlspecialchars($r['complaint_no']);
        $sts_2 = htmlspecialchars($r['registered_on']);
        $sts_3 = htmlspecialchars($r['device_id']) . $d_name;
        $sts_4 = htmlspecialchars($r['complaint']);

        // Status badge
        if ($status == "OPEN") {
            $sts_5 = "<label class='badge text-bg-danger'>Pending</label>";
        } else if ($status == "PROGRESS") {
            $sts_5 = "<label class='badge text-bg-warning'>In Progress</label>";
        } else if ($status == "CLOSED") {
            $sts_5 = "<label class='badge text-bg-success'>Resolved</label>";
        }

        $check_status = '<button class="btn btn-sm btn-primary p-0 px-2" onclick=check_track("' . $sts_3 . '","' . $sts_1 . '")>Check</button>';
        ?>
        <tr>
            <td class="body-cell col2"><?php echo $sts_1; ?></td>
            <td class="body-cell col2"><?php echo $sts_2; ?></td>
            <td class="body-cell col2"><?php echo $sts_3; ?></td>
            <td class="body-cell col2 text-left" colspan="2"><?php echo $sts_4; ?></td>
            <td class="body-cell col1"><?php echo $sts_5; ?></td>
            <td class="body-cell col1"><?php echo $check_status; ?></td>
        </tr>
        <?php
    }

    if (!$found) {
        ?>
        <tr><td class="text-danger" colspan="7">No records found</td></tr>
        <?php
        $_SESSION['FETCH_COUNT'] = 0;
    }
} catch (Exception $e) {
    ?>
    <tr><td class="text-danger" colspan="7">Something went wrong</td></tr>
    <?php
}
?>
