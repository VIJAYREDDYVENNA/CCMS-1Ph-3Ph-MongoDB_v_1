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

$device_list = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["GROUP_ID"])) {
    $group_id = strtoupper(trim($_POST['GROUP_ID']));
    $selected_phase = strtoupper($_SESSION["SELECTED_PHASE"]);

    // Default group_by
    $group_by = "device_group_or_area";

    // 🔹 If group_id == ALL
    if ($group_id === "ALL") {
        $filter = ["login_id" => intval($user_login_id)];

        if ($selected_phase !== "ALL") {
            $filter["phase"] = $selected_phase;
        }

        // Exclude devices already in electrician_devices
        $assignedDevices = $devices_db_conn->electrician_devices->distinct("device_id");
        if (!empty($assignedDevices)) {
            $filter["device_id"] = ['$nin' => $assignedDevices];
        }

        $cursor = $user_db_conn->user_device_list->find(
            $filter,
            ["projection" => ["device_id" => 1, "device_name" => 1]]
        );

    } else {
        // 🔹 Get group_by from device_selection_group
        $groupData = $user_db_conn->device_selection_group->findOne(
            ["login_id" => intval($user_login_id)],
            ["projection" => ["group_by" => 1]]
        );

        if ($groupData && isset($groupData["group_by"])) {
            $group_by = $groupData["group_by"];
        }

        // Build filter
        $filter = [
            "login_id" => intval($user_login_id),
            $group_by => $group_id
        ];

        if ($selected_phase !== "ALL") {
            $filter["phase"] = $selected_phase;
        }

        // Exclude already assigned
        $assignedDevices = $devices_db_conn->electrician_devices->distinct("device_id");
        if (!empty($assignedDevices)) {
            $filter["device_id"] = ['$nin' => $assignedDevices];
        }

        $cursor = $user_db_conn->user_device_group_view->find(
            $filter,
            ["projection" => ["device_id" => 1, "device_name" => 1]]
        );
    }

    // 🔹 Prepare response (safe access for device_name)
    foreach ($cursor as $doc) {
        $device_list[] = [
            "D_ID" => $doc['device_id'] ?? "",
            "D_NAME" => $doc['device_name'] ?? $doc['device_id'] // fallback
        ];
    }

    echo json_encode($device_list);

} else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}
