<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

use MongoDB\BSON\ObjectId;

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();
$user_login_id = $sessionVars['user_login_id'];

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

// MongoDB collections
$permissionColl = $user_db_conn->user_permissions;
$electriciansListColl = $user_db_conn->electricians_list;
$electricianDevicesColl = $user_db_conn->electrician_devices;

// Check permission
$permission = $permissionColl->findOne(
    ['login_id' => (int)$user_login_id],
    ['projection' => ['add_remove_electrician' => 1]]
);

if (empty($permission) || $permission['add_remove_electrician'] != 1) {
    echo json_encode(["status" => "error", "message" => "You do not have permission to Add or Remove electricians and Devices."]);
    exit;
}

// SINGLE DELETE
if (isset($_POST["electrician_id"])) {
    try {
        $id = $_POST["electrician_id"];
        $electricianObj = $electriciansListColl->findOne(['_id' => new ObjectId($id)]);
        if (!$electricianObj) {
            echo json_encode(["status" => "error", "message" => "Electrician not found."]);
            exit;
        }

        $electrician_name = $electricianObj['name'];
        $electrician_phone = $electricianObj['phone_number'];

        // Delete from electricians_list
        $electriciansListColl->deleteOne(['_id' => new ObjectId($id)]);

        // Delete from electrician_devices
        $electricianDevicesColl->deleteMany([
            'electrician_name' => $electrician_name,
            'phone_number' => $electrician_phone
        ]);

        echo json_encode(["status" => "success", "message" => "Electrician access removed successfully."]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}

// MULTIPLE DELETE
elseif (isset($_POST["electrician_ids"])) {
    try {
        $ids = json_decode($_POST["electrician_ids"], true);
        if (!is_array($ids) || empty($ids)) {
            echo json_encode(["status" => "error", "message" => "No valid IDs provided."]);
            exit;
        }

        $objectIds = array_map(fn($id) => new ObjectId($id), $ids);

        // Fetch names and phones first
        $cursor = $electriciansListColl->find(['_id' => ['$in' => $objectIds]]);
        $electricianData = [];
        foreach ($cursor as $doc) {
            $electricianData[] = [
                'name' => $doc['name'],
                'phone' => $doc['phone_number']
            ];
        }

        if (!empty($electricianData)) {
            // Delete from electricians_list
            $electriciansListColl->deleteMany(['_id' => ['$in' => $objectIds]]);

            // Delete from electrician_devices
            foreach ($electricianData as $item) {
                $electricianDevicesColl->deleteMany([
                    'electrician_name' => $item['name'],
                    'phone_number' => $item['phone']
                ]);
            }
        }

        echo json_encode(["status" => "success", "message" => "Selected electricians removed successfully."]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}

// INVALID REQUEST
else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}
?>
