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

// Helper function to send JSON response
function sendResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = $_POST['GROUP_ID'] ?? null;

    try {
        // MongoDB collection
        $collection = $devices_db_conn->live_data_updates;

        // Query devices with overload_flag = 1
        $filter = ['overload_flag' => 1];

        // Project only relevant fields
        $options = [
            'projection' => [
                'device_id' => 1,
                'energy_kwh_total' => 1,
                'lights_wattage' => 1,
                '_id' => 0
            ]
        ];

        $cursor = $collection->find($filter, $options);

        $devices = [];
        foreach ($cursor as $doc) {
            $energy_kwh_total = isset($doc['energy_kwh_total']) ? (float)$doc['energy_kwh_total'] : 0;
            $lights_wattage = isset($doc['lights_wattage']) ? (float)$doc['lights_wattage'] : 0;
            $difference = $energy_kwh_total - $lights_wattage;

            $devices[] = [
                'device_id' => $doc['device_id'] ?? '',
                'total_load_received' => $energy_kwh_total,
                'total_wattage_installed' => $lights_wattage,
                'difference' => $difference
            ];
        }

        sendResponse([
            'success' => true,
            'devices' => $devices
        ]);

    } catch (Exception $e) {
        sendResponse(['success' => false, 'message' => 'Error fetching devices: ' . $e->getMessage()]);
    }
} else {
    sendResponse(['success' => false, 'message' => 'Invalid request method']);
}
?>
