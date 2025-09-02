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
$permission_check = 0;

$dtIST = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
$date_time = new MongoDB\BSON\UTCDateTime($dtIST->getTimestamp() * 1000);

// Check if the request is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get POST parameters and sanitize
        $device_id = trim($_POST['D_ID']);
        $brandName = trim($_POST['BRAND']);
        $wattage = (int) $_POST['WATT'];
        $lights = (int) $_POST['LIGHTS'];
        $db = strtolower($device_id);

        $totalWatts = $lights * $wattage;

        // Check user permissions using MongoDB
        $userPermissionCollection = $user_db_conn->user_permissions;

        $permissionDoc = $userPermissionCollection->findOne(
            ['login_id' => (int)$user_login_id],
            ['projection' => ['lights_info_update' => 1]]
        );

        if (!$permissionDoc || !isset($permissionDoc['lights_info_update']) || (int)$permissionDoc['lights_info_update'] != 1) {
            echo json_encode(["status" => "error", "message" => "No permission to add the device"]);
            exit();
        }

        $permission_check = $permissionDoc['lights_info_update'];

        if ($permission_check == 1) {
            // Get collections from devices database
            $installedLightsCollection = $devices_db_conn->installed_lights_info;
            $liveDataCollection = $devices_db_conn->live_data_updates;


            $pipeline = [
                ['$match' => ['add_or_removed' => 1]],
                ['$group' => [
                    '_id' => null,
                    'total_lights_sum' => ['$sum' => '$total_lights'],
                    'total_wattage_sum' => ['$sum' => '$total_wattage']
                ]]
            ];

            $sumResult = $installedLightsCollection->aggregate($pipeline)->toArray();

            $total_lights_sum = 0;
            $total_wattage_sum = 0;

            if (!empty($sumResult)) {
                $total_lights_sum = (int) $sumResult[0]['total_lights_sum'];
                $total_wattage_sum = (int) $sumResult[0]['total_wattage_sum'];
            }

            // Prepare document for insertion into installed_lights_info
            $installedLightDoc = [
                'device_id' => $device_id,
                'brand_name' => $brandName,
                'wattage' => $wattage,
                'total_lights' => $lights,
                'total_wattage' => $totalWatts,
                'add_or_removed' => 1,
                'user_id' => $user_id,
                'user_mobile' => $mobile_no,
                'user_name' => $user_name,
                'role' => $role,
                'created_date_time' =>  $date_time,
                'updated_date_time' =>  $date_time
            ];

            // Insert into installed_lights_info
            $insertResult = $installedLightsCollection->insertOne($installedLightDoc);

            if ($insertResult->getInsertedCount() > 0) {
                // Calculate new totals
                $device = $liveDataCollection->findOne(
                    ['device_id' => $device_id]
                );
                $pre_wattage = $device['lights_wattage'];
                $pre_lights = $device['total_lights'];

                $new_lights = $lights + $pre_lights;
                $new_totalWatts = $totalWatts + $pre_wattage;

                // Perform upsert operation using updateOne with $set operator
                $upsertResult = $liveDataCollection->updateOne(
                    ['device_id' => $device_id],
                    [
                        '$set' => [
                            'device_id' => $device_id,
                            'lights_wattage' => $new_totalWatts,
                            'total_lights' => $new_lights,
                            'updated_at' => $date_time
                        ]
                    ],
                    ['upsert' => true]
                );

                if ($upsertResult->getModifiedCount() > 0 || $upsertResult->getUpsertedCount() > 0) {
                    echo json_encode(["status" => "success", "message" => "Details added and updated successfully."]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Error updating live_data_updates"]);
                }
            } else {
                echo json_encode(["status" => "error", "message" => "Error inserting into installed_lights_info"]);
            }
        }
    } catch (MongoDB\Exception\ConnectionTimeoutException $e) {
        echo json_encode(["status" => "error", "message" => "Database connection timeout: " . $e->getMessage()]);
    } catch (MongoDB\Exception\Exception $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "General error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
