<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

use MongoDB\BSON\ObjectId;

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();
$user_login_id = $sessionVars['user_login_id'];

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

// Check user permission
$permission = $user_db_conn->user_permissions->findOne(
    ["login_id" => (int)  $user_login_id],
    ["projection" => ["add_remove_electrician" => 1]]
);

if (!$permission || ($permission["add_remove_electrician"] != 1)) {
    echo json_encode(["status" => "error", "message" => "You do not have permission to Add or Remove electricians and Devices."]);
    exit;
}

// Single deletion
if (isset($_POST["electrician_id"])) {
    $electrician_id = $_POST["electrician_id"];

    if (empty($electrician_id)) {
        echo json_encode(["status" => "error", "message" => "No electrician ID provided."]);
        exit;
    }

    try {
        $deleteResult = $user_db_conn->electrician_devices->deleteOne([
            "_id" => new ObjectId($electrician_id)
        ]);

        if ($deleteResult->getDeletedCount() > 0) {
            echo json_encode(["status" => "success", "message" => "Electrician access removed successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "No electrician found with this ID."]);
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Error deleting electrician: " . $e->getMessage()]);
    }
    exit;
}

// Multiple deletions
if (isset($_POST["electrician_ids"])) {
    $electrician_ids = json_decode($_POST["electrician_ids"], true);

    if (!is_array($electrician_ids) || count($electrician_ids) === 0) {
        echo json_encode(["status" => "error", "message" => "No valid electrician IDs provided."]);
        exit;
    }

    try {
        $objectIds = array_map(fn($id) => new ObjectId($id), $electrician_ids);
        $deleteResult = $user_db_conn->electrician_devices->deleteMany([
            "_id" => ['$in' => $objectIds]
        ]);

        echo json_encode([
            "status" => "success",
            "message" => "Selected electricians removed successfully.",
            "deleted_count" => $deleteResult->getDeletedCount()
        ]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Error deleting electricians: " . $e->getMessage()]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid request parameters."]);
