<?php
$device_phase = "3PH";

try {
    // Select the user database where activation_codes collection exists
    // DB_USER constant containing the DB name
    $activation_codes = $user_db_conn->activation_codes;

    // Query the phase for the given device_id ($id)
    $doc = $activation_codes->findOne(['device_id' => $id]);

    if ($doc !== null && isset($doc['phase'])) {
       $device_phase = $doc['phase'];
       $phase = array("PHASE"=> $doc['phase']); 
   }

   
} catch (Exception $e) {
    // You may log the error if needed
    // error_log("Error fetching device phase: " . $e->getMessage());
    // Default value remains "3PH"
}
?>
