<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php'; // gives $user_db_conn
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars   = SessionManager::SessionVariables();
$mobile_no     = $sessionVars['mobile_no'];
$user_id       = $sessionVars['user_id'];
$role          = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["group_id"])) {
    $group_id   = trim($_POST['group_id']);
    $searchTerm = trim($_POST['searchTerm'] ?? '');

    try {
        $electricianDevicesColl = $user_db_conn->electrician_devices;
        $deviceGroupViewColl    = $user_db_conn->user_device_group_view;
        $deviceSelectionGroupColl = $user_db_conn->device_selection_group;

        $electricians = [];
        $group_areas = [];

        if ($group_id === "ALL") {
            // Fetch all electricians with optional search term
            $filter = [];
            if (!empty($searchTerm)) {
                $filter = [
                    '$or' => [
                        ['device_id' => new MongoDB\BSON\Regex($searchTerm, 'i')],
                        ['electrician_name' => new MongoDB\BSON\Regex($searchTerm, 'i')]
                    ]
                ];
            }

            $cursor = $electricianDevicesColl->find($filter);

            foreach ($cursor as $doc) {
                $electricians[] = [
                    "id" => (string)$doc["_id"],
                    "name" => $doc["electrician_name"] ?? null,
                    "phone" => $doc["phone_number"] ?? null,
                    "device_id" => $doc["device_id"] ?? null
                ];
            }
        } else {
            // Get the group_by field for current user
            $groupDoc = $deviceSelectionGroupColl->findOne(
                ["login_id" => (int)$user_login_id],
                ["projection" => ["group_by" => 1]]
            );

            $group_by = $groupDoc['group_by'] ?? 'device_group_or_area';

            if ($group_by !== "device_group_or_area") {
                $field = match ($group_by) {
                    'state' => 'state',
                    'district' => 'district',
                    'city_or_town' => 'city_or_town',
                    default => 'device_group_or_area'
                };

                // Get all group areas corresponding to the selected group_id
                $groupCursor = $deviceGroupViewColl->distinct('device_group_or_area', [$field => $group_id]);
                $group_areas = $groupCursor ?: [];
            }

            // Build filter for electricians collection
            $filter = [];
            if (!empty($group_areas)) {
                $filter['group_area'] = ['$in' => $group_areas];
            } else {
                $filter['group_area'] = $group_id;
            }

            if (!empty($searchTerm)) {
                $filter['$or'] = [
                    ['device_id' => new MongoDB\BSON\Regex($searchTerm, 'i')],
                    ['electrician_name' => new MongoDB\BSON\Regex($searchTerm, 'i')]
                ];
            }

            $cursor = $electricianDevicesColl->find($filter);

            foreach ($cursor as $doc) {
                $electricians[] = [
                    "id" => (string)$doc["_id"],
                    "name" => $doc["electrician_name"] ?? null,
                    "phone" => $doc["phone_number"] ?? null,
                    "device_id" => $doc["device_id"] ?? null
                ];
            }
        }

        echo json_encode([
            "status" => "success",
            "electricians" => $electricians,
            "total_results" => count($electricians)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage(),
            "electricians" => []
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request parameters",
        "electricians" => []
    ]);
}
