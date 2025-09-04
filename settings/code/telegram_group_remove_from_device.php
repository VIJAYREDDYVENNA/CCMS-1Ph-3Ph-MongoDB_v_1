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
$user_login_id = $sessionVars['user_login_id'];

$response = ["status" => "error", "message" => ""];

if (isset($_POST['ID']) && isset($_POST['GROUP_ID'])) {
    $id = filter_input(INPUT_POST, 'ID', FILTER_SANITIZE_STRING);
    $GROUP_ID = filter_input(INPUT_POST, 'GROUP_ID', FILTER_SANITIZE_STRING);

    $user_permissions_collection = $user_db_conn->user_permissions;
    $telegram_groups_new_collection = $user_db_conn->telegram_groups_new;
    $telegram_groups_devices_collection = $user_db_conn->telegram_groups_devices;

    // Check user permissions
    $permission_check = $user_permissions_collection->findOne(
        ['login_id' => (int)$user_login_id], 
        ['projection' => ['notification_update' => 1]]
    );

    if ($permission_check && $permission_check['notification_update'] != 1) {
        $response['status'] = 'error';
        $response['message'] = "This account doesn't have permission to update.";
        sendResponse($response);
    }

    if ($permission_check['notification_update'] == 1) {
        // Get group_id from telegram_groups_new collection based on chat_id
        $group = $telegram_groups_new_collection->findOne(['chat_id' => $GROUP_ID]);
        $group_id = isset($group['chat_id']) ? $group['chat_id'] : null;

        if ($group_id) {
            // Prepare device list and remove devices associated with the group_id
            $device_list = explode(',', $id); // Devices that need to be removed
            $device_list = array_map('strtoupper', array_map('trim', $device_list)); // Sanitize device IDs

            // Remove devices from the telegram_groups_devices collection based on group_id and device_id
            $delete_result = $telegram_groups_devices_collection->deleteMany(
                [
                    'device_id' => ['$in' => $device_list], // Match device IDs to be removed
                    'chat_id' => $group_id // Match the group_id (chat_id)
                ]
            );

            if ($delete_result->getDeletedCount() > 0) {
                $response['status'] = 'success';
                $response['message'] = "Devices successfully removed from the group.";
            } else {
                $response['status'] = 'error';
                $response['message'] = "No matching devices found to remove.";
            }
        } else {
            $response['status'] = 'error';
            $response['message'] = "Group with the provided chat_id not found.";
        }
    }

    sendResponse($response);
} else {
    $response['status'] = 'error';
    $response['message'] = "Please provide valid data.";
    sendResponse($response);
}

// Send JSON response
function sendResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
