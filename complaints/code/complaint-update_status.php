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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ID']) && isset($_POST['COMPLAINT'])) {

    // Sanitize inputs
    $complaint_no = filter_input(INPUT_POST, 'ID', FILTER_SANITIZE_STRING);
    $complaint_update = filter_input(INPUT_POST, 'COMPLAINT', FILTER_SANITIZE_STRING);
    $close_status = filter_input(INPUT_POST, 'CLOSE', FILTER_SANITIZE_STRING);
    $response = "";

    $update_user = $mobile_no . " / " . $user_email . "/" . $user_name;

    date_default_timezone_set('Asia/Kolkata');
    $date = date("Y-m-d H:i:s");

    // MongoDB collection
    $complaintsHistory = $devices_db_conn->complaints_history;
    $complaints = $devices_db_conn->complaints;

    try {
        // Insert complaint update into complaints_history collection
        $complaintHistoryData = [
            'complaint_no' => $complaint_no,
            'complaint_update' => $complaint_update,
            'updated_by' => $update_user,
            'updated_time' => $date
        ];

        $complaintsHistory->insertOne($complaintHistoryData);

        if ($close_status === "CLOSE") {
            // Update the complaint status to CLOSED
            $updateResult = $complaints->updateOne(
                ['complaint_no' => $complaint_no],
                ['$set' => ['status' => 'CLOSED', 'closed_by' => $update_user, 'closed_on' => $date]]
            );

            if ($updateResult->getModifiedCount() > 0) {
                $response = "Complaint closed successfully.";
            } else {
                $response = "Failed to close the complaint.";
            }
        } else {
            // Update the complaint status to PROGRESS
            $updateResult = $complaints->updateOne(
                ['complaint_no' => $complaint_no],
                ['$set' => ['status' => 'PROGRESS']]
            );

            if ($updateResult->getModifiedCount() > 0) {
                $response = "Complaint updated successfully.";
            } else {
                $response = "Failed to update complaint status.";
            }
        }

    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage(), 0);
        $response = "Failed to process the request.";
    }

    echo $response;

} else {
    echo "Something went wrong. Please try again.";
}

?>
