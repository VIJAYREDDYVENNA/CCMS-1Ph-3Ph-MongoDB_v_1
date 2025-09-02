<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();

$return_response = [
    'success' => false,
    'message' => '',
    'data' => [
        'l_r' => 0,
        'l_y' => 0,
        'l_b' => 0,
        'u_r' => 0,
        'u_y' => 0,
        'u_b' => 0,
        'i_r' => 0,
        'i_y' => 0,
        'i_b' => 0,
        'pf' => 0,
        'capacity' => 0,
        'frame_time' => 60,
        'ct_ratio' => 0
    ],
    'phase' =>''
];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['D_ID'])) {
    $Deviceid = trim($_POST['D_ID']);
   
    $device_id = strtoupper($Deviceid);
    $db = strtolower($Deviceid);
    $id=$device_id;

    include_once("../../common-files/fetch-device-phase.php");
    $phase = $device_phase;
    $return_response['phase'] = $phase;

    // Use MongoDB client and db connection that are assumed to exist in $devices_db_conn
    // Replace below comment lines with actual initialized MongoDB\Client and selecting db:
    // $devices_db_conn = $manager->ccms_data;

    // Each collection holds documents for all devices identified by 'device_id'
    // Query each collection for the latest (by date_time or inserted _id descending) document for this device

    // Helper function to fetch latest numeric fields from collection
    function fetchLatestFields($collection, $device_id, array $fields) {
        global $devices_db_conn;
        $filter = ['device_id' => $device_id];
        $options = [
            'sort' => ['date_time' => -1],
            'limit' => 1,
            'projection' => array_fill_keys($fields, 1)
        ];

        $doc = $devices_db_conn->$collection->findOne($filter, $options);
        if ($doc === null) {
            return null;
        }
        $result = [];
        foreach ($fields as $field) {
            if (isset($doc[$field]) && is_numeric($doc[$field])) {
                $result[$field] = $doc[$field];
            }
        }
        return $result;
    }

    // Fetch and merge data from various collections
    $fields_voltage = fetchLatestFields('limits_voltage', $device_id, ['l_r','l_y','l_b','u_r','u_y','u_b']);
    if ($fields_voltage !== null) {
        $return_response['data'] = array_merge($return_response['data'], $fields_voltage);
    } else {
        $return_response['message'] = "No data in limits_voltage.";
    }

    $fields_current = fetchLatestFields('limits_current', $device_id, ['i_r','i_y','i_b']);
    if ($fields_current !== null) {
        $return_response['data'] = array_merge($return_response['data'], $fields_current);
    } else {
        $return_response['message'] = "No data in limits_current.";
    }

    $fields_pf = fetchLatestFields('limits_pf', $device_id, ['pf']);
    if ($fields_pf !== null) {
        $return_response['data'] = array_merge($return_response['data'], $fields_pf);
    } else {
        $return_response['message'] = "No data in limits_pf.";
    }

    $fields_capacity = fetchLatestFields('unit_capacity', $device_id, ['capacity']);
    if ($fields_capacity !== null) {
        $return_response['data'] = array_merge($return_response['data'], $fields_capacity);
    } else {
        $return_response['message'] = "No data in unit_capacity.";
    }

    $fields_frame_time = fetchLatestFields('frame_time', $device_id, ['frame_time']);
    if ($fields_frame_time !== null) {
        $return_response['data'] = array_merge($return_response['data'], $fields_frame_time);
    } else {
        $return_response['message'] = "No data in frame_time.";
    }

    $fields_ct_ratio = fetchLatestFields('limits_ct_ratio', $device_id, ['ct_ratio']);
    if ($fields_ct_ratio !== null) {
        $return_response['data'] = array_merge($return_response['data'], $fields_ct_ratio);
    } else {
        $return_response['message'] = "No data in limits_ct_ratio.";
    }

    $return_response['success'] = true;

} else {
    $return_response['message'] = "Data not available";
}

// Output JSON response
header('Content-Type: application/json');
echo json_encode($return_response);
?>
