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

$return_response = "";
$add_confirm = false;
$code = "";
$phase = "3PH";

$dtIST = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
$date_time = new MongoDB\BSON\UTCDateTime($dtIST->getTimestamp() * 1000);
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    try {
        // Get MongoDB connections (assuming these are available from config.php)
        // $client, $user_db_conn, $devices_db_conn should be available from config
        
        // Get and sanitize POST parameters
        $device_id = htmlspecialchars(trim($_POST['deviceId']));
        $device_name = htmlspecialchars(trim($_POST['deviceName']));
        
        // Check if device name is empty
        if (empty($device_name)) {
            echo json_encode("Please enter Device Name");
            exit();
        }
        
        // Get collections
        $userDeviceListCollection = $user_db_conn->user_device_list;
        $deviceNameUpdateLogCollection = $devices_db_conn->device_name_update_log;
        
        // Check if device name already exists based on role
        if ($role === "SUPERADMIN") {
            // For SUPERADMIN, check only s_device_name
            $existingDevice = $userDeviceListCollection->findOne([
                's_device_name' => $device_name
            ]);
        } else {
            // For non-SUPERADMIN roles, check both s_device_name and c_device_name
            $existingDevice = $userDeviceListCollection->findOne([
                '$or' => [
                    ['s_device_name' => $device_name],
                    ['c_device_name' => $device_name]
                ]
            ]);
        }
        
        // If device name doesn't exist, proceed with update
        if (!$existingDevice) {
            
            // Prepare update data based on role
            if ($role === "SUPERADMIN") {
                // For SUPERADMIN, update only s_device_name
                $updateData = [
                    '$set' => [
                        's_device_name' => $device_name,
                        'updated_at' => $date_time
                    ]
                ];
            } else {
                // For non-SUPERADMIN roles, update both c_device_name and s_device_name
                $updateData = [
                    '$set' => [
                        'c_device_name' => $device_name,
                        's_device_name' => $device_name,
                        'updated_at' => $date_time
                    ]
                ];
            }
            
            // Update the device name in user_device_list collection
            $updateResult = $userDeviceListCollection->updateOne(
                ['device_id' => $device_id],
                $updateData
            );
            
            if ($updateResult->getModifiedCount() > 0) {
                // Successfully updated, now insert into device_name_update_log
                $current_date_time = $date_time;
                
                $logData = [
                    'device_id' => $device_id,
                    'user_alternative_name' => $device_name,
                    'date_time' => $current_date_time,
                    'updated_by' => $user_login_id,
                    'role' => $role,
                    'created_at' => $current_date_time
                ];
                
                $logResult = $deviceNameUpdateLogCollection->insertOne($logData);
                
                if ($logResult->getInsertedId()) {
                    // Successfully updated and logged
                    echo json_encode(['status' => 'success', 'message' => 'Device name updated and log added successfully.']);
                } else {
                    // Logging failed
                    echo json_encode(['status' => 'error', 'message' => 'Device name updated, but failed to log the change.']);
                }
                
            } else {
                // If the update failed (no document was modified)
                echo json_encode(['status' => 'error', 'message' => 'Failed to update the device name or device not found.']);
            }
            
        } else {
            // If the device name already exists
            echo json_encode(['status' => 'warning', 'message' => 'Device name already exists!']);
        }
        
    } catch (MongoDB\Exception\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
    
} else {
    $return_response = "Data not available";
    echo json_encode(['status' => 'error', 'message' => $return_response]);
}
?>