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

if (isset($_POST['GROUP_ID'])) {
    $id = filter_input(INPUT_POST, 'GROUP_ID', FILTER_SANITIZE_STRING);
    $send = array();


    $user_device_list_collection = $user_db_conn->user_device_list;
    $telegram_groups_devices_collection = $user_db_conn->telegram_groups_devices;
    $telegram_groups_new_collection = $user_db_conn->telegram_groups_new;

    // Fetch group_id based on chat_id
    $group = $telegram_groups_new_collection->findOne(['chat_id' => $id]);
    $group_id = isset($group['chat_id']) ? $group['chat_id'] : null;

    if ($group_id) {
        $device_query = [
            'login_id' => (int)$user_login_id,
            'device_id' => ['$in' => $telegram_groups_devices_collection->distinct('device_id', ['chat_id' => $group_id])]
        ];

        // Query the devices list based on the user role
        if ($role == "SUPERADMIN") {
            $devices = $user_device_list_collection->find($device_query);
        } else {
            $devices = $user_device_list_collection->find($device_query);
        }

        // Collect the results
        foreach ($devices as $device) {
            $send[] = [
                'device_id' => $device['device_id'],
                'device_name' => $device['c_device_name']
            ];
        }
    }

    // Return the response
    echo json_encode($send);
}
?>
