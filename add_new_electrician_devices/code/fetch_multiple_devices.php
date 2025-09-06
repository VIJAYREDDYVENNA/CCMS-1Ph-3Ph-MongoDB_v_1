<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php'; // contains $user_db_conn
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars   = SessionManager::SessionVariables();
$mobile_no     = $sessionVars['mobile_no'];
$user_id       = $sessionVars['user_id'];
$role          = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];

header('Content-Type: application/json');

$device_list = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["GROUP_ID"])) {
    $group_id       = strtoupper(trim($_POST['GROUP_ID']));
    $selected_phase = strtoupper($_SESSION["SELECTED_PHASE"]);

    try {
        // collections
        $userDeviceListColl  = $user_db_conn->user_device_list;
        $deviceSelectionColl = $user_db_conn->device_selection_group;
        $electricianDevicesColl = $user_db_conn->electrician_devices;

        // fetch all device_ids already assigned to electricians
        $assignedCursor = $electricianDevicesColl->find(
            [],
            ['projection' => ['device_id' => 1]]
        );
        $assignedIds = [];
        foreach ($assignedCursor as $doc) {
            $assignedIds[] = $doc['device_id'];
        }

        // build base filter
        $filter = ['login_id' => (int)$user_login_id];

        if ($group_id !== "ALL") {
            // get group_by from device_selection_group
            $groupByDoc = $deviceSelectionColl->findOne(['login_id' => (int)$user_login_id]);
            $group_by = $groupByDoc['group_by'] ?? "device_group_or_area";
            $filter[$group_by] = $group_id;
        }

        if ($selected_phase !== "ALL") {
            $filter['phase'] = $selected_phase;
        }

        if (!empty($assignedIds)) {
            $filter['device_id'] = ['$nin' => $assignedIds];
        }

        // fetch devices
        $cursor = $userDeviceListColl->find($filter);

        foreach ($cursor as $doc) {
            if ($role === "SUPERADMIN") {
                $device_name = $doc['s_device_name'] ?? null;
            } else {
                $device_name = $doc['c_device_name'] ?? null;
            }

            $device_list[] = [
                "D_ID"   => $doc['device_id'] ?? null,
                "D_NAME" => $device_name
            ];
        }

        // natural sorting (prefix + number)
        usort($device_list, function($a, $b) {
            $prefixA = preg_replace('/[0-9]/', '', $a['D_ID']);
            $prefixB = preg_replace('/[0-9]/', '', $b['D_ID']);
            if ($prefixA === $prefixB) {
                $numA = (int)preg_replace('/\D/', '', $a['D_ID']);
                $numB = (int)preg_replace('/\D/', '', $b['D_ID']);
                return $numA <=> $numB;
            }
            return strcmp($prefixA, $prefixB);
        });

        echo json_encode($device_list);

    } catch (Exception $e) {
        echo json_encode([
            "status"  => "error",
            "message" => $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        "status"  => "error",
        "message" => "Invalid request."
    ]);
}
