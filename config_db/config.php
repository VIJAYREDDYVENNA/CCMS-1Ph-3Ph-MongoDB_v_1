<?php

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

/*$ip_address = $_SERVER['REMOTE_ADDR'];

if($ip_address=="::1")
{
	define('HOST','localhost');
	define('USERNAME', 'root');
	define('PASSWORD','123456');
	
	define('DB_USER', 'new_ccms_1ph_3ph_userdb');
	define('DB_ALL', 'new_ccms_all');

}
else
{
	define('HOST','103.101.59.93');
	define('USERNAME', 'istlabsonline_db_user');
	define('PASSWORD','istlabsonline_db_pass');
	define('DB_USER', 'ccms_user_details');
	define('DB_ALL', 'ccms_all_devices');
}
$central_db=DB_ALL;
$users_db=DB_USER;*/

require 'MongoDB/vendor/autoload.php';
use MongoDB\Client;
use MongoDB\Exception\ConnectionTimeoutException;
use MongoDB\Operation\Find;

try {

	// $client = new Client("mongodb://Mongoadmin:istl_123456@216.48.182.199:27017/?appname=myapp&maxPoolSize=50");
	$client = new Client("mongodb://Mongoadmin:istl_123456@103.101.59.93:27017/?appname=myapp&maxPoolSize=50");

	$client->ccms_data->command(['ping' => 1]);
	$devices_db_conn = $client->ccms_data;
	$user_db_conn = $client->ccms_user_db;
} catch (Exception $e) {
	http_response_code(500); 
	echo json_encode([
		"status"  => "error",
		"message" => "Database connection failed",

	]);
	exit;
}


?>