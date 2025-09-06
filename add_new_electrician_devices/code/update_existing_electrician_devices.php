<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php'; // gives $user_db_conn
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars   = SessionManager::SessionVariables();
$mobile_no     = $sessionVars['mobile_no'];
$user_id       = $sessionVars['user_id'];
$role          = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["device_ids"])) {
    $electrician_name  = trim($_POST['electrician_name'] ?? '');
    $electrician_phone = trim($_POST['electrician_phone'] ?? '');
    $group_id          = $_POST['group_id'] ?? null;
    $device_ids        = $_POST['device_ids'] ?? [];

    if (empty($electrician_name) || empty($electrician_phone) || empty($device_ids)) {
        echo json_encode(["status" => "error", "message" => "All fields are required."]);
        exit;
    }

    try {
        // collections
        $permissionsColl      = $user_db_conn->user_permissions;
        $deviceGroupViewColl  = $user_db_conn->user_device_group_view;
        $electriciansListColl = $user_db_conn->electricians_list;
        $electricianDevicesColl = $user_db_conn->electrician_devices;

        // 1. Permission check
        $permissionDoc = $permissionsColl->findOne(
            ["login_id" => (int)$user_login_id],
            ["projection" => ["add_remove_electrician" => 1]]
        );

        if (!$permissionDoc || ($permissionDoc["add_remove_electrician"] ?? 0) != 1) {
            echo json_encode([
                "status"  => "error",
                "message" => "You do not have permission to Add or Remove electricians and Devices."
            ]);
            exit;
        }

        // 2. Fetch group_area for provided device_ids (pick one)
        $deviceDoc = $deviceGroupViewColl->findOne(
            ["device_id" => ['$in' => $device_ids]],
            ["projection" => ["device_group_or_area" => 1]]
        );

        if (!$deviceDoc || empty($deviceDoc["device_group_or_area"])) {
            echo json_encode([
                "status"  => "error",
                "message" => "No group area found for selected devices."
            ]);
            exit;
        }

        $group_area = $deviceDoc["device_group_or_area"];

        // 3. Check if electrician already exists
        $electricianDoc = $electriciansListColl->findOne([
            "phone_number" => $electrician_phone
        ]);

        if (!$electricianDoc) {
            // Insert new electrician
            $insertResult = $electriciansListColl->insertOne([
                "name"          => $electrician_name,
                "phone_number"  => $electrician_phone,
                "group_area"    => $group_area,
                "user_login_id" => (int)$user_login_id,
                "created_at"    => new MongoDB\BSON\UTCDateTime()
            ]);

            $electrician_id = (string)$insertResult->getInsertedId();
        } else {
            $electrician_id = (string)$electricianDoc["_id"];
        }

        // 4. Assign devices to electrician
        foreach ($device_ids as $device_id) {
            $electricianDevicesColl->insertOne([
                "electrician_name" => $electrician_name,
                "phone_number"     => $electrician_phone,
                "device_id"        => $device_id,
                "group_area"       => $group_area,
                "user_login_id"    => (int)$user_login_id,
                "created_at"       => new MongoDB\BSON\UTCDateTime()
            ]);
        }

        echo json_encode([
            "status"  => "success",
            "message" => "Electrician added successfully.",
            "electrician_id" => $electrician_id
        ]);

    } catch (Exception $e) {
        echo json_encode([
            "status"  => "error",
            "message" => $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        "status"  => "error",
        "message" => "Invalid request."
    ]);
}
