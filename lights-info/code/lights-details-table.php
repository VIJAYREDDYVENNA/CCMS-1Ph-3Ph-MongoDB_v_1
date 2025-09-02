<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();

$return_response = [
    'success' => false,
    'message' => '',
    'data' => []
];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['D_ID'])) {
    try {
        $device_id_input = trim($_POST['D_ID']);
        $device_id_lower = strtolower($device_id_input);
        
        // Get the installed_lights_info collection from devices database
        $installedLightsCollection = $devices_db_conn->installed_lights_info;
        
        // Try multiple variations of device_id matching
        $cursor = $installedLightsCollection->find(
            [
                'add_or_removed' => 1,
                '$or' => [
                    ['device_id' => $device_id_input],        // Original case
                    ['device_id' => $device_id_lower],        // Lowercase
                    ['device_id' => strtoupper($device_id_input)], // Uppercase
                    ['device_id' => ['$regex' => '^' . preg_quote($device_id_input, '/') . '$', '$options' => 'i']] // Case insensitive regex
                ]
            ],
            [
                'projection' => [
                    '_id' => 1, // Include the _id field in the projection
                    'device_id' => 1,
                    'brand_name' => 1,
                    'wattage' => 1,
                    'total_lights' => 1,
                    'total_wattage' => 1
                ]
            ]
        );
        
        $data = [];
        $total_lights_sum = 0;
        $total_wattage_sum = 0;
        
        // Process each document
        foreach ($cursor as $document) {
            $row = [
                '_id' => (string)$document['_id'], // Convert _id to string so it can be returned in the response
                'device_id' => $document['device_id'] ?? '',
                'brand_name' => $document['brand_name'] ?? '',
                'wattage' => $document['wattage'] ?? 0,
                'total_lights' => $document['total_lights'] ?? 0,
                'total_wattage' => $document['total_wattage'] ?? 0
            ];
            
            $data[] = $row;
            $total_lights_sum += $row['total_lights'];
            $total_wattage_sum += $row['total_wattage'];
        }
        
        // Debug: Let's also add a query to check what device_ids exist
        $allDevices = $installedLightsCollection->distinct('device_id', ['add_or_removed' => 1]);
        
        // If no data found, add debug information
        if (empty($data)) {
            $return_response['debug'] = [
                'searched_for' => $device_id_input,
                'available_devices' => $allDevices,
                'total_documents_with_add_or_removed_1' => $installedLightsCollection->countDocuments(['add_or_removed' => 1])
            ];
        }
        
        // Add the last row with the totals (only if we have data)
        if (!empty($data)) {
            $data[] = [
                '_id' => 'Total',
                'device_id' => 'Total',
                'brand_name' => '',
                'wattage' => '',
                'total_lights' => $total_lights_sum,
                'total_wattage' => $total_wattage_sum
            ];
        }
        
        $return_response['success'] = true;
        $return_response['data'] = $data;
        
    } catch (Exception $e) {
        $return_response['message'] = "Database connection timeout: " . $e->getMessage();
    } catch (Exception $e) {
        $return_response['message'] = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $return_response['message'] = "General error: " . $e->getMessage();
    }
} else {
    $return_response['message'] = "Data not available";
}

// Output JSON response
header('Content-Type: application/json');
echo json_encode($return_response);
?>
