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

$return_response = "";
$user_devices = "";
$device_list = array();
$total_switch_point = 0;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["GROUP_ID"])) {
    $group_id = $_POST['GROUP_ID'];
    
    include_once(BASE_PATH_1 . "common-files/selecting_group_device.php");
    $_SESSION["DEVICES_LIST"] = json_encode($device_list);

    if ($user_devices != "") {
        $user_devices = substr($user_devices, 0, -1);
    }

    $device_ids = explode(",", $user_devices);
    $param_type = str_repeat("s", count($device_ids));
    $params = array();
    foreach ($device_ids as $device_id) {
        $params[] = $device_id;
    }

    // explode string into array
    $device_ids = array_map('trim', explode(",", $user_devices));

    $return_response = "";

    $device_ids = array_map(function($id) {
        return trim($id, "'");  
    }, $device_ids);


// Query MongoDB
    $alerts = $devices_db_conn->main_alerts_and_updates->find(
        [
            'device_id' => ['$in' => $device_ids]
        ],
        [
        'sort' => ['_id' => -1],   // MongoDB ObjectId (_id) increases with time
        'limit' => 100
    ]
);

    foreach ($alerts as $rl) {
        $device_id        = $rl['device_id'] ?? "";
        $device_id_name   = $rl['device_id_name'] ?? "";
        $update           = $rl['update'] ?? "";
        $electrician_name = $rl['electrician_name'] ?? "";
        $phone_number     = $rl['electrician_phone_number'] ?? "";

    // ✅ handle MongoDB datetime conversion
        $date_time = "";
        if (isset($rl['date_time']) && $rl['date_time'] instanceof MongoDB\BSON\UTCDateTime) {
            $date_time = $rl['date_time']->toDateTime()->modify('+5 hours 30 minutes')->format("Y-m-d H:i:s");
        }

    // build response
        $return_response .= '<div class="alert-item">
        <div class="device-header">
        <span class="device-name">
        <i class="bi bi-cpu"></i>
        ' . htmlspecialchars($device_id_name) . '
        </span>
        </div>
        <div class="mb-1 font-small text-info-emphasis">' . htmlspecialchars($update) . '</div>
        <div class="d-flex justify-content-end">
        <span class="timestamp text-primary">
        <i class="bi bi-clock"></i>
        ' . $date_time . '
        </span>
        </div>';

        if (!empty($electrician_name) && !empty($phone_number)) {
            $return_response .= '<div class="contact-info">
            <span class="electrician-info">
            <i class="bi bi-person"></i>
            ' . htmlspecialchars($electrician_name) . '
            </span>
            <a href="tel:' . htmlspecialchars($phone_number) . '" class="phone-number">
            <i class="bi bi-telephone"></i>
            ' . htmlspecialchars($phone_number) . '
            </a>
            </div>';
        }

    $return_response .= '</div>'; // Closing alert-item
}

echo json_encode($return_response);

}
?>