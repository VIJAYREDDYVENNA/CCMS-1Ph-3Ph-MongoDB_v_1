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

// Function to validate ObjectId
function isValidObjectId($id) {
    try {
        new ObjectId($id);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// SINGLE DELETE
if (isset($_POST["electrician_id"])) {
    try {
        $id = trim($_POST["electrician_id"]);
        
        // Validate ObjectId
        if (!isValidObjectId($id)) {
            echo json_encode(["status" => "error", "message" => "Invalid electrician ID format."]);
            exit;
        }
        
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
        $ids_input = $_POST["electrician_ids"];
        
        // Handle both JSON string and direct array
        if (is_string($ids_input)) {
            $ids = json_decode($ids_input, true);
            
            // Check if JSON decode was successful
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(["status" => "error", "message" => "Invalid JSON format: " . json_last_error_msg()]);
                exit;
            }
        } elseif (is_array($ids_input)) {
            $ids = $ids_input;
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid data format for electrician IDs."]);
            exit;
        }
        
        if (!is_array($ids) || empty($ids)) {
            echo json_encode(["status" => "error", "message" => "No valid IDs provided."]);
            exit;
        }

        // Debug: Log the received IDs
        error_log("Received IDs: " . print_r($ids, true));

        // Validate and filter valid ObjectIds
        $validIds = [];
        $invalidIds = [];
        
        foreach ($ids as $id) {
            // Handle both string IDs and object format {id: "..."}
            if (is_array($id) && isset($id['id'])) {
                $id = $id['id'];
            } elseif (is_object($id) && isset($id->id)) {
                $id = $id->id;
            }
            
            $id = trim((string)$id);
            
            // Debug: Log each ID being processed
            error_log("Processing ID: " . $id);
            
            if (isValidObjectId($id)) {
                $validIds[] = $id;
            } else {
                $invalidIds[] = $id;
                error_log("Invalid ObjectId: " . $id);
            }
        }
        
        if (empty($validIds)) {
            echo json_encode([
                "status" => "error", 
                "message" => "No valid ObjectIds provided.",
                "invalid_ids" => $invalidIds,
                "debug_info" => "Received " . count($ids) . " IDs, none were valid"
            ]);
            exit;
        }
        
        // Convert to ObjectId array
        $objectIds = [];
        foreach ($validIds as $id) {
            try {
                $objectIds[] = new ObjectId($id);
            } catch (Exception $e) {
                error_log("Failed to create ObjectId for: " . $id . " - " . $e->getMessage());
                $invalidIds[] = $id;
            }
        }
        
        if (empty($objectIds)) {
            echo json_encode([
                "status" => "error", 
                "message" => "Failed to create valid ObjectIds.",
                "invalid_ids" => $invalidIds
            ]);
            exit;
        }

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
            $deleteResult = $electriciansListColl->deleteMany(['_id' => ['$in' => $objectIds]]);

            // Delete from electrician_devices
            foreach ($electricianData as $item) {
                $electricianDevicesColl->deleteMany([
                    'electrician_name' => $item['name'],
                    'phone_number' => $item['phone']
                ]);
            }
            
            $message = "Selected electricians removed successfully.";
            if (!empty($invalidIds)) {
                $message .= " Note: " . count($invalidIds) . " invalid ID(s) were skipped.";
            }
            
            echo json_encode([
                "status" => "success", 
                "message" => $message,
                "deleted_count" => $deleteResult->getDeletedCount(),
                "processed_count" => count($validIds),
                "invalid_count" => count($invalidIds)
            ]);
        } else {
            echo json_encode([
                "status" => "error", 
                "message" => "No electricians found with the provided IDs.",
                "searched_ids" => count($objectIds),
                "valid_ids" => $validIds
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            "status" => "error", 
            "message" => "Error: " . $e->getMessage(),
            "line" => $e->getLine(),
            "file" => $e->getFile()
        ]);
    }
}

// INVALID REQUEST
else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}
?>