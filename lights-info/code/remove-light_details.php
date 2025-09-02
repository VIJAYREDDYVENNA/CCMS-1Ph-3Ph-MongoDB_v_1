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
$permission_check = 0;


$dtIST = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
$date_time = new MongoDB\BSON\UTCDateTime($dtIST->getTimestamp() * 1000);
// Check if the request is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    try {
          
        $device_id = trim($_POST['D_ID']);
        $record_id = trim($_POST['RECORD']);
        $total_lights = (int) trim($_POST['T_LIGHTS']);
        $total_wattage = (int) trim($_POST['T_WATTAGE']);
        
        // Validate ObjectId format for record_id
        if (!preg_match('/^[a-f\d]{24}$/i', $record_id)) {
            echo json_encode(["status" => "error", "message" => "Invalid record ID format"]);
            exit();
        }
        
        // Check user permissions from user database
        $userPermissionCollection = $user_db_conn->user_permissions;
        $permissionDoc = $userPermissionCollection->findOne(
            ['login_id' => (int)$user_login_id],
            ['projection' => ['lights_info_update' => 1]]
        );
        
        if (!$permissionDoc || !isset($permissionDoc['lights_info_update']) || (int)$permissionDoc['lights_info_update'] != 1) {
            echo json_encode(["status" => "error", "message" => "No permission to delete the device record"]);
            exit();
        }
        
        $permission_check = $permissionDoc['lights_info_update'];
        
        if ($permission_check == 1) {
            // Get collections from devices database
            $installedLightsCollection = $devices_db_conn->installed_lights_info;
            $liveDataUpdatesCollection = $devices_db_conn->live_data_updates;
            
            // First, get the current record to verify it exists and get current values
            $recordToDelete = $installedLightsCollection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($record_id),
                'device_id' => $device_id
            ]);
            
            if (!$recordToDelete) {
                echo json_encode(["status" => "error", "message" => "Record not found or device_id mismatch"]);
                exit();
            }
            
            // Get current live_data_updates record for this device
            $currentLiveData = $liveDataUpdatesCollection->findOne(
                ['device_id' => $device_id],
                ['projection' => ['lights_wattage' => 1, 'total_lights' => 1]]
            );
            
            // Calculate new values by subtracting the values to be deleted
            $current_total_lights = 0;
            $current_lights_wattage = 0;
            
            if ($currentLiveData) {
                $current_total_lights = isset($currentLiveData['total_lights']) ? (int) $currentLiveData['total_lights'] : 0;
                $current_lights_wattage = isset($currentLiveData['lights_wattage']) ? (int) $currentLiveData['lights_wattage'] : 0;
            }
            
            // Calculate new values after deletion (subtract the values being deleted)
            $new_total_lights = $current_total_lights - $total_lights;
            $new_lights_wattage = $current_lights_wattage - $total_wattage;
            
            // Ensure values don't go negative
            $new_total_lights = max(0, $new_total_lights);
            $new_lights_wattage = max(0, $new_lights_wattage);
            
            // Delete the record
            $deleteResult = $installedLightsCollection->deleteOne([
                '_id' => new MongoDB\BSON\ObjectId($record_id),
                'device_id' => $device_id
            ]);
            
            if ($deleteResult->getDeletedCount() > 0) {
                // Update the live_data_updates collection using updateOne
                $updateResult = $liveDataUpdatesCollection->updateOne(
                    ['device_id' => $device_id],
                    [
                        '$set' => [
                            'lights_wattage' => $new_lights_wattage,
                            'total_lights' => $new_total_lights,
                            'updated_at' => $date_time
                        ]
                    ],
                    ['upsert' => true]
                );
                
                if ($updateResult->getModifiedCount() > 0 || $updateResult->getUpsertedCount() > 0) {
                    echo json_encode(["status" => "success", "message" => "Lights details removed successfully."]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Error updating lights status"]);
                }
                
            } else {
                echo json_encode(["status" => "error", "message" => "Error removing record or record not found."]);
            }
        }
        
    } catch (MongoDB\Exception\Exception $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "An error occurred: " . $e->getMessage()]);
    }
    
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
?>