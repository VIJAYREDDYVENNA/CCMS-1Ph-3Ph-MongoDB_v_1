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


$userPermissionsCollection =$user_db_conn->user_permissions;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'], $_POST['permissions'])) {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);   // Sanitize user ID
    $permissions = $_POST['permissions'];  // Array of permissions

    // Check if logged-in user has permission to update
    $permissionCheckDocument = $userPermissionsCollection->findOne(['login_id' => (int)$user_login_id]);

    $permission_check = $permissionCheckDocument ? (int)($permissionCheckDocument['user_permissions'] ?? 0) : 0;

    if ($permission_check != 1) {
        $response['status'] = 'error';
        $response["message"] = "This account doesn't have permission to update.";
        sendResponse($response);
    }

    // Sanitize user ID and permissions
    $userId = sanitize_input($userId);
    $sanitizedPermissions = sanitize_permissions($permissions);

    // Check if permissions exist for this user
    $exists = $userPermissionsCollection->countDocuments(['login_id' => (int)$userId]) > 0;

    if ($exists) {
        // Update existing permissions
        $updateData = [];
        foreach ($sanitizedPermissions as $field => $value) {
            $updateData[$field] = (int)$value;
        }

        $updateResult = $userPermissionsCollection->updateOne(
            ['login_id' => (int)$userId],
            ['$set' => $updateData]
        );

        if ($updateResult->getModifiedCount() > 0) {
            $response = ['status' => 'success', 'message' => 'Permissions updated successfully.'];
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to update permissions.'];
        }
    } else {
        // Insert new permissions document
        $insertData = ['login_id' => (int)$userId];
        foreach ($sanitizedPermissions as $field => $value) {
            $insertData[$field] = (int)$value;
        }

        $insertResult = $userPermissionsCollection->insertOne($insertData);

        if ($insertResult->getInsertedId()) {
            $response = ['status' => 'success', 'message' => 'Permissions saved successfully.'];
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to insert permissions.'];
        }
    }

    sendResponse($response);
}

function sendResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    // For MongoDB, you don't use connection based escaping but still clean input
    return $data;
}

function sanitize_permissions($permissions) {
    $sanitizedPermissions = [];
    $validFields = ['on_off_control', 'on_off_mode', 'device_info_update', 'threshold_settings', 'iot_settings', 'lights_info_update', 'device_add_remove', 'user_details_updates', 'create_group', 'add_remove_electrician', 'notification_update', 'installation_status_update', 'download_data', 'user_permissions'];

    foreach ($permissions as $field => $value) {
        if (in_array($field, $validFields)) {
            // Cast the permission values to integer max as per requirement
            $sanitizedPermissions[$field] = (int)$value;
        }
    }
    return $sanitizedPermissions;
}
?>
