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
$client_dashboard_login = $sessionVars['client'];
$dashboard_version = $sessionVars['client_login'];

$response = ["status" => "", "message" => ""];



if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $updateType = $_POST['UPDATE'] ?? '';

    // EDIT USER DETAILS
    if ($updateType === "EDIT") {
        $userName = trim(filter_input(INPUT_POST, 'USERNAME', FILTER_SANITIZE_STRING));
        $userId = trim(filter_input(INPUT_POST, 'USERID', FILTER_SANITIZE_STRING));
        $userRole = trim(filter_input(INPUT_POST, 'USERROLE', FILTER_SANITIZE_STRING));
        $userEmail = trim(filter_input(INPUT_POST, 'USEREMAIL', FILTER_SANITIZE_EMAIL));
        $userMobile = trim(filter_input(INPUT_POST, 'USERMOBILE', FILTER_SANITIZE_NUMBER_INT));
        $id = trim(filter_input(INPUT_POST, 'ID', FILTER_SANITIZE_NUMBER_INT));

        // Validate inputs
        if (!$userName || !$userId || !$userRole || !$userEmail || !$userMobile) {
            $response['status'] = "error";
            $response['message'] = "All fields are required.";
            sendResponse($response);
        }
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $response['status'] = "error";
            $response['message'] = "Invalid email format.";
            sendResponse($response);
        }
        if (strlen($userMobile) != 10 || !ctype_digit($userMobile)) {
            $response['status'] = "error";
            $response['message'] = "Please enter a valid 10-digit mobile number.";
            sendResponse($response);
        }
        if (!is_numeric($id)) {
            $response['status'] = "error";
            $response['message'] = "Invalid user ID.";
            sendResponse($response);
        }

        // Check user permission
        $permDoc = $user_db_conn->user_permissions->findOne(['login_id' => (int)$user_login_id], ['projection' => ['user_details_updates' => 1]]);
        if (empty($permDoc) || intval((int)$permDoc['user_details_updates']) !== 1) {
            $response['status'] = "error";
            $response['message'] = "This account doesn't have permission to update.";
            sendResponse($response);
        }

        // Check uniqueness of email, mobile, and user_id excluding current user id
        $count = $user_db_conn->login_details->countDocuments([
            '$and' => [
                ['id' => ['$ne' =>(int)$id]],
                ['$or' => [
                    ['email_id' => $userEmail],
                    ['mobile_no' => $userMobile],
                    ['user_id' => $userId]
                ]]
            ]
        ]);


        if ($count > 0) {
            $response['status'] = "error";
            $response['message'] = "Email, mobile number, or user ID already exists.";
            sendResponse($response);
        }

        // Get current role and status to handle account confirmation logic
        $currentUser = $user_db_conn->login_details->findOne(['id' => (int)$id], ['projection' => ['role' => 1, 'status' => 1]]);
        $prev_role = $currentUser['role'] ?? '';
        $prev_status = $currentUser['status'] ?? '';

        $account_confirmation = "ACTIVE";
        $msg = "User details updated successfully.";

        if ($prev_status === "HOLD") {
            $account_confirmation = "HOLD";
            if ($userRole === "SUPERADMIN" && $prev_role !== "SUPERADMIN") {
                $msg = "User details updated successfully, but the account is currently on hold. Please contact ISTL for activation.";
            }
        } else {
            if ($userRole === "SUPERADMIN" && $prev_role !== "SUPERADMIN") {
                $account_confirmation = "HOLD";
                $msg = "The user details have been successfully updated; however, the account is now on hold because a role cannot be upgraded to SUPERADMIN. Please contact ISTL for reactivation.";
            }
        }

        /*$response['status'] = "error";
        $response['message'] = "Error updating record or no changes made -". ;
        sendResponse($response);*/

        // Update user document
        $updateResult = $user_db_conn->login_details->updateOne(
            ['id' => (int)$id],
            ['$set' => [
                'name' => $userName,
                'user_id' => $userId,
                'role' => $userRole,
                'status' => $account_confirmation,
                'email_id' => $userEmail,
                'mobile_no' => $userMobile
            ]]
        );

        if ($updateResult->getModifiedCount() > 0) {
            $response['status'] = "success";
            $response['message'] = $msg;
        } else {
            $response['status'] = "error";
            $response['message'] = "Error updating record or no changes made.";
        }

        sendResponse($response);
    }
    // DELETE USER
    else if ($updateType === "DELETE") {
        $userId = trim(filter_input(INPUT_POST, 'USERID', FILTER_SANITIZE_STRING));
        $userMobile = trim(filter_input(INPUT_POST, 'USERMOBILE', FILTER_SANITIZE_NUMBER_INT));

        if (!$userId || !$userMobile) {
            $response['status'] = 'error';
            $response['message'] = 'User ID and Mobile number are required.';
            sendResponse($response);
        }

        // Check permission
        $permDoc = $user_db_conn->user_permissions->findOne(['login_id' => (int)$user_login_id], ['projection' => ['user_details_updates' => 1]]);
        if (empty($permDoc) || intval((int)$permDoc['user_details_updates']) !== 1) {
            $response['status'] = 'error';
            $response['message'] = "This account doesn't have permission to update.";
            sendResponse($response);
        }

        // Mark account as deleted (account_delete = '0')
        $updateResult = $user_db_conn->login_details->updateOne(
            ['id' => (int)$userId, 'mobile_no' => $userMobile],
            ['$set' => ['account_delete' => 0]]
        );

        if ($updateResult->getModifiedCount() > 0) {
            $response['status'] = 'success';
            $response['message'] = "Account deleted successfully.";
        } else {
            $response['status'] = 'error';
            $response['message'] = "Error updating record or account not found.";
        }

        sendResponse($response);
    }
    // CREATE NEW USER
    else if ($updateType === "NEW_USER") {
        $userName = trim(filter_input(INPUT_POST, 'USERNAME', FILTER_SANITIZE_STRING));
        $userId = trim(filter_input(INPUT_POST, 'USERID', FILTER_SANITIZE_STRING));
        $userRole = trim(filter_input(INPUT_POST, 'USERROLE', FILTER_SANITIZE_STRING));
        $userEmail = trim(filter_input(INPUT_POST, 'USEREMAIL', FILTER_SANITIZE_EMAIL));
        $userMobile = trim(filter_input(INPUT_POST, 'USERMOBILE', FILTER_SANITIZE_NUMBER_INT));
        $login_page = trim(filter_input(INPUT_POST, 'LOGIN_PAGE', FILTER_SANITIZE_STRING));
        $newPassword = trim(filter_input(INPUT_POST, 'PASSWORD', FILTER_SANITIZE_STRING));
        $reenterPassword = trim(filter_input(INPUT_POST, 'REENTERPASSWORD', FILTER_SANITIZE_STRING));

        // Validation
        if (!$userName || !$userId || !$userRole || !$userEmail || !$userMobile) {
            $response['status'] = 'error';
            $response['message'] = "All fields are required.";
            sendResponse($response);
        }
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $response['status'] = 'error';
            $response['message'] = "Invalid email format.";
            sendResponse($response);
        }
        if (strlen($userMobile) !== 10 || !ctype_digit($userMobile)) {
            $response['status'] = 'error';
            $response['message'] = "Please enter a valid 10-digit mobile number.";
            sendResponse($response);
        }
        if (!$newPassword || !$reenterPassword) {
            $response['status'] = 'error';
            $response['message'] = "Both password fields are required.";
            sendResponse($response);
        }
        if (strlen($newPassword) < 8) {
            $response['status'] = 'error';
            $response['message'] = "Password must be at least 8 characters.";
            sendResponse($response);
        }
        if ($newPassword !== $reenterPassword) {
            $response['status'] = 'error';
            $response['message'] = "Passwords do not match.";
            sendResponse($response);
        }

        // Check permissions
        $permDoc = $user_db_conn->user_permissions->findOne(['login_id' => (int)$user_login_id], ['projection' => ['user_details_updates' => 1]]);
        if (empty($permDoc) || intval((int)$permDoc['user_details_updates']) !== 1) {
            $response['status'] = 'error';
            $response['message'] = "This account doesn't have permission to update.";
            sendResponse($response);
        }

        // Check uniqueness
        $count = $user_db_conn->login_details->countDocuments([
            '$or' => [
                ['email_id' => (string)$userEmail],
                ['mobile_no' => $userMobile],
                ['user_id' => (string)$userId],
            ]
        ]);

        if ($count > 0) {
            $response['status'] = 'error';
            $response['message'] = "Email, mobile number, or user ID already exists.";
            sendResponse($response);
        }

        if ($login_page === "USER") {
            $login_page = $client_dashboard_login;
        }
        $login_page = strtolower($login_page);



        $account_confirmation = $userRole === "SUPERADMIN" ? "HOLD" : "ACTIVE";

        // Hash password securely
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);


        $nextId = getNextSequence($user_db_conn->id_counters, "auto_id_save");

        $dtIST = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
        $date_time = new MongoDB\BSON\UTCDateTime($dtIST->getTimestamp() * 1000);

        $insertResult = $user_db_conn->login_details->insertOne([
            'id' => (int)$nextId,
            'user_id' => $userId,
            'mobile_no' => $userMobile,
            'email_id' => $userEmail,
            'password' => $hashedPassword,
            'name' => $userName,
            'role' => $userRole,
            'user_type'  => "--",
            'status' => $account_confirmation,
            'client' => $login_page,
            'client_login' => $dashboard_version,
            'account_delete' => 1,
            'created_by' => (int)$user_login_id,
            'created_date' => $date_time,

        ]);

        

        if ($insertResult->getInsertedId()) {
            // Insert default menu_permissions_list for new user by login_id



         $defaultPermissions = [
            'device_dashboard' => 1,
            'dashboard' => 1,
            'devices_list' => 1,
            'onoff_control' => 0,
            'gis_map' => 1,
            'data_report' => 1,
            'energy_consumption' => 0,
            'thresholdsettings' => 0,
            'group_creation' => 0,
            'location_update' => 0,
            'notification_settings' => 0,
            'iotsettings' => 0,
            'pending_actions' => 0,
            'add_new_electrician_devices' => 0,
            'phase_alerts' => 1,
            'alerts' => 1,
            'notification_mesages' => 1,
            'graphs' => 0,
            'up_down_time' => 0,
            'glowing_time' => 0,
            'user_activity' => 0,
            'download' => 0,
            'complaints' => 0,
            'office_use' => 0,
            'users_list' => 0,
        ];


        //$user_db_conn->menu_permissions_list->insertOne(['login_id' => (int)$nextId], $defaultPermissions);
        $user_db_conn->menu_permissions_list->updateOne(
            ['login_id' => (int)$nextId],          
            ['$set' => $defaultPermissions],        
            ['upsert' => true]                      
        );

        $response['status'] = 'success';
        $response['message'] = "Account created successfully.";
    } else {
        $response['status'] = 'error';
        $response['message'] = "Error inserting record.";
    }

    sendResponse($response);
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid UPDATE operation.';
    sendResponse($response);
}
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method.';
    sendResponse($response);
}

function sendResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

function getNextSequence($counters, $name) {
    $result = $counters->findOneAndUpdate(
        ['_id' => $name],
        ['$inc' => ['seq' => 1]],
        [
            'returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            'upsert' => true
        ]
    );
    return $result['seq'];
}

?>
