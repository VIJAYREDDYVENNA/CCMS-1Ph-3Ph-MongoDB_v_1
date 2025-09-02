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

$response = ["status" => "", "message" => ""];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_STRING);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    

    // Check permission
    $permDoc = $user_db_conn->user_permissions->findOne(
        ['login_id' => (int)$user_login_id],
        ['projection' => ['user_details_updates' => 1]]
    );

    if (empty($permDoc) || intval((int)$permDoc['user_details_updates']) !== 1) {
        $response['status'] = 'error';
        $response['message'] = "This account doesn't have permission to update.";
        sendResponse($response);
    }

    // Validate userId as ObjectId
   

    $updateFields = [];
    switch (strtoupper($action)) {
        case 'ACTIVATE':
            $updateFields = ['account_delete' => 1, 'status' => 'ACTIVE'];
            break;
        case 'HOLD':
            $updateFields = ['account_delete' => 1, 'status' => 'HOLD'];
            break;
        case 'DELETE':
            $updateFields = ['account_delete' => 0];
            break;
        default:
            $response['status'] = 'error';
            $response['message'] = 'Invalid Action.';
            sendResponse($response);
    }

    $updateResult = $user_db_conn->login_details->updateOne(
        ['id' => (int)$userId],
        ['$set' => $updateFields]
    );

    if ($updateResult->getModifiedCount() > 0) {
        $response['status'] = 'success';
        $response['message'] = 'Updated successfully.';
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Failed to update or no changes made.';
    }

    sendResponse($response);
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request.';
    sendResponse($response);
}

function sendResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
