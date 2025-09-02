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

$response = ["status" => "error", "message" => "", "group_list" => []];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Example: expected from frontend "device_group_or_area", "state", "city_or_town"
    $groupField = trim($_POST['GROUP'] ?? 'device_group_or_area');  



    try {
        $device_selection_group = $user_db_conn->device_selection_group;
        $device_list_by_group   = $user_db_conn->device_list_by_group;

        // Save selected grouping field for user
        $filter  = ['login_id' => (int)$user_login_id];
        $update  = ['$set' => ['group_by' => $groupField]];
        $options = ['upsert' => true];
        $device_selection_group->updateOne($filter, $update, $options);

        // Fetch distinct values for that group field from device_list_by_group
        $pipeline = [
            ['$match' => ['login_id' => (int)$user_login_id]],
            ['$group' => ['_id' => '$' . $groupField]],
            ['$sort' => ['_id' => 1]]
        ];

        $cursor = $device_list_by_group->aggregate($pipeline);

        $group_list = [];
        foreach ($cursor as $doc) {
            $group_name = strtoupper($doc->_id ?? '');
            if (!empty($group_name)) {
                $group_list[] = ["GROUP" => $group_name];
            }
        }

        $response["status"]     = "success";
        $response["message"]    = "Successfully Updated..";
        $_SESSION["GROUP_LIST"] = json_encode($group_list);
        $response["group_list"] = $group_list;

    } catch (Exception $e) {
        $response["message"] = "Error: " . $e->getMessage();
    }

} else {
    $response["message"] = "Invalid request method.";
}

echo json_encode($response);
