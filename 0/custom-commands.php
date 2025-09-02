<?php
require_once 'config-path.php';
require_once '../session/session-manager.php';
require_once BASE_PATH . 'config_db/config.php';
SessionManager::checkSession();

$send = ["status" => "", "message" => ""];

?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

<head>
    <title>Custom-Commands</title>
    <?php
    include(BASE_PATH . "assets/html/start-page.php");
    ?>
</head>

<body>
    <div class="d-flex flex-column flex-shrink-0 p-3 main-content">
        <div class="container-fluid">
            <div class="row d-flex align-items-center">
                <div class="col-12 p-0">
                    <p class="m-0 p-0"><span class="text-body-tertiary">Pages / </span><span>Custom Commands</span></p>
                </div>
            </div>
            <?php
            include(BASE_PATH."dropdown-selection/group-device-list.php");
            ?>
            <div class="row">
                <div class="col-sm-3 p-0"></div>
                <div class="col-sm-6 p-0">
                    <div class="card mt-3">
                        <div class="card-header bg-primary bg-opacity-25 fw-bold">
                            <span class="me-2">Custom Commands</span>
                            <a tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-title="Info" data-bs-content="Custom Commands">
                                <i class="bi bi-info-circle"></i>
                            </a>
                        </div>
                        <div class="card-body row">
                            <form class="col-md-12" id="new-client-data" method="post">
                                <div class="pb-2">
                                    <label for="command" class="form-label">Enter Command</label>
                                    <input type="text" class="form-control" id="command" name="command" placeholder="Enter Command">
                                </div>

                                <!-- Error/Success message display -->
                                <div class="mt-2" id="response-message"></div>

                                <div class="d-flex justify-content-center align-items-center mt-2">
                                    <button type="button" class="btn btn-primary" onclick="submitcommands()">Submit</button>
                                </div>
                            </form>
                        </div>

                        <!-- <div class="card-footer d-flex justify-content-between align-items-center">
                            <div class="w-100 text-center">
                                <div class="mt-1 text-start">

                                </div>
                            </div>
                        </div> -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>



<script src="<?php echo BASE_PATH; ?>js_modal_scripts/popover.js"></script>
<script src="<?php echo BASE_PATH; ?>assets/js/sidebar-menu.js"></script>
<script src="<?php echo BASE_PATH; ?>assets/js/project/custom-command.js"></script>

<?php
include(BASE_PATH . "assets/html/body-end.php");
include(BASE_PATH . "assets/html/html-end.php");
?>
</body>

</html>