<?php
require("../config_db/config.php");

// Initialize variables
$mobile_no = "";
$e_mail = "";
$user_id = "";
$user_name = "";
$role = "";
$user_type = "";
$client = "";
$status = "INACTIVE";
$client_login_verion = "";
$redirect = false;
$count = 0;
$login_id = 0;
$delete_status = 0;
$credentials_check = false;

// Input cleanup
$user_login_id = trim(strtolower($user_login_id));
$password = trim($password);


$login_collection = $user_db_conn->login_details; 


$r = $login_collection->findOne([
    '$or' => [
        ['mobile_no' => (int)$user_login_id],     // try as int
        ['email_id'  => (string)$user_login_id],  // emails are strings
        ['user_id'   => (string)$user_login_id]   // user_id is string
    ]
]);

if ($r) {
    // Check password
    if (password_verify($password, $r['password'])) {
        $mobile_no = trim(strtolower($r['mobile_no']));
        $e_mail = trim(strtolower($r['email_id']));
        $user_id = $r['user_id'];
        $user_name = $r['name'];
        $role = $r['role'];
        $user_type = $r['user_type'];
        $client = $r['client'];
        $status = $r['status'];
        $client_login_verion = $r['client_login'];
        $login_id = $r['id']; // MongoDB uses ObjectId
        $delete_status = $r['account_delete'];
        $credentials_check = true;
    } else {
    	

        $GLOBALS['login_error'] = "Invalid Credentials";
    }
} else {
    $GLOBALS['login_error'] = "Invalid Credentials123";
}

if ($credentials_check) {
    if ($delete_status === 1) {
        if (strtoupper($status) === "ACTIVE") {
            // ✅ Session Variables
            $_SESSION['mobile_no']   = $mobile_no;
            $_SESSION['login_user_id'] = $user_id;
            $_SESSION['user_name']   = $user_name;
            $_SESSION['user_email']  = $e_mail;
            $_SESSION['role']        = $role;
            $_SESSION['user_type']   = $user_type;
            $_SESSION['client']      = $client;
            $_SESSION['status']      = $status;
            $_SESSION['client_login'] = $client_login_verion;
            $_SESSION['user_login_id'] = (string)$login_id;

            echo "<script> localStorage.setItem('client_type', '$login_path'); </script>";

            activity_log($user_db_conn, $mobile_no, $e_mail, (string)$login_id);

            $device_list = [];
            $group_list = [];

            require("../common-files/client-super-admin-device-names.php");

            // ✅ Get devices
            $user_devices = $user_db_conn->user_device_list->find(
                ['login_id' => (int)$login_id],
                [
                    'sort' => [
                        'device_id' => 1  
                    ]
                ]
            );


            if ($user_devices) {
                foreach ($user_devices as $doc) {
                    $device_list[] = [
                        "D_ID"   => $doc['device_id'],
                        "D_NAME" => $doc['s_device_name'] ?? $doc['c_device_name'] ?? ""
                    ];
                }

                $redirect = true;
            } else {
                if ($role == "SUPERADMIN" || $role == "ADMIN") {
                    fetch_menu_permissions((string)$login_id, $user_db_conn);
                    header("location:device-list.php");
                    exit();
                } else {
                    $GLOBALS['login_error'] = "Please contact your admin.";
                }
            }



            // ✅ Group selection
            $group_col = $user_db_conn->device_selection_group->findOne([
                'login_id' => (int)$login_id
            ]);

            $group_by_column = $group_col['group_by'] ?? "device_group_or_area";

// Second query: distinct group values from device_list_by_group
            $groups = $user_db_conn->device_list_by_group->distinct(
                $group_by_column,
                ['login_id' => (int)$login_id]
            );

            $group_list = [];
            foreach ($groups as $g) {
                $group_list[] = ["GROUP" => strtoupper($g)];
            }

            $_SESSION["SELECTED_PHASE"] = "ALL";
            $_SESSION["DEVICES_LIST"] = json_encode($device_list);
            $_SESSION["GROUP_LIST"] = json_encode($group_list);

            if ($redirect) {
                $_SESSION["login_time_stamp"] = time();

                fetch_menu_permissions($login_id, $user_db_conn);

                if ($login_path == "0") {
                    header("location:index.php");
                } else {
                    header("location:../$client_login_verion/index.php");
                }
                exit();
            }
        } else {
            $GLOBALS['login_error'] = "Your account is Inactive.";
        }
    } else {
        $GLOBALS['login_error'] = "Your account has been deleted.";
    }
}

// ✅ Fetch menu permissions from MongoDB
function fetch_menu_permissions($login_id, $user_db_conn)
{ 

    $doc = $user_db_conn->menu_permissions_list->findOne(['login_id' => (int)$login_id]);

    if ($doc) {
        $permissions = [];
        $permission_fields = [
            'device_dashboard', 'dashboard', 'devices_list', 'onoff_control',
            'gis_map', 'data_report', 'energy_consumption', 'thresholdsettings',
            'group_creation', 'location_update', 'notification_settings',
            'iotsettings', 'pending_actions', 'add_new_electrician_devices',
            'phase_alerts', 'alerts', 'notification_mesages', 'graphs',
            'up_down_time', 'glowing_time', 'user_activity', 'download',
            'complaints', 'office_use', 'users_list'
        ];
        foreach ($permission_fields as $key) {
            if (!empty($doc[$key]) && $doc[$key] == 1) {
                $permissions[] = $key;
            }
        }
        if (count($permissions) > 0) {
            $_SESSION['menu_permission_variables'] = implode(", ", $permissions);
        }

        
    }
}

// ✅ Log activity in MongoDB
function activity_log($user_db_conn, $mobile_no, $mail, $user_id)
{
    include('../account/code/client-login-details.php');

    $ip_address = $_SERVER['REMOTE_ADDR'];
    if ($ip_address != "::1") {
        $device_info = $_SERVER['HTTP_USER_AGENT'];

        $user_db_conn->user_login_activity->insertOne([
            'user_id' => $user_id,
            'mobile' => $mobile_no,
            'email' => $mail,
            'activity' => 'LOGIN',
            'ip_address' => $ip_address,
            'country' => $country,
            'subdivision' => $subdivision,
            'city' => $city,
            'isp_name' => $org,
            'device_details' => $device_info,
            'date_time' => $date
        ]);
    }
}
?>
