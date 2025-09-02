<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1.'config_db/config.php';
require_once BASE_PATH_1.'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];

$return_response = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    

    $device_id = htmlspecialchars($_POST['D_ID']);

    // Check user permission from user_permissions collection
    $permissionDoc = $user_db_conn->user_permissions->findOne(
        ['login_id' => (int)$user_login_id],
        ['projection' => ['device_add_remove' => 1]]
    );
    $device_add_remove = (int)$permissionDoc['device_add_remove'] ?? 0;

    if ($device_add_remove != 1) {
        echo json_encode("No permission to Delete the device");
        exit();
    }

    // Delete device document from user_device_list collection
    $deleteResult = $user_db_conn->user_device_list->deleteOne([
        'device_id' => $device_id,
        'login_id' => (int)$user_login_id  // type cast if needed
    ]);

    if ($deleteResult->getDeletedCount() > 0) {
        $return_response = "Device deleted successfully";
    } else {
        $return_response = "Error: Device not found or could not be deleted";
    }

} else {
    $return_response = "Data not Available";
}

echo json_encode($return_response);
?>
