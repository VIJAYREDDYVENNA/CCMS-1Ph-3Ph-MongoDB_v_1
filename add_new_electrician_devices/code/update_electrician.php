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

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["device_id"], $_POST["new_electrician_id"], $_POST["group_id"])) {
    $device_id           = trim($_POST["device_id"]);
    $new_electrician_id  = trim($_POST["new_electrician_id"]);
    $group_id            = trim($_POST["group_id"]);

    try {
        // 1️⃣ Check permissions
        $permissionDoc = $user_db_conn->user_permissions->findOne(['login_id' => (int)$user_login_id]);
        $add_remove_electrician = $permissionDoc['add_remove_electrician'] ?? 0;

        if ($add_remove_electrician != 1) {
            echo json_encode(["status" => "error", "message" => "You do not have permission to Add or Remove electricians and Devices."]);
            exit();
        }

        // 2️⃣ Fetch new electrician details
        $electricianDoc = $user_db_conn->electricians_list->findOne(['_id' => new MongoDB\BSON\ObjectId($new_electrician_id)]);
        if (!$electricianDoc) {
            echo json_encode(["status" => "error", "message" => "Electrician details not found."]);
            exit();
        }

        $electrician_name  = $electricianDoc['name'] ?? '';
        $electrician_phone = $electricianDoc['phone_number'] ?? '';

        // 3️⃣ Fetch group_area from user_device_group_view
        $deviceDoc = $user_db_conn->user_device_group_view->findOne(['device_id' => $device_id]);
        $group_area = $deviceDoc['device_group_or_area'] ?? '';

        if (empty($group_area)) {
            echo json_encode(["status" => "error", "message" => "Device group area not found."]);
            exit();
        }

        // 4️⃣ Upsert electrician_devices record
        $updateResult = $user_db_conn->electrician_devices->updateOne(
            ['device_id' => $device_id],
            ['$set' => [
                'electrician_name' => $electrician_name,
                'phone_number'     => $electrician_phone,
                'group_area'       => $group_area,
                'user_login_id'    => (int)$user_login_id
            ]],
            ['upsert' => true] // insert if not exists
        );

        if ($updateResult->getModifiedCount() > 0 || $updateResult->getUpsertedCount() > 0) {
            echo json_encode(["status" => "success", "message" => "Electrician assigned/updated successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "No changes were made."]);
        }

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request parameters."]);
}
?>