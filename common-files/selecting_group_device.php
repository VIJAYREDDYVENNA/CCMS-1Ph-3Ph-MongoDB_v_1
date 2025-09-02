<?php

$group_id       = strtoupper(trim($group_id));
$selected_phase = strtoupper($_SESSION["SELECTED_PHASE"]);
$total_switch_point = 0;
$device_list = [];
$user_devices = "";

// device name selection (like your client-super-admin-device-names.php)
$list_field = ($role == "SUPERADMIN") ? "s_device_name" : "c_device_name";

if ($group_id == "ALL") {
    // ---------- CASE 1: ALL ----------
    $filter = ['login_id' => (int)$user_login_id];
    if ($selected_phase !== "ALL") {
        $filter['phase'] = $selected_phase;
    }

    $pipeline = [
        ['$match' => $filter],
        [
            '$addFields' => [
                'prefix' => [
                    '$regexFind' => [
                        'input' => '$device_id',
                        'regex' => '^[^0-9]+'
                    ]
                ],
                'numPart' => [
                    '$regexFind' => [
                        'input' => '$device_id',
                        'regex' => '[0-9]+$'
                    ]
                ]
            ]
        ],
        [
            '$addFields' => [
                'prefix'  => '$prefix.match',
                'numPart' => ['$toInt' => '$numPart.match']
            ]
        ],
        ['$sort' => ['prefix' => 1, 'numPart' => 1]]
    ];

    $cursor = $user_db_conn->user_device_list->aggregate($pipeline);

    foreach ($cursor as $doc) {
        $dname = $doc[$list_field] ?? $doc['device_id'];
        $device_list[] = ["D_ID" => $doc['device_id'], "D_NAME" => $dname];
        $user_devices .= "'" . $doc['device_id'] . "',";
        $total_switch_point++;
    }

} else {
    // ---------- CASE 2: GROUP ----------
    $group_col = $user_db_conn->device_selection_group->findOne([
        'login_id' => (int)$user_login_id
    ]);
    $group_by = $group_col['group_by'] ?? "device_group_or_area";

    $filter = [
        'login_id' => (int)$user_login_id,
        $group_by  => $group_id
    ];
    if ($selected_phase !== "ALL") {
        $filter['phase'] = $selected_phase;
    }

    $pipeline = [
        ['$match' => $filter],
        [
            '$addFields' => [
                'prefix' => [
                    '$regexFind' => [
                        'input' => '$device_id',
                        'regex' => '^[^0-9]+'
                    ]
                ],
                'numPart' => [
                    '$regexFind' => [
                        'input' => '$device_id',
                        'regex' => '[0-9]+$'
                    ]
                ]
            ]
        ],
        [
            '$addFields' => [
                'prefix'  => '$prefix.match',
                'numPart' => ['$toInt' => '$numPart.match']
            ]
        ],
        ['$sort' => ['prefix' => 1, 'numPart' => 1]]
    ];

    $cursor = $user_db_conn->user_device_group_view->aggregate($pipeline);

    foreach ($cursor as $doc) {
        $dname = $doc[$list_field] ?? $doc['device_id'];
        $device_list[] = ["D_ID" => $doc['device_id'], "D_NAME" => $dname];
        $user_devices .= "'" . $doc['device_id'] . "',";
        $total_switch_point++;
    }
}

// cleanup trailing comma
$user_devices = rtrim($user_devices, ",");

// ---- optional debugging ----
// var_dump($device_list);
// echo "Devices: " . $user_devices;
// echo "Total: " . $total_switch_point;
?>
