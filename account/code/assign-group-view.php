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
$client_dashboard_login  = $sessionVars['client'];
$dashboard_version = $sessionVars['client_login'];

$permission_check = 0;


$userPermissionsCollection = $user_db_conn->user_permissions;
$deviceSelectionGroupCollection = $user_db_conn->device_selection_group;

$response = ["status" => "", "message" => ""];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $group = trim(filter_input(INPUT_POST, 'group', FILTER_SANITIZE_STRING));
    $userId = trim(filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_STRING));

    if (empty($group)) {
        $response['status'] = 'error';
        $response['message'] = "Group is empty.";
        sendResponse($response);
    }

    if (empty($userId)) {
        $response['status'] = 'error';
        $response['message'] = "User_ID is required.";
        sendResponse($response);
    }

    // Check user permission: user_details_updates == 1
    $permissionCheckDoc = $userPermissionsCollection->findOne(['login_id' => (int)$user_login_id]);
    $permission_check = $permissionCheckDoc ? (int)($permissionCheckDoc['user_details_updates'] ?? 0) : 0;

    if ($permission_check != 1) {
        $response['status'] = 'error';
        $response["message"] = "This account doesn't have permission to update.";
        sendResponse($response);
    }



    // Sanitize inputs
    $group = sanitize_input($group);
    $userId = sanitize_input($userId);

    $validGroups = ["device_group_or_area", "city_or_town", "district", "state"];
    if (!in_array($group, $validGroups)) {
        $response['status'] = 'error';
        $response["message"] = "Selected group is Invalid";
        sendResponse($response);
    }

    if (in_array($group, ["device_group_or_area", "city_or_town", "district", "state"])) {
        // Fetch user's allowed group_by from device_selection_group collection
        $validateByUserGroupDoc = $deviceSelectionGroupCollection->findOne(['login_id' => (int)$user_login_id]);
        $validate_by_user_group = $validateByUserGroupDoc['group_by'] ?? 'device_group_or_area';

        if ($role !== "SUPERADMIN") {
            if ($validate_by_user_group === "device_group_or_area") {
                if (in_array($group, ["city_or_town", "district", "state"])) {
                    $response['status'] = 'error';
                    $response["message"] = "Not allowed to assign this group";
                    sendResponse($response);
                }
            } elseif ($validate_by_user_group === "city_or_town") {
                if (in_array($group, ["district", "state"])) {
                    $response['status'] = 'error';
                    $response["message"] = "Not allowed to assign this group";
                    sendResponse($response);
                }
            } elseif ($validate_by_user_group === "district") {
                if ($group === "state") {
                    $response['status'] = 'error';
                    $response["message"] = "Not allowed to assign this group";
                    sendResponse($response);
                }
            }
        }
    }

    
    $updateResult = $deviceSelectionGroupCollection->updateOne(
        ['login_id' => (int)$userId],
        ['$set' => ['group_by' => $group]],
        ['upsert' => true]
    );

    if ($updateResult->getModifiedCount() > 0 || $updateResult->getUpsertedCount() > 0) {
        $response['status'] = 'success';
        $response['message'] = "Device group saved successfully.";
    } else {
        // No modification or new insertion made
        $response['status'] = 'error';
        $response['message'] = "Error saving device group.";
    }

    sendResponse($response);
}

function sendResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
