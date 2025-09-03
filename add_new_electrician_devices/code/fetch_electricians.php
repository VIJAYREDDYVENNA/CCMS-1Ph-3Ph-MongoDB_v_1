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
$user_devices = "";
header('Content-Type: application/json');

// Include the device selection logic (MongoDB-based)
include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");

// Cleanup trailing comma
if ($user_devices != "") {
    $user_devices = rtrim($user_devices, ",");
}

$electricians = [];
$temp_data = [];

// MongoDB connection
// $client = new MongoDB\Client("mongodb://localhost:27017");
$user_db_conn = $client->ccms_user_db;

// Convert $user_devices string to array
$device_ids = array_map(function($id) {
    return trim($id, "'");
}, explode(",", $user_devices));

// 1. Fetch electricians from electrician_devices using device_ids
if (!empty($device_ids)) {
    $cursor = $user_db_conn->electrician_devices->distinct('phone_number', [
        'device_id' => ['$in' => $device_ids]
    ]);

    foreach ($cursor as $phone) {
        $doc = $user_db_conn->electrician_devices->findOne(['phone_number' => $phone]);
        if ($doc) {
            $temp_data[] = [
                'name' => $doc['electrician_name'],
                'phone' => $doc['phone_number']
            ];
        }
    }
}

$fetched_phone_numbers = []; // to track already fetched

// 2. If temp_data empty, fallback to electricians_list using login_id
if (empty($temp_data)) {
    $cursor = $user_db_conn->electricians_list->find(['user_login_id' => (int)$user_login_id]);
    foreach ($cursor as $doc) {
        $electricians[] = [
            'id' => (string)$doc['_id'],
            'name' => $doc['name'],
            'phone' => $doc['phone_number']
        ];
        $fetched_phone_numbers[] = $doc['phone_number'];
    }
} else {
    // 3. Get electrician IDs from electricians_list based on name & phone
    foreach ($temp_data as $data) {
        $doc = $user_db_conn->electricians_list->findOne([
            'name' => $data['name'],
            'phone_number' => $data['phone']
        ]);
        if ($doc) {
            $electricians[] = [
                'id' => (string)$doc['_id'],
                'name' => $doc['name'],
                'phone' => $doc['phone_number']
            ];
            $fetched_phone_numbers[] = $doc['phone_number'];
        }
    }

    // 4. Fetch remaining electricians not already included
    $filter = ['user_login_id' => (int)$user_login_id];
    if (!empty($fetched_phone_numbers)) {
        $filter['phone_number'] = ['$nin' => $fetched_phone_numbers];
    }
    $cursor = $user_db_conn->electricians_list->find($filter);
    foreach ($cursor as $doc) {
        $electricians[] = [
            'id' => (string)$doc['_id'],
            'name' => $doc['name'],
            'phone' => $doc['phone_number']
        ];
    }
}

echo json_encode($electricians);
