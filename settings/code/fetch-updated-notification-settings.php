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
$user_email = $sessionVars['user_email'];
$permission_check = 0;

$normal='class=""';
$red='class="text-danger-emphasis fw-bold"'; 
$orange='class="text-warning-emphasis fw-bold"'; 
$green='class="text-success-emphasis fw-bold"';  
$primary='class="text-info-emphasis fw-bold"'; 
$class=$normal;
$data = "";

$response = ["status" => "error", "message" => ""];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['D_ID'])) {
    $device_ids = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);

    try {
        $device_ids = sanitize_input($device_ids);
        
        // Get the collection name based on device_ids
        $collection_name ='notification_updates';
        
        // Access the collection from the ccms_data database
        $collection = $devices_db_conn->selectCollection($collection_name);
        
        // Filter for specific device_id and get the most recent document
        $filter = ['device_id' => $device_ids];
        
        $options = [
            'limit' => 1,
            'sort' => ['date_time' => -1], 
            'projection' => [
                'voltage' => 1,
                'overload' => 1,
                'power_fail' => 1,
                'on_off' => 1,
                'mcb_contactor_trip' => 1,
                'door_alert' => 1,
                'date_time' => 1,
                'user_mobile' => 1,
                'email' => 1,
                'name' => 1,
                'role' => 1,
                '_id' => 0 // Exclude _id from results
            ]
        ];
        
        $cursor = $collection->find($filter, $options);
        $document = $cursor->toArray();
        
        if (!empty($document)) {
            $r = $document[0];
            $response = ["status" => "success", "data" => [
                "voltage" => $r['voltage'] ?? 0,
                "overload" => $r['overload'] ?? 0,
                "power_fail" => $r['power_fail'] ?? 0,
                "on_off" => $r['on_off'] ?? 0,
                "mcb_contactor_trip" => $r['mcb_contactor_trip'] ?? 0,
                "door_alert" => $r['door_alert'] ?? 0,
                "date_time" => $r['date_time'] ?? null,
                "user_mobile" => $r['user_mobile'] ?? '--',
                "email" => $r['email'] ?? '--',
                "name" => $r['name'] ?? '--',
                "role" => $r['role'] ?? '--'
            ]];
        } else {
            $response = ["status" => "error", "message" => "Records not found.."];
        }
        
    } catch (MongoDB\Exception\Exception $e) {
        $response = ["status" => "error", "message" => "Database error: " . $e->getMessage()];
    } catch (Exception $e) {
        $response = ["status" => "error", "message" => "Something went wrong: " . $e->getMessage()];
    }

    echo json_encode($response);
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}
?>