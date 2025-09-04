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

if (isset($_POST['ID']) && isset($_POST['COMPLAINT'])) {

    // Sanitize inputs
    $id = filter_input(INPUT_POST, 'ID', FILTER_SANITIZE_STRING);
    $new_complaint = filter_input(INPUT_POST, 'COMPLAINT', FILTER_SANITIZE_STRING);

    $response = "";

    $update_user = $mobile_no . " / " . $user_email . "/" . $user_name;

    date_default_timezone_set('Asia/Kolkata');
    $date = date("Y-m-d H:i:s");
    $complaint_no = date("YmdHis");

    // MongoDB collections
    $complaints = $devices_db_conn->complaints;
    $complaintsHistory = $devices_db_conn->complaints_history;

    try {
        // Insert into complaints collection
        $complaintData = [
            'device_id' => $id,
            'complaint_no' => $complaint_no,
            'complaint' => $new_complaint,
            'status' => 'OPEN',
            'registered_by' => $update_user,
            'registered_on' => $date,
            'closed_by' => '',
            'closed_on' => '',
            'estimated_date' => $date
        ];

        $complaints->insertOne($complaintData);

        // Insert into complaints_history collection
        $complaintHistoryData = [
            'complaint_no' => $complaint_no,
            'complaint_update' => $new_complaint,
            'updated_by' => $update_user,
            'updated_time' => $date
        ];

        $complaintsHistory->insertOne($complaintHistoryData);

        $response = "Successfully registered..!!";

    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage(), 0);
        $response = "Failed to process the request.";
    }

    echo $response;

} else {
    echo "Something went wrong. Please try again.";
}

?>
