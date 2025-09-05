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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["device_ids"])) {
    try {
        // Check user permissions
        $user_permissions_collection = $user_db_conn->user_permissions;
        $permission_result = $user_permissions_collection->findOne(['login_id' => (int)$user_login_id]);
        
        if (!$permission_result || $permission_result['add_remove_electrician'] != 1) {
            echo json_encode(["status" => "error", "message" => "You do not have permission to Add / remove electricians and Devices."]);
            exit();
        }

        function sanitize_input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            return $data;
        }

        $electrician_name = sanitize_input($_POST['electrician_name']);
        $electrician_phone = sanitize_input($_POST['electrician_phone']);
        $group_id = sanitize_input($_POST['group_id']);
        $device_ids = array_map(function($id) {
            return sanitize_input($id);
        }, $_POST['device_ids']);

        if (empty($electrician_name) || empty($electrician_phone) || empty($device_ids)) {
            echo json_encode(["status" => "error", "message" => "All fields are required."]);
            exit;
        }

        // Check if electrician exists (both name and phone)
        $electricians_collection = $user_db_conn->electricians_list;
        $existing_electrician = $electricians_collection->findOne([
            'phone_number' => $electrician_phone,
            'name' => $electrician_name
        ]);

        if ($existing_electrician) {
            echo json_encode(["status" => "error", "message" => "Electrician already exists."]);
            exit;
        }

        // Check if phone number already exists
        $existing_phone = $electricians_collection->findOne(['phone_number' => $electrician_phone]);
        
        if ($existing_phone) {
            echo json_encode(["status" => "error", "message" => "Phone Number Already Exists."]);
            exit;
        }

        // Fetch group_area for the device
        $user_device_group_collection = $user_db_conn->user_device_group_view;
        $device_group_result = $user_device_group_collection->findOne([
            'device_id' => ['$in' => $device_ids]
        ]);

        $group_area = $device_group_result ? $device_group_result['device_group_or_area'] : null;

        // Insert into electricians_list
        $electrician_data = [
            
            'name' => $electrician_name,
            'phone_number' => $electrician_phone,
            'group_area' => $group_area,
            'user_login_id' => (int)$user_login_id
        ];

        $insert_result = $electricians_collection->insertOne($electrician_data);

        if (!$insert_result->getInsertedId()) {
            echo json_encode(["status" => "error", "message" => "Failed to add electrician."]);
            exit;
        }

        $electrician_id = $insert_result->getInsertedId();

        // Assign electrician to devices
        $electrician_devices_collection = $user_db_conn->electrician_devices;
        
        // Get next ID for electrician_devices
        $last_device_assignment = $electrician_devices_collection->findOne([], ['sort' => ['id' => -1]]);
        $next_device_id = $last_device_assignment ? $last_device_assignment['id'] + 1 : 1;
        
        $device_assignments = [];

        foreach ($device_ids as $device_id) {
            $device_assignments[] = [
                'id' => $next_device_id++,
                'device_id' => $device_id,
                'electrician_name' => $electrician_name,
                'phone_number' => $electrician_phone,
                'group_area' => $group_area,
                'user_login_id' => (int)$user_login_id
                
            ];
        }

        $device_insert_result = $electrician_devices_collection->insertMany($device_assignments);

        if ($device_insert_result->getInsertedCount() != count($device_ids)) {
            echo json_encode(["status" => "error", "message" => "Failed to assign electrician to some devices."]);
            exit;
        }

        echo json_encode(["status" => "success", "message" => "Electrician added successfully."]);

    } catch (MongoDB\Exception\ConnectionTimeoutException $e) {
        echo json_encode(["status" => "error", "message" => "Database connection timeout."]);
    } catch (MongoDB\Exception\RuntimeException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "An error occurred: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}
?>