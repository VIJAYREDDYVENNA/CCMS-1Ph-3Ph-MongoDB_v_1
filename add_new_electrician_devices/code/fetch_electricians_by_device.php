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
$user_devices = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["group_id"])) {
    $group_id = strtoupper(trim($_POST['group_id']));
    include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");

    if ($user_devices != "") {
        // Convert string of devices to array
        $user_devices = explode(",", rtrim($user_devices, ","));
    } else {
        $user_devices = [];
    }

    $electricians = [];
    $unassigned_devices = [];
    $group_areas = [];
    $group_by = "device_group_or_area"; // default

    if ($group_id === "ALL") {
        // 🔹 Fetch all electricians with assigned devices
        $cursor = $devices_db_conn->electrician_devices->find(
            ["device_id" => ['$in' => $user_devices]],
            ["projection" => ["id" => 1, "electrician_name" => 1, "phone_number" => 1, "device_id" => 1]]
        );

        foreach ($cursor as $row) {
            $electricians[] = [
                "id" => $row["id"] ?? "",
                "name" => $row["electrician_name"] ?? "",
                "phone" => $row["phone_number"] ?? "",
                "device_id" => $row["device_id"] ?? ""
            ];
        }

        // 🔹 Fetch unassigned devices (exclude devices already in electrician_devices)
        $assignedDevices = $devices_db_conn->electrician_devices->distinct("device_id", ["user_login_id" => intval($user_login_id)]);
        $cursor = $user_db_conn->user_device_list->find(
            [
                "login_id" => intval($user_login_id),
                "device_id" => ['$nin' => $assignedDevices]
            ],
            ["projection" => ["device_id" => 1, "c_device_name" => 1]]
        );

        foreach ($cursor as $row) {
            $unassigned_devices[] = [
                "device_id" => $row["device_id"],
                "device_name" => $row["c_device_name"] ?? $row["device_id"]
            ];
        }

    } else {
        // 🔹 Get group_by for this user
        $groupData = $user_db_conn->device_selection_group->findOne(
            ["login_id" => intval($user_login_id)],
            ["projection" => ["group_by" => 1]]
        );
        if ($groupData && isset($groupData["group_by"])) {
            $group_by = $groupData["group_by"];
        }

        // 🔹 Find group_areas if group_by != default
        if ($group_by !== "device_group_or_area") {
            $filter = [$group_by => $group_id];
            $cursor = $user_db_conn->user_device_group_view->distinct("device_group_or_area", $filter);
            $group_areas = $cursor;
        }

        // 🔹 Fetch electricians
        if (!empty($user_devices)) {
            if (!empty($group_areas)) {
                $cursor = $devices_db_conn->electrician_devices->find(
                    ["group_area" => ['$in' => $group_areas], "device_id" => ['$in' => $user_devices]],
                    ["projection" => ["id" => 1, "electrician_name" => 1, "phone_number" => 1, "device_id" => 1]]
                );
            } else {
                $cursor = $devices_db_conn->electrician_devices->find(
                    ["group_area" => $group_id, "device_id" => ['$in' => $user_devices]],
                    ["projection" => ["id" => 1, "electrician_name" => 1, "phone_number" => 1, "device_id" => 1]]
                );
            }

            foreach ($cursor as $row) {
                $electricians[] = [
                    "id" => $row["id"] ?? "",
                    "name" => $row["electrician_name"] ?? "",
                    "phone" => $row["phone_number"] ?? "",
                    "device_id" => $row["device_id"] ?? ""
                ];
            }
        }

        // 🔹 Fetch unassigned devices
        $assignedDevices = $devices_db_conn->electrician_devices->distinct("device_id", ["user_login_id" => intval($user_login_id)]);
        if (!empty($group_areas)) {
            $cursor = $user_db_conn->user_device_group_view->find(
                [
                    "device_group_or_area" => ['$in' => $group_areas],
                    "device_id" => ['$nin' => $assignedDevices]
                ],
                ["projection" => ["device_id" => 1, "c_device_name" => 1]]
            );
        } else {
            $cursor = $user_db_conn->user_device_group_view->find(
                [
                    "device_group_or_area" => $group_id,
                    "device_id" => ['$nin' => $assignedDevices]
                ],
                ["projection" => ["device_id" => 1, "c_device_name" => 1]]
            );
        }

        foreach ($cursor as $row) {
            $unassigned_devices[] = [
                "device_id" => $row["device_id"],
                "device_name" => $row["c_device_name"] ?? $row["device_id"]
            ];
        }
    }

    echo json_encode([
        "group_by" => $group_by,
        "group_areas" => $group_areas,
        "electricians" => $electricians,
        "unassigned_devices" => $unassigned_devices
    ]);

} else {
    echo json_encode([]);
}
