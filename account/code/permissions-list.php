<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';



SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$permissionVariables = $_SESSION['permission_variables'] ?? "";  // e.g. "on_off_control, on_off_mode, device_info_update"

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'])) {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

   

    if (empty($permissionVariables)) {
        sendResponse(['status' => 'error', 'message' => 'No permission variables found in session.']);
    }

    $permissionFields = array_map('trim', explode(',', $permissionVariables));

    // Build projection dynamically for specific permission fields
    $projection = [];
    foreach ($permissionFields as $field) {
        if ($field !== '') {
            $projection[$field] = 1;
        }
    }

    try {
        // MongoDB stores fields with string user IDs – cast if needed
        $userIdValue = intval($userId);

        $permissionDoc = $user_db_conn->user_permissions->findOne(
            ['login_id' => $userIdValue],
            ['projection' => $projection]
        );

        if ($permissionDoc === null) {
            sendResponse(['status' => 'error', 'message' => 'User permissions not found.']);
        }

        // Prepare response permissions
        $permissions = [];
        foreach ($permissionFields as $field) {
            $permissions[$field] = $permissionDoc[$field] ?? null;
        }

        sendResponse(['status' => 'success', 'permissions' => $permissions]);
    } catch (Exception $e) {
        sendResponse(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()]);
    }
}

function sendResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
