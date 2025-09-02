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
$sanitizedparamters = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['D_ID']) && isset($_POST['parameters'])) {
    $D_ID = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);  
    $parameters = $_POST['parameters'];  

    try {
        // --- check permissions from MongoDB ---
        $user_permissions_coll = $user_db_conn->user_permissions;
        $permissionDoc = $user_permissions_coll->findOne(['login_id' => (int)$user_login_id], ['projection' => ['notification_update' => 1]]);
        
        if (!$permissionDoc || $permissionDoc['notification_update'] != 1) {
            $response['status'] = 'error';
            $response["message"] = "This account doesn't have permission to update.";
            sendResponse($response);
        }

        // --- sanitize parameters ---
        $sanitizedparamters = sanitize_perameters($parameters);

        // --- reference to devices collection ---
        $notification_coll = $devices_db_conn->notification_updates;

        $device_ids_array = explode(",", $D_ID);

        foreach ($device_ids_array as $device_id) {
            $device_id = strtoupper(trim($device_id));

            try {
                // Update if exists, Insert if not
                $updateResult = $notification_coll->updateOne(
                    ['device_id' => $device_id],
                    [
                        '$set' => array_merge(['device_id' => $device_id], $sanitizedparamters)
                    ],
                    ['upsert' => true] // <== handles both insert & update
                );

            } catch (Exception $e) {
                $response = [
                    'status' => 'error',
                    'message' => 'Something went wrong while updating device - ' . $device_id
                ];
                sendResponse($response);
            }
        }

        $response = ['status' => 'success', 'message' => 'Notification settings updated successfully.'];
        sendResponse($response);

    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        sendResponse($response);
    }
}

function sendResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

function sanitize_perameters($parameters) {
    $sanitizedParameters = [];
    $validFields = ['voltage', 'overload', 'power_fail', 'on_off', 'mcb_contactor_trip', 'door_alert'];    

    foreach ($parameters as $field => $value) {
        $field = htmlspecialchars(stripslashes($field));
        $value = htmlspecialchars(stripslashes($value));

        if (in_array($field, $validFields)) {
            // cast values properly (integers for flags, etc.)
            $sanitizedParameters[$field] = is_numeric($value) ? (int)$value : $value;
        }
    }
    return $sanitizedParameters;
}
?>
