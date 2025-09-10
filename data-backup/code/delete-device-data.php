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
        // Step 1: Check permission and date before proceeding
        $permissionCheck = checkDeletionPermission($devices_db_conn, $device_id);
        
        if (!$permissionCheck['allowed']) {
            echo json_encode([
                'success' => false,
                'no_permission' => true,
                'error' => $permissionCheck['message']
            ]);
            exit;
        }

        // Step 2: Check if device has any data
        $dataCheck = checkDeviceDataExists($devices_db_conn, $device_id);
        
        if (!$dataCheck['has_data']) {
            echo json_encode([
                'success' => false,
                'no_data' => true,
                'message' => 'No data found for this device ID, deletion cannot proceed'
            ]);
            exit;
        }

        // Step 3: Proceed with deletion
        $result = deleteDeviceDataFromAllCollections($devices_db_conn, $device_id);
        
        // Step 4: Update permission record after successful deletion
        $updateResult = updatePermissionAfterDeletion($devices_db_conn, $device_id);
        
        if (!$updateResult['success']) {
            error_log("Warning: Failed to update permission record after deletion for device {$device_id}: " . $updateResult['message']);
        }
        
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

/**
 * Check if deletion is permitted for the given device with date-time validation
 */
function checkDeletionPermission($db, $device_id) {
    try {
        $permissionCollection = $db->selectCollection('data_delete_permission');
        
        // Find permission record for this device
        $permissionDoc = $permissionCollection->findOne(['device_id' => $device_id]);
        
        if (!$permissionDoc) {
            return [
                'allowed' => false,
                'message' => 'No permission record found for this device ID'
            ];
        }
        
        // Check if permission is explicitly true (boolean)
        if (!isset($permissionDoc['permission']) || $permissionDoc['permission'] !== true) {
            return [
                'allowed' => false,
                'message' => 'Deletion permission is denied for this device ID'
            ];
        }
        
        // Check updated_date against current date-time with 20-minute window
        $currentDateTime = new DateTime();
        
        if (!isset($permissionDoc['updated_date'])) {
            return [
                'allowed' => false,
                'message' => 'Permission record missing updated_date field'
            ];
        }
        
        // Convert updated_date to DateTime object
        $updatedDateTime = null;
        $updatedDate = $permissionDoc['updated_date'];
        
        if ($updatedDate instanceof MongoDB\BSON\UTCDateTime) {
            $updatedDateTime = $updatedDate->toDateTime();
        } elseif (is_string($updatedDate)) {
            try {
                $updatedDateTime = new DateTime($updatedDate);
            } catch (Exception $e) {
                return [
                    'allowed' => false,
                    'message' => 'Invalid updated_date format in permission record'
                ];
            }
        } else {
            return [
                'allowed' => false,
                'message' => 'Invalid updated_date type in permission record'
            ];
        }
        
        // Calculate time difference in minutes
        $timeDifferenceSeconds = $currentDateTime->getTimestamp() - $updatedDateTime->getTimestamp();
        $timeDifferenceMinutes = $timeDifferenceSeconds / 60;
        
        // Check if updated_date is within the last 20 minutes
        if ($timeDifferenceMinutes > 20) {
            return [
                'allowed' => false,
                'message' => 'Deletion permission has expired. Permission is only valid for 20 minutes after being granted'
            ];
        }
        
        // Check if updated_date is in the future (should not happen, but safety check)
        if ($timeDifferenceMinutes < 0) {
            return [
                'allowed' => false,
                'message' => 'Permission updated_date cannot be in the future'
            ];
        }
        
        return [
            'allowed' => true,
            'message' => sprintf('Permission granted. Valid for %.1f more minutes', 20 - $timeDifferenceMinutes)
        ];
        
    } catch (Exception $e) {
        error_log("Permission check error: " . $e->getMessage());
        return [
            'allowed' => false,
            'message' => 'Error checking deletion permission: ' . $e->getMessage()
        ];
    }
}

/**
 * Update permission record after successful deletion
 */
function updatePermissionAfterDeletion($db, $device_id) {
    try {
        $permissionCollection = $db->selectCollection('data_delete_permission');
        
        $updateResult = $permissionCollection->updateOne(
            ['device_id' => $device_id],
            [
                '$set' => [
                    'permission' => false
                ]
            ]
        );
        
        if ($updateResult->getModifiedCount() > 0) {
            return [
                'success' => true,
                'message' => 'Permission record updated successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Permission record was not updated (no matching document found or no changes made)'
            ];
        }
        
    } catch (Exception $e) {
        error_log("Permission update error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error updating permission record: ' . $e->getMessage()
        ];
    }
}

/**
 * Check if device has any data in any collection
 */
function checkDeviceDataExists($db, $device_id) {
    try {
        $collections = $db->listCollections();
        $totalDocuments = 0;
        
        // Define collections to skip
        $skipCollections = ["voltage_current_graph", "data_delete_permission"];

        foreach ($collections as $collectionInfo) {
            $collectionName = $collectionInfo->getName();
            
            // Skip system collections and specified collections
            if (strpos($collectionName, 'system.') === 0 || in_array($collectionName, $skipCollections)) {
                continue;
            }

            try {
                $collection = $db->selectCollection($collectionName);
                $documentCount = $collection->countDocuments(['device_id' => $device_id]);
                $totalDocuments += $documentCount;
                
                // If we find any data, we can return early
                if ($totalDocuments > 0) {
                    break;
                }
                
            } catch (Exception $e) {
                // Log error but continue checking other collections
                error_log("Error checking collection {$collectionName}: " . $e->getMessage());
                continue;
            }
        }

        return [
            'has_data' => $totalDocuments > 0,
            'total_documents' => $totalDocuments
        ];
        
    } catch (Exception $e) {
        error_log("Data existence check error: " . $e->getMessage());
        return [
            'has_data' => false,
            'total_documents' => 0
        ];
    }
}

/**
 * Delete device data from all collections
 */
function deleteDeviceDataFromAllCollections($db, $device_id) {
    $collections = $db->listCollections();
    $totalDeleted = 0;
    $collectionsProcessed = 0;
    $errors = [];
    
    // Define collections to skip
    $skipCollections = ["voltage_current_graph", "data_delete_permission"];

    foreach ($collections as $collectionInfo) {
        $collectionName = $collectionInfo->getName();
        
        // Skip system collections and specified collections
        if (strpos($collectionName, 'system.') === 0 || in_array($collectionName, $skipCollections)) {
            continue;
        }

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
                
                // Log progress for large collections
                if ($deletedCount > 1000) {
                    error_log("Deleted {$deletedCount} documents from collection {$collectionName} for device {$device_id}");
                }
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

/**
 * Alternative faster deletion function for very large datasets
 */
function fastDeleteDeviceData($db, $device_id) {
    $collections = $db->listCollections();
    $totalDeleted = 0;
    $collectionsProcessed = 0;
    $errors = [];
    
    // Define collections to skip
    $skipCollections = ["voltage_current_graph", "data_delete_permission"];

    foreach ($collections as $collectionInfo) {
        $collectionName = $collectionInfo->getName();
        
        // Skip system collections and specified collections
        if (strpos($collectionName, 'system.') === 0 || in_array($collectionName, $skipCollections)) {
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