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
$client_dashboard_login  = $sessionVars['client'];
$dashboard_version = $sessionVars['client_login'];

$response = [];

function sendResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

function sanitize_input($data) {
    if (is_string($data)) {
        return trim(htmlspecialchars($data));
    }
    return $data;
}

function hasUpdatePermission($user_db_conn, $user_login_id) {
    $userPerm = $user_db_conn->user_permissions->findOne(['login_id' => (int)$user_login_id], ['projection' => ['user_details_updates' => 1]]);
    if ($userPerm && isset($userPerm['user_details_updates']) && intval($userPerm['user_details_updates']) === 1) {
        return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Common sanitization
    $status = isset($_POST['STATUS']) ? sanitize_input($_POST['STATUS']) : '';
    $rawDevices = isset($_POST['DEVICES']) ? $_POST['DEVICES'] : '';
    $userId = (int)isset($_POST['USERID']) ? sanitize_input($_POST['USERID']) :0 ;

    if (empty($rawDevices) || empty($userId)) {
        $response['status'] = 'error';
        $response['message'] = 'Devices and User ID are required.';
        sendResponse($response);
    }

    // Check permission prior to any DB operation
    if (!hasUpdatePermission($user_db_conn, $user_login_id)) {
        $response['status'] = 'error';
        $response['message'] = "This account doesn't have permission to update.";
        sendResponse($response);
    }

    $deviceArray = array_map('trim', explode(',', $rawDevices));
    if (empty($deviceArray)) {
        $response['status'] = 'error';
        $response['message'] = 'Invalid device list.';
        sendResponse($response);
    }

    if ($status === 'DELETE') {
        // DELETE devices matching userId and device ID in list
        $deleteResult = $user_db_conn->user_device_list->deleteMany([
            'login_id' => $userId,
            'device_id' => ['$in' => $deviceArray]
        ]);

        $response['status'] = $deleteResult->getDeletedCount() > 0 ? 'success' : 'error';
        $response['message'] = $deleteResult->getDeletedCount() > 0 ? 'Devices deleted successfully.' : 'Error deleting devices.';
        sendResponse($response);

    } elseif ($status === 'ADD') {
        // FETCH role for $userId
        $loginDetails = $user_db_conn->login_details->findOne(['id' => (int)$userId], ['projection' => ['role' => 1]]);
        $userRole = $loginDetails['role'] ?? null;

        if (!$userRole) {
            $response['status'] = 'error';
            $response['message'] = 'User role not found.';
            sendResponse($response);
        }

        // Find devices owned by logged in user ($user_login_id)
        $existingDevices = $user_db_conn->user_device_list->find([
            'login_id' => $user_login_id,
            'device_id' => ['$in' => $deviceArray]
        ], ['projection' => ['device_id' => 1, 'c_device_name' => 1, 's_device_name' => 1, 'phase' => 1]]);

        $devicesToInsert = [];
        foreach ($existingDevices as $device) {
            $devicesToInsert[] = [
                'device_id' => $device['device_id'],
                'c_device_name' => $device['c_device_name'],
                's_device_name' => $device['s_device_name'],
                'role' => $userRole,
                'login_id' => $userId,
                'phase' => $device['phase'] ?? ''
            ];
        }

        if (count($devicesToInsert) === 0) {
            $response['status'] = 'error';
            $response['message'] = 'No matching devices found for the user.';
            sendResponse($response);
        }

        try {
            $insertResult = $user_db_conn->user_device_list->insertMany($devicesToInsert);
            $response['status'] = 'success';
            $response['message'] = 'Devices added successfully.';
        } catch (Exception $e) {
            $response['status'] = 'error';
            $response['message'] = 'Error adding devices: ' . $e->getMessage();
        }
        sendResponse($response);

    } elseif ($status === 'SYNC') {
        // Fetch logged in user role (needed for inserts below)
        $loginDetails = $user_db_conn->login_details->findOne(['id' => $user_login_id], ['projection' => ['role' => 1]]);
        $loggedInUserRole = $loginDetails['role'] ?? '';

        if (!$loggedInUserRole) {
            $response['status'] = 'error';
            $response['message'] = 'Logged in user role not found.';
            sendResponse($response);
        }

        // Find existing devices for $userId matching deviceArray
        $existingDevices = $user_db_conn->user_device_list->find([
            'login_id' => (int)$userId,
            'device_id' => ['$in' => $deviceArray]
        ], ['projection' => ['device_id' => 1, 'c_device_name' => 1, 's_device_name' => 1, 'phase' => 1]]);

       /* $insertDocs = [];
        foreach ($existingDevices as $device) {
            $insertDocs[] = [
                ['device_id' => $device['device_id'],'login_id' => $user_login_id],
                ['$set' =>['c_device_name' => $device['c_device_name'],
                's_device_name' => $device['s_device_name'],
                'role' => $loggedInUserRole,
                
                'phase' => $device['phase'] ?? '3PH']],
                 ["upsert" => true]
            ];
        }       
        $user_db_conn->user_device_list->updateMany($insertDocs);*/

        $c = [];
        foreach ($existingDevices as $device) {
            $bulkOps[] = [
                'updateOne' => [
            ['device_id' => $device['device_id'], 'login_id' => $user_login_id], // filter
            ['$set' => [
                'c_device_name' => $device['c_device_name'],
                's_device_name' => $device['s_device_name'],
                'role' => $loggedInUserRole,
                'phase' => isset($device['phase']) ? $device['phase'] : '3PH'
            ]],
            ['upsert' => true]
        ]
    ];
}

if (count($bulkOps) === 0) {
    $response['status'] = 'error';
    $response['message'] = 'No matching devices found to add.';
    sendResponse($response);
}

try {
    if (!empty($bulkOps)) {
        $result = $user_db_conn->user_device_list->bulkWrite($bulkOps);


            // Get group_by column from device_selection_group collection
        $groupByDoc = $user_db_conn->device_selection_group->findOne(['login_id' => $user_login_id], ['projection' => ['group_by' => 1]]);
        $groupByColumn = $groupByDoc['group_by'] ?? 'device_group_or_area';

            // Aggregate group list from device_list_by_group
        $groupCursor = $user_db_conn->device_list_by_group->aggregate([
            ['$match' => ['login_id' => $user_login_id]],
            ['$group' => ['_id' => '$' . $groupByColumn]],
            ['$sort' => ['_id' => 1]],
        ]);

        $group_list = [];
        foreach ($groupCursor as $groupRow) {
            if (isset($groupRow['_id'])) {
                $group_list[] = ['GROUP' => strtoupper($groupRow['_id'])];
            }
        }

            // Store group list in session (simulate PHP session)
        $_SESSION['GROUP_LIST'] = json_encode($group_list);

        $response['status'] = 'success';
        $response['message'] = 'Devices added successfully.';
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = 'Error adding devices: ' . $e->getMessage();
}

sendResponse($response);


} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid STATUS parameter.';
    sendResponse($response);
}
}

sendResponse(['status' => 'error', 'message' => 'Invalid request method or parameters.']);
?>
