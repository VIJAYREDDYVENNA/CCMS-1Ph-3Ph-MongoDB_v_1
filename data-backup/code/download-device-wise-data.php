<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

// Optimize for large datasets
ini_set('memory_limit', '1G');
ini_set('max_execution_time', 600); // 10 minutes
ini_set('output_buffering', 0); // Disable output buffering

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();
$user_login_id = $sessionVars['user_login_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $device_id = filter_input(INPUT_POST, 'D_ID', FILTER_SANITIZE_STRING);
    $parameter = filter_input(INPUT_POST, 'PARAMETER', FILTER_SANITIZE_STRING);

    if (empty($device_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Device ID is required']);
        exit;
    }

    try {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_{$device_id}_{$timestamp}";

        if ($parameter === 'backup-excel') {
            generateFastCSVBackup($devices_db_conn, $device_id, $filename);
        } else {
            generateFastJSONBackup($devices_db_conn, $device_id, $filename);
        }

    } catch (Exception $e) {
        error_log("MongoDB Backup Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error occurred: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function generateFastCSVBackup($db, $device_id, $filename) {
    // Set headers for immediate streaming
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('X-Accel-Buffering: no'); // Disable nginx buffering

    // Disable PHP output buffering completely
    while (ob_get_level()) {
        ob_end_clean();
    }

    $output = fopen('php://output', 'w');
    
    // Write minimal header
    fwrite($output, "Device: {$device_id} | Generated: " . date('Y-m-d H:i:s') . "\n\n");

    $collections = $db->listCollections();
    $totalDocs = 0;

    $skipCollections = ["voltage_current_graph", "software_update", "live_data_updates"];

    foreach ($collections as $collectionInfo) {
        $collectionName = $collectionInfo->getName();
        
        if (strpos($collectionName, 'system.') === 0) continue;

        if (in_array($collectionName, $skipCollections)) continue;

        $collection = $db->selectCollection($collectionName);
        
        // Use large batch size for maximum performance
        $batchSize = 5000;
        $skip = 0;
        $headerWritten = false;
        $keys = [];
        $docsInCollection = 0;

        while (true) {
            // Optimized query - minimal projection initially to check if data exists
            $cursor = $collection->find(
                ['device_id' => $device_id],
                [
                    'skip' => $skip,
                    'limit' => $batchSize,
                    'sort' => ['_id' => 1],
                    'maxTimeMS' => 30000 // 30 second timeout per batch
                ]
            );

            $batch = [];
            foreach ($cursor as $doc) {
                $batch[] = fastConvertBSON($doc);
            }

            if (empty($batch)) break;

            // Write collection header only when first data is found
            if (!$headerWritten) {
                fwrite($output, "\n=== {$collectionName} ===\n");
                
                // Get all unique keys from first batch only (for speed)
                foreach ($batch as $doc) {
                    $keys = array_merge($keys, array_keys($doc));
                }
                $keys = array_unique($keys);
                fputcsv($output, $keys);
                $headerWritten = true;
            }

            // Write data rows
            foreach ($batch as $doc) {
                $row = [];
                foreach ($keys as $key) {
                    $value = $doc[$key] ?? '';
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                    $row[] = $value;
                }
                fputcsv($output, $row);
                $docsInCollection++;
            }

            $skip += $batchSize;
            
            // Immediate flush every batch
            fflush($output);
        }

        if ($docsInCollection > 0) {
            $totalDocs += $docsInCollection;
            fwrite($output, "\n");
        }
    }

    fwrite($output, "\nTotal Documents: {$totalDocs}\n");
    fclose($output);
    exit;
}

function generateFastJSONBackup($db, $device_id, $filename) {
    // Set headers for immediate streaming
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    header('Cache-Control: no-cache, must-revalidate');
    header('X-Accel-Buffering: no');

    // Disable PHP output buffering
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Start JSON immediately
    echo '{"device_id":"' . $device_id . '","generated":"' . date('Y-m-d H:i:s') . '","data":{';

    $collections = $db->listCollections();
    $totalDocs = 0;
    $firstCollection = true;

    foreach ($collections as $collectionInfo) {
        $collectionName = $collectionInfo->getName();
        
        if (strpos($collectionName, 'system.') === 0) continue;

        if($collectionName=="voltage_current_graph")
        {
            break;
        }

        $collection = $db->selectCollection($collectionName);
        
        $batchSize = 3000; // Smaller for JSON to avoid memory issues
        $skip = 0;
        $firstDoc = true;
        $hasData = false;
        $docsInCollection = 0;

        while (true) {
            $cursor = $collection->find(
                ['device_id' => $device_id],
                [
                    'skip' => $skip,
                    'limit' => $batchSize,
                    'sort' => ['_id' => 1],
                    'maxTimeMS' => 30000
                ]
            );

            $batch = [];
            foreach ($cursor as $doc) {
                $batch[] = fastConvertBSON($doc);
            }

            if (empty($batch)) break;

            // Start collection output when first data found
            if (!$hasData) {
                if (!$firstCollection) echo ',';
                echo '"' . $collectionName . '":[';
                $firstCollection = false;
                $hasData = true;
            }

            // Output documents
            foreach ($batch as $doc) {
                if (!$firstDoc) echo ',';
                echo json_encode($doc, JSON_UNESCAPED_UNICODE);
                $firstDoc = false;
                $docsInCollection++;
            }

            $skip += $batchSize;
            
            // Flush every 1000 documents
            if ($docsInCollection % 1000 === 0) {
                flush();
            }
        }

        // Close collection if it had data
        if ($hasData) {
            echo ']';
            $totalDocs += $docsInCollection;
        }
    }

    echo '},"total_documents":' . $totalDocs . '}';
    exit;
}

// Extremely fast BSON conversion - minimal processing
function fastConvertBSON($document) {
    $data = [];
    
    foreach ($document as $key => $value) {
        if ($value instanceof MongoDB\BSON\ObjectId) {
            $data[$key] = (string)$value;
        } elseif ($value instanceof MongoDB\BSON\UTCDateTime) {
            // Fast date conversion - no timezone change
            $data[$key] = $value->toDateTime()->format('Y-m-d H:i:s');
        } elseif ($value instanceof MongoDB\BSON\Decimal128) {
            $data[$key] = (string)$value;
        } elseif ($value instanceof MongoDB\BSON\Binary) {
            $data[$key] = base64_encode($value->getData());
        } elseif ($value instanceof MongoDB\Model\BSONDocument || $value instanceof MongoDB\Model\BSONArray) {
            // Convert to array quickly without recursion for speed
            $data[$key] = $value->toArray();
        } else {
            $data[$key] = $value;
        }
    }
    
    return $data;
}
?>