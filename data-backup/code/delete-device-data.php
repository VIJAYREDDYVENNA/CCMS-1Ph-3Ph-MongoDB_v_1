<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

// Set execution time and memory for large deletions
ini_set('max_execution_time', 600); // 10 minutes
ini_set('memory_limit', '512M');

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();
$user_login_id = $sessionVars['user_login_id'];

// Use existing database connection from config.php
// $devices_db_conn is already available

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $device_id = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);
    $parameter = filter_input(INPUT_POST, 'PARAMETER', FILTER_SANITIZE_STRING);

    if (empty($device_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Device ID is required', 'success' => false]);
        exit;
    }

    if ($parameter !== 'delete-data') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid parameter', 'success' => false]);
        exit;
    }

    try {
        $result = deleteDeviceDataFromAllCollections($devices_db_conn, $device_id);
        
        // Log the deletion activity
        error_log("Device Data Deletion: Device ID {$device_id} - {$result['total_deleted']} documents deleted from {$result['collections_processed']} collections by user {$user_login_id}");
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully deleted {$result['total_deleted']} documents from {$result['collections_processed']} collections for device {$device_id}",
            'details' => $result
        ]);

    } catch (Exception $e) {
        error_log("MongoDB Deletion Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => 'Database error occurred: ' . $e->getMessage(),
            'success' => false
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'success' => false]);
}

function deleteDeviceDataFromAllCollections($db, $device_id) {
    $collections = $db->listCollections();
    $totalDeleted = 0;
    $collectionsProcessed = 0;
    // $collectionDetails = []; // Commented out - not showing detailed breakdown
    $errors = [];

    $skipCollections = ["voltage_current_graph", "software_update", "live_data_updates"];

    foreach ($collections as $collectionInfo) {
        $collectionName = $collectionInfo->getName();
        
        // Skip system collections
        if (strpos($collectionName, 'system.') === 0) {
            continue;
        }
        if (in_array($collectionName, $skipCollections)) {
            continue
        };


        try {
            $collection = $db->selectCollection($collectionName);
            
            // First check if there are any documents for this device
            $documentCount = $collection->countDocuments(['device_id' => $device_id]);
            
            if ($documentCount > 0) {
                // Delete documents for this device
                $deleteResult = $collection->deleteMany(['device_id' => $device_id]);
                $deletedCount = $deleteResult->getDeletedCount();
                
                $totalDeleted += $deletedCount;
                $collectionsProcessed++;
                
                // Commented out - detailed collection tracking not needed for now
                /*
                $collectionDetails[] = [
                    'collection_name' => $collectionName,
                    'documents_found' => $documentCount,
                    'documents_deleted' => $deletedCount,
                    'status' => 'success'
                ];
                */
                
                // Log progress for large collections
                if ($deletedCount > 1000) {
                    error_log("Deleted {$deletedCount} documents from collection {$collectionName} for device {$device_id}");
                }
            } else {
                // No documents found for this device in this collection
                // Commented out - not tracking empty collections
                /*
                $collectionDetails[] = [
                    'collection_name' => $collectionName,
                    'documents_found' => 0,
                    'documents_deleted' => 0,
                    'status' => 'no_data'
                ];
                */
            }
            
        } catch (Exception $e) {
            $errors[] = [
                'collection_name' => $collectionName,
                'error' => $e->getMessage()
            ];
            
            // Commented out - not tracking detailed error info per collection
            /*
            $collectionDetails[] = [
                'collection_name' => $collectionName,
                'documents_found' => 'unknown',
                'documents_deleted' => 0,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
            */
            
            error_log("Error deleting from collection {$collectionName}: " . $e->getMessage());
        }
    }

    return [
        'device_id' => $device_id,
        'total_deleted' => $totalDeleted,
        'collections_processed' => $collectionsProcessed,
        // 'collection_details' => $collectionDetails, // Commented out - not showing detailed breakdown
        'errors' => $errors,
        'deletion_completed_at' => date('Y-m-d H:i:s')
    ];
}

// Alternative faster deletion function for very large datasets
function fastDeleteDeviceData($db, $device_id) {
    $collections = $db->listCollections();
    $totalDeleted = 0;
    $collectionsProcessed = 0;
    $errors = [];

    foreach ($collections as $collectionInfo) {
        $collectionName = $collectionInfo->getName();
        
        if (strpos($collectionName, 'system.') === 0) {
            continue;
        }

        try {
            $collection = $db->selectCollection($collectionName);
            
            // Direct deletion without counting first (faster for large datasets)
            $deleteResult = $collection->deleteMany([
                'device_id' => $device_id
            ], [
                'maxTimeMS' => 60000 // 60 second timeout per collection
            ]);
            
            $deletedCount = $deleteResult->getDeletedCount();
            
            if ($deletedCount > 0) {
                $totalDeleted += $deletedCount;
                $collectionsProcessed++;
            }
            
        } catch (Exception $e) {
            $errors[] = [
                'collection_name' => $collectionName,
                'error' => $e->getMessage()
            ];
            error_log("Error deleting from collection {$collectionName}: " . $e->getMessage());
        }
    }

    return [
        'device_id' => $device_id,
        'total_deleted' => $totalDeleted,
        'collections_processed' => $collectionsProcessed,
        'errors' => $errors,
        'deletion_completed_at' => date('Y-m-d H:i:s')
    ];
}
?>