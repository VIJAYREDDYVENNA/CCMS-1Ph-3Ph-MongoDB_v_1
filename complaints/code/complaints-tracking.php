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

$d_name = "";
$data = "";
$count = 0;
$device_list = json_decode($_SESSION["DEVICES_LIST"]);

$send = array();
$send = "";
$user_devices = "";

// use MongoDB collection
$complaintsHistory = $devices_db_conn->complaints_history;

//////////////////////////////////////////////////////////

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $complaint_id = isset($_POST['ID']) ? $_POST['ID'] : $_SESSION['complaint_id'];

    $sts_1 = "";
    $sts_2 = "";
    $sts_3 = "";

    // Sanitize complaint_id
    $complaint_id = htmlspecialchars(trim($complaint_id));

    $limit = 100;
    $offset = 0;

    if (isset($_POST['FETCH_MORE']) && $_POST['FETCH_MORE'] === "MORE") {
        if ($_SESSION['FETCH_TRACK'] == 0 || $_SESSION['complaint_id'] == "") {
            exit();
        }
        $page = $_SESSION['FETCH_TRACK'];

        $page = $page ? intval($page) : 1;
        $limit = $limit ? intval($limit) : 1;
        $offset = ($page - 1) * $limit;

        $_SESSION['FETCH_TRACK'] = $_SESSION['FETCH_TRACK'] + 1;
    } else {
        $_SESSION['FETCH_TRACK'] = 2;
        $_SESSION['complaint_id'] = $complaint_id;
    }

    try {
        // MongoDB query with filter, sort, limit, and skip
        $cursor = $complaintsHistory->find(
            ['complaint_no' => $complaint_id],
            [
                'sort' => ['id' => -1],
                'limit' => $limit,
                'skip'  => $offset
            ]
        );

        $results = iterator_to_array($cursor);

        if (count($results) > 0) {
            foreach ($results as $r) {
                $r['updated_time'] = date("H:i:s d-m-Y", strtotime($r['updated_time']));

                $sts_1 = htmlspecialchars($r['complaint_update']);
                $sts_2 = htmlspecialchars($r['updated_time']);
                $sts_3 = htmlspecialchars($r['updated_by']);
                ?>
                <tr>
                    <td class="body-cell col2"><?php echo $sts_1; ?></td>
                    <td class="body-cell col2"><?php echo $sts_3; ?></td>
                    <td class="body-cell col2"><?php echo $sts_2; ?></td>
                </tr>
                <?php
            }
        } else {
            $_SESSION['FETCH_TRACK'] = 0;
            ?>
            <tr>
                <td class="body-cell col1 text-left text-danger" colspan="5">Records are not Found</td>
            </tr>
            <?php
        }
    } catch (Exception $e) {
        $_SESSION['FETCH_TRACK'] = 0;
        ?>
        <tr>
            <td class="body-cell col1 text-left text-danger" colspan="5">
                Error: <?php echo $e->getMessage(); ?>
            </td>
        </tr>
        <?php
    }
}
?>
