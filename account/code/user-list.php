<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

use MongoDB\BSON\Regex;

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];
$user_name = $sessionVars['user_name'];
$user_email = $sessionVars['user_email'];
$user_type = $sessionVars['user_type'];

$response = ["data" => "", "totalPages" => 0, "totalRecords" => 0];

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT);
    $limit = filter_input(INPUT_GET, 'limit', FILTER_SANITIZE_NUMBER_INT);
    $search_user = filter_input(INPUT_GET, 'search_user', FILTER_SANITIZE_STRING);

    $page = $page ? intval($page) : 1;
    $limit = $limit ? intval($limit) : 20;
    $limit = max(1, min(500, $limit)); // Ensure limit is between 1 and 500
    $offset = ($page - 1) * $limit;

    $filter = [];

    if ($role == "SUPERADMIN" && $user_type == "MANAGER") {
        $filter['id'] = ['$ne' => (int)$user_login_id];
        if (!empty($search_user)) {
            $regex = new Regex(preg_quote($search_user), 'i');
            $filter['$or'] = [
                ['user_id' => $regex],
                ['mobile_no' => $regex],
                ['email_id' => $regex],
                ['name' => $regex]
            ];
        }
    } else if ($role == "SUPERADMIN") {
        $filter['account_delete'] = 1;
        $filter['id'] = ['$ne' => $user_login_id];
        $filter['$or'] = [
            ['created_by' => $user_login_id],
            ['role' => ['$ne' => 'SUPERADMIN']]
        ];
        if (!empty($search_user)) {
            $regex = new Regex(preg_quote($search_user), 'i');
            // Add $and condition for the search inside existing $or condition
            $filter['$and'] = [[
                '$or' => [
                    ['user_id' => $regex],
                    ['mobile_no' => $regex],
                    ['email_id' => $regex],
                    ['name' => $regex]
                ]
            ]];
        }
    } else {
        $filter['account_delete'] = '1';
        $filter['created_by'] = $user_login_id;
        if (!empty($search_user)) {
            $regex = new Regex(preg_quote($search_user), 'i');
            $filter['$or'] = [
                ['user_id' => $regex],
                ['mobile_no' => $regex],
                ['email_id' => $regex],
                ['name' => $regex]
            ];
        }
    }

    // Get total record count for pagination
    $totalRecords = $user_db_conn->login_details->countDocuments($filter);
    $totalPages = ceil($totalRecords / $limit);

    $options = [
        'limit' => $limit,
        'skip' => $offset,
        'sort' => ['name' => 1] // optional: sort by name ascending
    ];

    $cursor = $user_db_conn->login_details->find($filter, $options);

    // Construct table headers dynamically based on role
    $data = "<thead>
        <tr>
        <th class='table-header-row-1'>Name</th>
        <th class='table-header-row-1'>User_Id</th>
        <th class='table-header-row-1'>User_Role</th>
        <th class='table-header-row-1'>Mobile</th>
        <th class='table-header-row-1'>Email</th>
        <th class='table-header-row-1'>Status</th>";

    if ($role == "SUPERADMIN" && $user_type == "MANAGER") {
        $data .= "<th class='table-header-row-1'>Login Page</th>
            <th class='table-header-row-1'>Version</th>";
    }

    $data .= "<th class='table-header-row-1 action-column' scope='col'>Action</th>
        </tr>
    </thead> <tbody>";

    $recordCount = 0;
    foreach ($cursor as $row) {
        $recordCount++;
        $status = $row['account_delete'] == 0 ? "<span class='text-danger'>DELETED</span>" : ($row['status'] ?? '');
        $data .= "<tr>
            <td>{$row['name']}</td>
            <td>{$row['user_id']}</td>
            <td>{$row['role']}</td>
            <td>{$row['mobile_no']}</td>
            <td>{$row['email_id']}</td>
            <td>{$status}</td>";

        if ($role == "SUPERADMIN" && $user_type == "MANAGER") {
            $data .= "<td>{$row['client']}</td>
                <td>{$row['client_login']}</td>";
        }

        $data .= "<td>
            <div class='btn-group dropend p-0'>
            <button class='btn p-0' type='button' data-bs-toggle='dropdown' style='border:none'>
            <i class='bi bi-three-dots-vertical'></i>
            </button>
            <ul class='dropdown-menu dropdown-menu-user-list p-0 border-0' style='width:200px'>
            <div class='list-group'>
            <button type='button' onclick='editMainTableDetails(\"{$row['id']}\", \"{$row['mobile_no']}\", \"{$row['name']}\", this)' class='list-group-item list-group-item-action text-primary'>
            <i class='bi bi-pen-fill'></i><strong> Edit</strong>
            </button>
            <button type='button' class='list-group-item list-group-item-action text-danger' onclick='deleteRow(\"{$row['id']}\", \"{$row['mobile_no']}\", \"{$row['name']}\", this)'>
            <i class='bi bi-trash-fill'></i><strong> Delete</strong>
            </button>
            <button type='button' class='list-group-item list-group-item-action text-success-emphasis' onclick='permissionModal(\"{$row['id']}\", \"{$row['mobile_no']}\", \"{$row['name']}\")'>
            <i class='bi bi-shield-lock-fill pe-1'></i><strong> Permissions</strong>
            </button>
            <button type='button' class='list-group-item list-group-item-action' onclick='managing_devices(\"{$row['id']}\", \"{$row['mobile_no']}\", \"{$row['name']}\")'>
            <i class='bi bi-cpu pe-1'></i><strong> Managing Devices</strong>
            </button>
            <button type='button' class='list-group-item list-group-item-action text-info' onclick='device_group(\"{$row['id']}\", \"{$row['mobile_no']}\", \"{$row['name']}\")'>
            <i class='bi bi-person-lines-fill'></i><strong>Group/Area View</strong>
            </button>
            <button type='button' class='list-group-item list-group-item-action text-warning' onclick='menu_permission(\"{$row['id']}\", \"{$row['mobile_no']}\", \"{$row['name']}\")'>
            <i class='bi bi-list pe-1'></i><strong>Menu Permissions</strong>
            </button>
             <button type='button' class='list-group-item list-group-item-action text-primary' onclick='account_action(\"{$row['id']}\", \"{$row['mobile_no']}\", \"{$row['name']}\")'>
            <i class='bi bi-person-lines-fill pe-1'></i><strong>Account Action</strong>
            </button>
            </div>
            </ul>
            </div>
            </td>
            </tr>";
    }

    $data .= "</tbody>";

    // Enhanced response with pagination info
    $response = [
        'data' => $data, 
        'totalPages' => $totalPages,
        'totalRecords' => $totalRecords,
        'currentPage' => $page,
        'itemsPerPage' => $limit,
        'recordsOnThisPage' => $recordCount
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
}

function sanitize_input($data, $conn)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data; // Not needed for MongoDB anymore
}
?>