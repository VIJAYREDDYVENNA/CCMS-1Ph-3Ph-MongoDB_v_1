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

if (isset($_POST['CHAT_ID']) && isset($_POST['SAVE'])) {
    $chat_id = filter_input(INPUT_POST, 'CHAT_ID', FILTER_SANITIZE_STRING);
    $group_name = filter_input(INPUT_POST, 'GROUP_NAME', FILTER_SANITIZE_STRING);


    $telegram_groups_collection = $user_db_conn->telegram_groups_new;
    $user_permissions_collection = $user_db_conn->user_permissions;

    // Check user permissions
    $permission_check = $user_permissions_collection->findOne(['login_id' => (int)$user_login_id], ['projection' => ['notification_update' => 1]]);
    
    if ($permission_check && $permission_check['notification_update'] != 1) {
        $response['message'] = "You do not have permission to update.";
        sendResponse($response);
    }

    if ($permission_check['notification_update'] == 1) {
        date_default_timezone_set('Asia/Kolkata');
        $date = date("Y-m-d H:i:s");

        // Check if the chat_id already exists
        $existing_group = $telegram_groups_collection->findOne(['chat_id' => $chat_id]);

        if ($existing_group) {
            $response['message'] = "A group with the name '{$existing_group['group_name']}' already exists.";
        } else {
            // Insert the new record
            $insert_result = $telegram_groups_collection->insertOne([
                'group_name' => $group_name,
                'chat_id' => $chat_id,
                'token' => 'bot5216794704:AAGkjWy3JDm-5wBaYWGwVwwnuvWrkd5QzgE',
                'date_time' => $date,
                'user_id' => $user_login_id
            ]);

            if ($insert_result->getInsertedCount() > 0) {
                $response['status'] = 'success';
                $response['message'] = "Group successfully saved.";
            } else {
                $response['message'] = "Error saving group.";
            }
        }
    }

    sendResponse($response);
} elseif (isset($_POST['CHAT_ID']) && isset($_POST['CHECK'])) {
    $chat_id = $_POST['CHAT_ID'];
    try {
        $TG_ALERT_URL = 'https://api.telegram.org/bot5216794704:AAGkjWy3JDm-5wBaYWGwVwwnuvWrkd5QzgE/sendMessage?chat_id=' . $chat_id . '&text=Hi, this is a confirmation message, please update the same without changing the chat ID';
        $get_msg = @file_get_contents($TG_ALERT_URL);

        if ($get_msg === FALSE) {
            $response['message'] = "Invalid Chat-ID, please check and try again.";
            sendResponse($response);
        }

        $response['status'] = 'success';
        $response['message'] = "Check your Telegram group for the confirmation message.";

    } catch (Exception $e) {
        $response['message'] = "Error occurred, please check the Chat-ID and try again.";
    }

    sendResponse($response);

} else {
    $response['message'] = "Invalid request. Please try again.";
    sendResponse($response);
}

// Send JSON response
function sendResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
