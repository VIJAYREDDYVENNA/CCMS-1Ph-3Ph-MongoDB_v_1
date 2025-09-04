<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$send = [];  // Initialize response array

// MongoDB Connection (Already included in config.php)
$user_db_conn = $client->ccms_user_db;  // Assuming this is the MongoDB connection

// Check if client-id and client-name are set
if (isset($_POST['client-id']) && isset($_POST['client-name'])) {
    $client_id = sanitize_input($_POST['client-id']);
    $client_name = sanitize_input($_POST['client-name']);

    // Check if client_id and client_name are empty
    if (empty($client_id) || empty($client_name)) {
        $send["status"] = "error";
        $send["message"] = "Client ID and Name cannot be empty.";
    } else {
        // Check if client ID already exists in MongoDB
        $clientCollection = $user_db_conn->client_dashboard;
        $existingClient = $clientCollection->findOne([
            '$or' => [
                ['client_dashboard' => $client_id],
                ['client_identity_name' => $client_name]
            ]
        ]);

        if ($existingClient) {
            // If client already exists
            $send["status"] = "error";
            $send["message"] = "Client with this ID or Name already exists.";
        } else {
            // Insert the new client data
            $insertResult = $clientCollection->insertOne([
                'client_dashboard' => $client_id,
                'client_identity_name' => $client_name,
                'date_time' => new MongoDB\BSON\UTCDateTime()
            ]);

            if ($insertResult->getInsertedCount() > 0) {
                // If insertion is successful
                $send["status"] = "success";
                $send["message"] = "Client added successfully!";
            } else {
                // If insertion fails
                $send["status"] = "error";
                $send["message"] = "Failed to add client.";
            }
        }
    }
} else {
    $send["status"] = "error";
    $send["message"] = "Client ID and Name are required.";
}

// Send response as JSON
header('Content-Type: application/json');
echo json_encode($send);

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
