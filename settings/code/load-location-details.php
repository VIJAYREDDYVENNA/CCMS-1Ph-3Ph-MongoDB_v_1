<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();

$return_response = [
    'success' => false,
    'message' => '',
    'data' => [
        'latitude' => 0, 
        'longitude' => 0,
        'update_status' => 0,
        'street' => "--",
        'town' => "--",
        'city' => "--",
        'district' => "--",
        'state' => "--",
        'pincode' => "--",
        'country' => "--",
        'landmark' => "--",
    ]
];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['D_ID'])) {
    $device_id = strtoupper(trim($_POST['D_ID']));
/*$device_id="SM1PH_3";*/
    // Assume $devices_db_conn is an initialized MongoDB database connection
    // e.g., $devices_db_conn = $manager->ccms_data;

    // Helper to fetch latest document fields for device from a collection
    function fetchLatestDocumentFields($collectionName, $device_id, $fieldKeys) {
        global $devices_db_conn;
        $doc = $devices_db_conn->$collectionName->findOne(
            ['device_id' => $device_id],
            [
                'sort' => ['date_time' => -1],
                'projection' => array_fill_keys($fieldKeys, 1)
            ]
        );
        if ($doc === null) {
            return null;
        }
        $result = [];
        foreach ($fieldKeys as $key) {
            if (isset($doc[$key])) {
                $result[$key] = $doc[$key];
            }
        }
        return $result;
    }

    // Fetch coordinates_list fields
    $coordsFields = ['latitude', 'longitude', 'update_status'];
    $coordsData = fetchLatestDocumentFields('coordinates_list', $device_id, $coordsFields);
    if ($coordsData !== null) {
        $return_response['data'] = array_merge($return_response['data'], $coordsData);
    } else {
        $return_response['message'] = "No coordinate records found.";
    }

    // Fetch device_address fields
    $addressFields = ['street', 'town', 'city', 'district', 'state', 'pincode', 'country', 'landmark'];
    $addressData = fetchLatestDocumentFields('device_address', $device_id, $addressFields);
    if ($addressData !== null) {
        $return_response['data'] = array_merge($return_response['data'], $addressData);
    } else {
        if ($return_response['message'] === '') {
            $return_response['message'] = "No address records found.";
        } else {
            $return_response['message'] .= " No address records found.";
        }
    }

    $return_response['success'] = true;
} else {
    $return_response['message'] = "Data not available";
}

// Output JSON response
header('Content-Type: application/json');
echo json_encode($return_response);
