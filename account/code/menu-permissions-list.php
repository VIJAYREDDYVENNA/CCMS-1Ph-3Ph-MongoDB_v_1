<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';



SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$permissionVariables = $_SESSION['menu_permission_variables'] ?? "";  // e.g. "on_off_control, on_off_mode, device_info_update"

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'])) {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

   

    if (empty($permissionVariables)) {
        sendResponse(['status' => 'error', 'message' => 'No menu permission variables found in session.']);
    }

    $permissionFields = array_map('trim', explode(',', $permissionVariables));

    // Build projection object dynamically to fetch only the requested fields
    $projection = [];
    foreach ($permissionFields as $field) {
        if ($field !== '') {
            $projection[$field] = 1;
        }
    }

    try {
        // MongoDB field 'login_id' assumed as integer - cast accordingly if needed
        $userIdValue = intval($userId);

        $permissionDoc = $user_db_conn->menu_permissions_list->findOne(
            ['login_id' => $userIdValue],
            ['projection' => $projection]
        );

        if ($permissionDoc === null) {
            sendResponse(['status' => 'error', 'message' => 'Menu permissions not found for user.']);
        }

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
