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
$permission_check = 0;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Exception\Exception as MongoException;

// Function to sanitize input (modified for MongoDB)
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Handle API request
if (isset($_POST['energyconsumption'])) {
    header('Content-Type: application/json');

    try {
        if (!isset($_POST['D_id'])) {
            throw new Exception("Missing device ID.");
        }

        if (!isset($_POST['fromdate'], $_POST['fromtime'], $_POST['todate'], $_POST['totime'])) {
            throw new Exception("Missing date/time parameters.");
        }

        // Sanitize inputs
        $device_id = sanitize_input($_POST['D_id']);
        $fromdate = sanitize_input($_POST['fromdate']);
        $fromtime = sanitize_input($_POST['fromtime']);
        $todate = sanitize_input($_POST['todate']);
        $totime = sanitize_input($_POST['totime']);

        // Validate date formats
        if (!strtotime($fromdate . ' ' . $fromtime) || !strtotime($todate . ' ' . $totime)) {
            throw new Exception("Invalid date/time format.");
        }

        // Create DateTime objects for MongoDB queries
        $from_datetime_str = $fromdate . ' ' . $fromtime;
        $to_datetime_str = $todate . ' ' . $totime;
        
        $from_timestamp = strtotime($from_datetime_str);
        $to_timestamp = strtotime($to_datetime_str);

        // Convert to MongoDB UTCDateTime
        $from_utc = new UTCDateTime($from_timestamp * 1000);
        $to_utc = new UTCDateTime($to_timestamp * 1000);

        // Get MongoDB collection - try different possible collection names
        $possible_collections = ['livedata', 'live_data', 'LiveData'];
        $collection = null;
        
        foreach ($possible_collections as $coll_name) {
            try {
                $test_collection = $devices_db_conn->selectCollection($coll_name);
                $test_count = $test_collection->countDocuments(['device_id' => $device_id], ['maxTimeMS' => 5000]);
                if ($test_count > 0) {
                    $collection = $test_collection;
                    break;
                }
            } catch (Exception $e) {
                // Continue to next collection name
                continue;
            }
        }
        
        if (!$collection) {
            // Default to livedata
            $collection = $devices_db_conn->livedata;
        }

        // FROM query - Find the first record >= from_datetime for the device
        $from_filter = [
            'device_id' => $device_id,
            'date_time' => ['$gte' => $from_utc]
        ];

        $from_options = [
            'sort' => ['date_time' => 1],
            'limit' => 1,
            'projection' => [
                'device_id' => 1,
                'date_time' => 1,
                'energy_kwh_total' => 1,
                'energy_kvah_total' => 1
            ]
        ];

        $from_result = $collection->findOne($from_filter, $from_options);

        if (!$from_result) {
            throw new Exception("No data found for the 'From' date time range.");
        }

        // TO query - Find the last record <= to_datetime for the device
        $to_filter = [
            'device_id' => $device_id,
            'date_time' => ['$lte' => $to_utc]
        ];

        $to_options = [
            'sort' => ['date_time' => -1],
            'limit' => 1,
            'projection' => [
                'device_id' => 1,
                'date_time' => 1,
                'energy_kwh_total' => 1,
                'energy_kvah_total' => 1
            ]
        ];

        $to_result = $collection->findOne($to_filter, $to_options);

        if (!$to_result) {
            throw new Exception("No data found for device '$device_id' in the 'To' date time range.");
        }

        // Extract values
        $from_kwh = $from_result['energy_kwh_total'] ?? 0;
        $from_kvah = $from_result['energy_kvah_total'] ?? 0;
        $to_kwh = $to_result['energy_kwh_total'] ?? 0;
        $to_kvah = $to_result['energy_kvah_total'] ?? 0;

        // Calculate consumption
        $diff_kwh = $to_kwh - $from_kwh;
        $diff_kvah = $to_kvah - $from_kvah;

        // Convert MongoDB dates to readable format (handle timezone properly)
        $actual_from_time = $from_result['date_time']->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
        $actual_to_time = $to_result['date_time']->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');

        echo json_encode([
            "status" => "success",
            "data" => [
                "diff_kwh" => $diff_kwh,
                "diff_kvah" => $diff_kvah,
                "actual_from_time" => $actual_from_time,
                "actual_to_time" => $actual_to_time
            ]
        ]);

        exit;

    } catch (MongoException $e) {
        echo json_encode([
            "status" => "error",
            "message" => "MongoDB Error: " . $e->getMessage()
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
        exit;
    }
}
?>