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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'], $_POST['permissions'])) {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT); // Sanitize user ID
    $permissions = $_POST['permissions']; // Array of permissions

    // MongoDB collection references
    $userPermissionsCollection = $user_db_conn->user_permissions;
    $menuPermissionsCollection = $user_db_conn->menu_permissions_list;

    // Check if user has permission to update
    $userPermissionDoc = $userPermissionsCollection->findOne(['login_id' => (int)$user_login_id]);
    if (!$userPermissionDoc || (int)$userPermissionDoc['user_permissions'] != 1) {
        sendResponse([
            'status' => 'error',
            'message' => "This account doesn't have permission to update."
        ]);
    }

    // Sanitize permissions keys against whitelist
    $validFields = ['device_dashboard','dashboard', 'devices_list', 'onoff_control', 'gis_map', 'data_report', 'energy_consumption',
    'thresholdsettings', 'group_creation', 'location_update', 'notification_settings', 'iotsettings', 'pending_actions',
    'add_new_electrician_devices', 'phase_alerts', 'alerts', 'notification_mesages', 'graphs', 'up_down_time', 'glowing_time',
    'user_activity', 'download', 'complaints', 'office_use', 'users_list'];

    $sanitizedPermissions = [];
    foreach ($permissions as $field => $value) {
        if (in_array($field, $validFields)) {
            $sanitizedPermissions[$field] = intval($value); // cast to int for safety
        }
    }

    
    $updateResult = $menuPermissionsCollection->updateOne(
        ['login_id' => (int)$userId],       
        ['$set' => $sanitizedPermissions],   
        ['upsert' => true]                   
    );

    if ($updateResult->getModifiedCount() > 0 || $updateResult->getUpsertedCount() > 0) {
        sendResponse(['status' => 'success', 'message' => 'Permissions saved or updated successfully.']);
    } else {
        sendResponse(['status' => 'error', 'message' => 'Failed to save or update permissions.']);
    }
}

function sendResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
