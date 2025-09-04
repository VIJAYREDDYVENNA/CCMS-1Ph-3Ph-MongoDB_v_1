<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

// Check session and retrieve session variables
SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();
$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];

$permission_check = 0;

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['DEVICES'])) {
    // Retrieve the devices JSON string
    $devices_json = filter_input(INPUT_POST, 'DEVICES', FILTER_SANITIZE_STRING);
    $installed_date = filter_input(INPUT_POST, 'ACTION_DATE', FILTER_SANITIZE_STRING);
    $installed_status = filter_input(INPUT_POST, 'STATUS', FILTER_SANITIZE_STRING);
    $devices_json = htmlspecialchars_decode($devices_json);

    // Decode the JSON string to a PHP array
    $devices = json_decode($devices_json, true);

    // Check if decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(['success' => false, 'message' => 'Invalid JSON format for devices.']);
    }

 

    // Check user permissions
    $user_permissions = $devices_db_conn->user_permissions->findOne(['login_id' => $user_login_id], ['projection' => ['installation_status_update' => 1]]);
    if ($user_permissions && $user_permissions['installation_status_update'] != 1) {
        sendResponse(['success' => false, 'message' => "This account doesn't have permission to update."]);
    }

    // Determine the update status based on the request
    $update_status = ($installed_status === "install") ? 1 : 0;

    // Sanitize the inputs
    $installed_date = sanitize_input($installed_date);
    $installed_status = sanitize_input($installed_status);
    $devices = sanitize_devices($devices);

    // MongoDB live_data_updates collection
    $live_data_updates_collection = $devices_db_conn->live_data_updates;

    // Iterate over the devices and perform the update/insert operation
    foreach ($devices as $device_id) {
        $update_result = $live_data_updates_collection->updateOne(
            ['device_id' => $device_id], // Filter
            ['$set' => ['installed_status' => $update_status, 'installed_date' => $installed_date]], // Update operation
            ['upsert' => true] // Create a new document if no match is found
        );

        if ($update_result->getMatchedCount() === 0 && $update_result->getUpsertedCount() === 0) {
            sendResponse(['success' => false, 'message' => 'Error updating device ID: ' . $device_id]);
        }
    }

    // Return a success message
    sendResponse(['success' => true, 'message' => 'Devices updated successfully.']);
} else {
    sendResponse(['success' => false, 'message' => 'Invalid request or missing data.']);
}

// Function to sanitize inputs
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to sanitize devices
function sanitize_devices($device_list) {
    $sanitizedDevices = [];
    foreach ($device_list as $device_id) {
        // Sanitize the device ID
        $sanitizedDevices[] = htmlspecialchars($device_id);
    }
    return $sanitizedDevices;
}

// Function to send response as JSON
function sendResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
