<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';
SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

use MongoDB\BSON\ObjectId;

$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];
$user_name = $sessionVars['user_name'];
$user_email = $sessionVars['user_email'];

$response = ["status" => "error", "message" => "", "group_list" => ""];

// Permission check
$permissionDoc = $user_db_conn->user_permissions->findOne(
    ["login_id" => (int)$user_login_id],
    ["projection" => ["create_group" => 1]]
);
if (!$permissionDoc || (int)$permissionDoc["create_group"] !== 1) {
    $response["message"] = "No Permission to add/change the device(s) Group";
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $device_ids = sanitize_input($_POST['D_ID']);
    $group = sanitize_input($_POST['GROUP']);
    $new_group = sanitize_input($_POST['NEW_GROUP']);
    $city_or_town = sanitize_input($_POST['TOWN']);
    $district = sanitize_input($_POST['DISTRICT']);
    $state = sanitize_input($_POST['STATE']);

    $device_ids_array = explode(",", $device_ids);
    foreach ($device_ids_array as $device_id) {
        $device_id = trim(strtolower($device_id));
        if (!preg_match('/^[a-z0-9_]+$/', $device_id)) {
            $response["message"] = $device_id." Invalid device(s) ID";
            echo json_encode($response);
            exit();
        }
    }

    $user_activity = "";

    switch ($group) {
        case 'EXISTING':
            // Fetch group details by login_id and new_group (s_id), similar to MySQL
        $details = $user_db_conn->device_list_by_group->findOne([
            "login_id" => (int)$user_login_id,
            "s_id" =>  new ObjectId($new_group)
        ], [
            "projection" => ["state"=>1,"district"=>1,"city_or_town"=>1,"device_group_or_area"=>1]
        ]);

        if (!$details) {
            $response["message"] = "Group details are not available.";
            echo json_encode($response);
            exit();
        }
        $state         = $details["state"];
        $district      = $details["district"];
        $city_or_town  = $details["city_or_town"];
        $group_name    = $details["device_group_or_area"];

        $user_activity = "Added the device(s) to the Group";


            // Upsert all selected device_ids into devices_group
        foreach ($device_ids_array as $device_id) {
            $user_db_conn->devices_group->updateOne(
                ['device_id' => $device_id],
                ['$set' => [
                    "state" => $state,
                    "district" => $district,
                    "city_or_town" => $city_or_town,
                    "device_group_or_area" => $group_name
                ]],
                ['upsert' => true]
            );
        }
        $response["status"] = "success";
        $response["message"] = $user_activity . " successfully";
        break;

        case 'CREATE_NEW':
            // Ensure group does not exist in device_list_by_group
        $exists = $user_db_conn->device_list_by_group->findOne([
            "device_group_or_area" => strtoupper($new_group)
        ]);
        if ($exists) {
            $response["message"] = "The group/area already exists. Please enter another name.";
            echo json_encode($response);
            exit();
        }

        $user_activity = "Created New Group/area and added the device(s) to the Group";

        $state = strtoupper($state);
        $district = strtoupper($district);
        $city_or_town = strtoupper($city_or_town);
        $new_group = strtoupper($new_group);

        foreach ($device_ids_array as $device_id) {
            $user_db_conn->devices_group->updateOne(
                ['device_id' => $device_id],
                ['$set' => [
                    "state" => $state,
                    "district" => $district,
                    "city_or_town" => $city_or_town,
                    "device_group_or_area" => $new_group
                ]],
                ['upsert' => true]
            );
        }
        $response["status"] = "success";
        $response["message"] = $user_activity . " successfully";


        break;

        default:
        $response["message"] = "Something went wrong..";
        echo json_encode($response);
        exit();
    }



    // Fetch group_by field for the user from device_selection_group
    $group_by_doc = $user_db_conn->device_selection_group->findOne([
        "login_id" => (int)$user_login_id
    ], [
        "projection" => ["group_by" => 1]
    ]);
    $group_by_column = "device_group_or_area";
    if ($group_by_doc && isset($group_by_doc["group_by"])) {
        $group_by_column = $group_by_doc["group_by"];
    }
    
    
    /////////////////////////////////////////////////////////////////////
    $group_by_column = "device_group_or_area";
    $groups = $user_db_conn->device_list_by_group->distinct(
        $group_by_column,
        ['login_id' => (int)$user_login_id]
    );

    $group_list = [];
    foreach ($groups as $g) {
        $group_list[] = ["GROUP" => strtoupper($g)];
    }
    /////////////////////////////////////////////////////////////////////



    $_SESSION["GROUP_LIST"] = json_encode($group_list);
    $response["group_list"] = $group_list;



    // Log activity
    $dtIST = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
    $date_time = new MongoDB\BSON\UTCDateTime($dtIST->getTimestamp() * 1000);

    $user_db_conn->user_activity_log->insertOne([
        "date_time"     => $date_time,
        "updated_field" => $user_activity,
        "device_meta"   => [
            "user_mobile" => $mobile_no,
            "email"       => $user_email,
            "name"        => $user_name,
            "role"        => $role
        ]
    ]);
    echo json_encode($response);
    exit();
} else {
    $response["message"] = "Invalid request method.";
    echo json_encode($response);
    exit();
}

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>
