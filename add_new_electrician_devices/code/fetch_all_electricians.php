<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars   = SessionManager::SessionVariables();
$mobile_no     = $sessionVars['mobile_no'];
$user_id       = $sessionVars['user_id'];
$role          = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];

header('Content-Type: application/json');

try {
    $electriciansColl = $user_db_conn->electricians_list;
    $filter = [];

    if ($role !== "SUPERADMIN") {
        $filter['user_login_id'] = (int)$user_login_id;
    }

    $cursor = $electriciansColl->find(
        $filter,
        ['sort' => ['name' => 1]] // Sort by name ascending
    );

    $electricians = [];
    foreach ($cursor as $doc) {
        $electricians[] = [
            "id"    => (string)$doc["_id"],
            "name"  => $doc["name"] ?? null,
            "phone" => $doc["phone_number"] ?? null
        ];
    }

    echo json_encode([
        "status" => "success",
        "data"   => $electricians
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage(),
        "data"    => []
    ]);
}
