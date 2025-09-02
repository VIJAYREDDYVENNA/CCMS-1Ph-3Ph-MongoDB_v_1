<?php
require_once '../base-path/config-path.php';
require_once BASE_PATH . 'config_db/config.php';
require_once BASE_PATH . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];

$return_response = "";
$add_confirm = false;
$code = "";
$user_devices = "";
$group_by_column = "";
$group_list = [];

$device_list = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["PHASE"])) {
    $phaseWise = $_POST['PHASE'];
    if (in_array($phaseWise, ["3PH", "1PH", "ALL"])) {
        $group_id = "ALL";
        $selected_phase = strtoupper($phaseWise);
        $_SESSION["SELECTED_PHASE"] = $selected_phase;

        include_once("selecting_group_device.php"); // Assumed to fill $device_list

        $_SESSION["DEVICES_LIST"] = json_encode($device_list);

        try {
            // Use MongoDB user database connection
            global $user_db_conn;
            $device_selection_group = $user_db_conn->device_selection_group;
            $device_list_by_group = $user_db_conn->device_list_by_group;

            // Fetch group_by for user_login_id
            $group_doc = $device_selection_group->findOne(['login_id' => (int)$user_login_id]);

            if (!$group_doc || empty($group_doc['group_by'])) {
                $group_by_column = "device_group_or_area";
            } else {
                $group_by_column = $group_doc['group_by'];
            }

            // Build filter for group list query
            $filter = ['login_id' => (int)$user_login_id];

            if ($selected_phase !== "ALL") {
                $filter['phase'] = $selected_phase;
            }

            // Aggregate distinct group_list values
            $pipeline = [
                ['$match' => $filter],
                ['$group' => ['_id' => '$' . $group_by_column]],
                ['$sort' => ['_id' => 1]]
            ];

            $cursor = $device_list_by_group->aggregate($pipeline);

            $group_list = [];
            foreach ($cursor as $doc) {
                $group_value = strtoupper($doc->_id ?? "");
                $group_list[] = ["GROUP" => $group_value];
            }

            $_SESSION["GROUP_LIST"] = json_encode($group_list);

            echo json_encode([$device_list, $group_list]);
            exit;

        } catch (Exception $e) {
            echo json_encode(["error" => "Error fetching group data: " . $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode("FAIL");
        exit;
    }
} else {
    echo json_encode("FAIL");
    exit;
}
?>
