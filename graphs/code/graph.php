<?php
require_once '../../base-path/config-path.php';
require_once BASE_PATH_1 . 'config_db/config.php';
require_once BASE_PATH_1 . 'session/session-manager.php';

SessionManager::checkSession();
$sessionVars = SessionManager::SessionVariables();

$mobile_no = $sessionVars['mobile_no'];
$user_id = $sessionVars['user_id'];
$role = $sessionVars['role'];
$user_login_id = $sessionVars['user_login_id'];
$user_name = $sessionVars['user_name'];
$user_email = $sessionVars['user_email'];
$permission_check = 0;

$year = "";
$month = "";
$day = "";
$phase = array();	

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['D_ID'])) {
    $device_id = filter_var($_POST['D_ID'], FILTER_SANITIZE_STRING);
    $type = filter_var($_POST['TYPE'], FILTER_SANITIZE_STRING);
    $paramter = filter_var($_POST['PARAMTER'], FILTER_SANITIZE_STRING);

    $id = $device_id;
    $send = array();
    $db = strtolower($id);

    $year = $month = $day = null;
    if (!empty($_POST['DATE'])) {
        $dateParts = explode('-', $_POST['DATE']);
        if (count($dateParts) === 3) {
            $year = (int)$dateParts[0];
            $month = (int)$dateParts[1];
            $day = (int)$dateParts[2];
        }
    }
    include_once("../../common-files/fetch-device-phase.php");
    
    try {
        $collection = $devices_db_conn->voltage_current_graph;

        $match = ['device_id' => $device_id];

        switch ($type) {

            case "YEAR":
                if (in_array($paramter, ['VOLTAGE', 'CURRENT'])) {
                    $valueFields = $paramter === 'VOLTAGE'
                        ? ['v1' => '$voltage_ph1', 'v2' => '$voltage_ph2', 'v3' => '$voltage_ph3']
                        : ['v1' => '$current_ph1', 'v2' => '$current_ph2', 'v3' => '$current_ph3'];

                    $pipeline = [
                        ['$match' => $match],
                        // Shift UTC → IST
                        ['$addFields' => [
                            'dateIST' => [
                                '$dateAdd' => [
                                    'startDate' => '$date_time',
                                    'unit' => 'minute',
                                    'amount' => 330 // +5:30 offset
                                ]
                            ]
                        ]],
                        // Extract year
                        ['$addFields' => ['yearInt' => ['$year' => '$dateIST']]],
                        ['$group' => [
                            '_id' => '$yearInt',
                            'v1'  => ['$max' => $valueFields['v1']],
                            'v2'  => ['$max' => $valueFields['v2']],
                            'v3'  => ['$max' => $valueFields['v3']]
                        ]],
                        ['$match' => ['_id' => ['$gt' => 2018]]],
                        ['$sort' => ['_id' => 1]]
                    ];
                }
                break;

            case "MONTHS":
                if (in_array($paramter, ['VOLTAGE', 'CURRENT'])) {
                    $valueFields = $paramter === 'VOLTAGE'
                        ? ['v1' => '$voltage_ph1', 'v2' => '$voltage_ph2', 'v3' => '$voltage_ph3']
                        : ['v1' => '$current_ph1', 'v2' => '$current_ph2', 'v3' => '$current_ph3'];

                    $pipeline = [
                        ['$match' => $match],
                        ['$addFields' => [
                            'dateIST' => [
                                '$dateAdd' => [
                                    'startDate' => '$date_time',
                                    'unit' => 'minute',
                                    'amount' => 330
                                ]
                            ]
                        ]],
                        ['$addFields' => [
                            'yearInt' => ['$year' => '$dateIST'],
                            'monthInt' => ['$month' => '$dateIST']
                        ]],
                        ['$match' => ['yearInt' => (int)$year]],
                        ['$group' => [
                            '_id' => '$monthInt',
                            'v1'  => ['$max' => $valueFields['v1']],
                            'v2'  => ['$max' => $valueFields['v2']],
                            'v3'  => ['$max' => $valueFields['v3']]
                        ]],
                        ['$sort' => ['_id' => 1]]
                    ];
                }
                break;

            case "DAYS":
                if (in_array($paramter, ['VOLTAGE', 'CURRENT'])) {
                    $valueFields = $paramter === 'VOLTAGE'
                        ? ['v1' => '$voltage_ph1', 'v2' => '$voltage_ph2', 'v3' => '$voltage_ph3']
                        : ['v1' => '$current_ph1', 'v2' => '$current_ph2', 'v3' => '$current_ph3'];

                    $pipeline = [
                        ['$match' => $match],
                        ['$addFields' => [
                            'dateIST' => [
                                '$dateAdd' => [
                                    'startDate' => '$date_time',
                                    'unit' => 'minute',
                                    'amount' => 330
                                ]
                            ]
                        ]],
                        ['$addFields' => [
                            'yearInt' => ['$year' => '$dateIST'],
                            'monthInt' => ['$month' => '$dateIST'],
                            'dayInt' => ['$dayOfMonth' => '$dateIST']
                        ]],
                        ['$match' => [
                            'yearInt' => (int)$year,
                            'monthInt' => (int)$month
                        ]],
                        ['$group' => [
                            '_id' => '$dayInt',
                            'v1'  => ['$max' => $valueFields['v1']],
                            'v2'  => ['$max' => $valueFields['v2']],
                            'v3'  => ['$max' => $valueFields['v3']]
                        ]],
                        ['$sort' => ['_id' => 1]]
                    ];
                }
                break;

            case "DAY":
                if (in_array($paramter, ["VOLTAGE", "CURRENT"])) {
                    $fieldNames = $paramter === "VOLTAGE"
                        ? ['v1' => 'voltage_ph1', 'v2' => 'voltage_ph2', 'v3' => 'voltage_ph3']
                        : ['v1' => 'current_ph1', 'v2' => 'current_ph2', 'v3' => 'current_ph3'];

                    $pipeline = [
                        ['$match' => $match],
                        ['$addFields' => [
                            'dateIST' => [
                                '$dateAdd' => [
                                    'startDate' => '$date_time',
                                    'unit' => 'minute',
                                    'amount' => 330
                                ]
                            ]
                        ]],
                        ['$addFields' => [
                            'yearInt' => ['$year' => '$dateIST'],
                            'monthInt' => ['$month' => '$dateIST'],
                            'dayInt' => ['$dayOfMonth' => '$dateIST']
                        ]],
                        ['$match' => [
                            'yearInt' => (int)$year,
                            'monthInt' => (int)$month,
                            'dayInt' => (int)$day
                        ]],
                        ['$project' => [
                            'dateIST' => 1,
                            'v1'   => '$' . $fieldNames['v1'],
                            'v2'   => '$' . $fieldNames['v2'],
                            'v3'   => '$' . $fieldNames['v3']
                        ]],
                        ['$sort' => ['dateIST' => 1]]
                    ];
                }
                break;

            case "LATEST":
                if (in_array($paramter, ['VOLTAGE', 'CURRENT'])) {
                    $fieldNames = $paramter === 'VOLTAGE'
                        ? ['v1' => 'voltage_ph1', 'v2' => 'voltage_ph2', 'v3' => 'voltage_ph3']
                        : ['v1' => 'current_ph1', 'v2' => 'current_ph2', 'v3' => 'current_ph3'];

                    $options = [
                        'sort' => ['date_time' => -1],
                        'limit' => 60
                    ];
                    $cursor = $collection->find($match, $options);
                    $temp = iterator_to_array($cursor);
                    usort($temp, function($a, $b) {
                        return $a['date_time']->toDateTime() <=> $b['date_time']->toDateTime();
                    });
                    foreach ($temp as $doc) {
                        $send[] = array(
                            'date' => $doc['date_time']->toDateTime()->modify('+5 hours 30 minutes')->format('H:i M d'),
                            'v_1' => $doc[$fieldNames['v1']] ?? null,
                            'v_2' => $doc[$fieldNames['v2']] ?? null,
                            'v_3' => $doc[$fieldNames['v3']] ?? null
                        );
                    }
                    echo json_encode(array($send, $phase));
                    exit;
                }
                break;
        }

        if (isset($pipeline)) {
            $result = $collection->aggregate($pipeline);
            foreach ($result as $doc) {
                $dateLabel = '';
                if ($type == 'YEAR' || $type == 'MONTHS' || $type == 'DAYS') {
                    $dateLabel = $doc->_id;
                } elseif ($type == 'DAY') {
                    $dateLabel = $doc->dateIST->toDateTime()->format('H:i M d');
                }
                $send[] = [
                    'date' => $dateLabel,
                    'v_1' => $doc->v1 ?? null,
                    'v_2' => $doc->v2 ?? null,
                    'v_3' => $doc->v3 ?? null
                ];
            }
        }
    } catch (Exception $e) {
        $send = [];
    }

    echo json_encode(array($send,$phase));
}
