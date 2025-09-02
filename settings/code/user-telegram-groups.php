<?php
require_once BASE_PATH . 'config_db/config.php';
require_once BASE_PATH . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();
$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];

// Assume $devices_db_conn is already initialized as MongoDB connection to ccms_data
// Example: $devices_db_conn = (new MongoDB\Client(MONGODB_URI))->ccms_data;

$device_list = array();

$filter = [];
if ($role !== "SUPERADMIN") {
    // Note: if user_id is a string in DB, remove (int) cast
    $filter['user_id'] = $user_login_id;
}

echo '<option value="">Select Telegram Group</option>';

$cursor = $user_db_conn->telegram_groups_new->find($filter);

foreach ($cursor as $r) {
    $chat_id = isset($r['chat_id']) ? htmlspecialchars($r['chat_id'], ENT_QUOTES) : '';
    $group_name = isset($r['group_name']) ? htmlspecialchars($r['group_name'], ENT_QUOTES) : '';
    echo '<option value="' . $chat_id . '">' . $group_name . '</option>';
}
?>
