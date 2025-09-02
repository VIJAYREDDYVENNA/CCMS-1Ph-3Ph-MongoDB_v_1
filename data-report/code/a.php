<?php
require_once '../../base-path/config-path.php';

require_once BASE_PATH_1 . 'config_db/config.php';


$collection_20 = $client->ccms_data->live_data_updates_20_new;


$device_id = "SPIOT_206"; // your device_id
try {
    // Step 1: Fetch documents by device_id (oldest 2 records)
	$docs = $collection_20->find(
		[ 'device_id' => (string)$device_id ],
		[
			'sort'  => ['date_time' => 1],
			'limit' => 2
		]
	);

    // Step 2: Loop and delete each document
	foreach ($docs as $doc) {
		if (isset($doc['device_id']) && isset($doc['date_time'])) {
			$result = $collection_20->deleteOne([
                'device_id' => $doc['device_id'],   // metaField
                'date_time' => $doc['date_time']    // timeField (already UTCDateTime)
            ]);

			if ($result->getDeletedCount() > 0) {
				echo "✅ Deleted: {$doc['_id']}\n";
			} else {
				echo "⚠️ Not deleted (filter mismatch): {$doc['_id']}\n";
			}
		} else {
			echo "⚠️ Skipped (missing device_id or date_time)\n";
		}
	}

} catch (Exception $e) {
	echo "❌ Error: " . $e->getMessage();
}




?>