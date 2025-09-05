<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

// Check session and retrieve session variables
SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();
$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_email = $sessionVars['user_email'];
$user_login_id = $sessionVars['user_login_id'];

$permission_check = 0;
$response = ["status" => "error", "message" => ""];

if (isset($_POST['ID']) && isset($_POST['CHAT_ID'])) {
    $id = filter_input(INPUT_POST, 'ID', FILTER_SANITIZE_STRING);
    $group_name = filter_input(INPUT_POST, 'GROUP', FILTER_SANITIZE_STRING);
    $chat_id = filter_input(INPUT_POST, 'CHAT_ID', FILTER_SANITIZE_STRING);

  
    $telegram_groups_collection = $user_db_conn->telegram_groups_new;
    $user_permissions_collection = $user_db_conn->user_permissions;
    $telegram_groups_devices_collection = $user_db_conn->telegram_groups_devices;

    // Check user permissions
    $permission_check = $user_permissions_collection->findOne(['login_id' => (int)$user_login_id], ['projection' => ['notification_update' => 1]]);
    
    if ($permission_check && $permission_check['notification_update'] != 1) {
        $response['status'] = 'error';
        $response['message'] = "This account doesn't have permission to update.";
        sendResponse($response);
    }

    if ($permission_check['notification_update'] == 1) {
        date_default_timezone_set('Asia/Kolkata');
        $date = date("Y-m-d H:i:s");
        $group_id = 0;

        // Fetch group id
        $group = $telegram_groups_collection->findOne(['group_name' => $group_name, 'chat_id' => $chat_id]);
        if ($group) {
            $group_id = $group['chat_id']; 
        }

        // Prepare device-group pairs
        $device_group_pairs = [];
        foreach (explode(',', $id) as $deviceId) {
            $device_group_pairs[] = [
                'device_id' => strtoupper(trim($deviceId)),
                'chat_id' => $group_id
            ];
        }

        // Upsert devices into telegram_groups_devices collection
        foreach ($device_group_pairs as $pair) {
            $result = $telegram_groups_devices_collection->updateOne(
                ['device_id' => $pair['device_id'], 'chat_id' => $pair['chat_id']], // Filter
                ['$set' => $pair], // Update operation
                ['upsert' => true] // Perform upsert if document doesn't exist
            );

            if ($result->getModifiedCount() > 0 || $result->getUpsertedCount() > 0) {
                $response['status'] = 'success';
                $response['message'] = "Successfully Saved";
            } else {
                $response['status'] = 'error';
                $response['message'] = "No changes made. Already Exist";
            }
        }
    }

    sendResponse($response);
} else {
    $response['status'] = 'error';
    $response['message'] = "Please try again.";
    sendResponse($response);
}

// Send JSON response
function sendResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
