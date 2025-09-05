<?php
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$login_error="";

include("config-path.php");
require_once '../session/session-manager.php';

SessionManager::startSession();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $user_login_id = strtolower($_POST['userid']);
    $password = $_POST['password']; 
    SessionManager::login("0", $user_login_id,  $password);    

}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">
<head>
    <title>Login</title>  
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="generator" content="Hugo 0.122.0">
    <link href="<?php echo BASE_PATH?>assets/css/bootstrap.css" rel="stylesheet">
    <link href="<?php echo BASE_PATH?>assets/css/sidebars.css" rel="stylesheet">
    <link href="<?php echo BASE_PATH?>assets/css/istl-styles.css" rel="stylesheet">
    <link href="<?php echo BASE_PATH?>assets/css/login-styles.css" rel="stylesheet">
    <script src="<?php echo BASE_PATH?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_PATH?>assets/js/sidebars.js"></script>
    <script src="<?php echo BASE_PATH?>assets/js/color-modes-login.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" ></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.9.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript">
        localStorage.removeItem("Devive_ID_Selection");
        localStorage.removeItem("SELECTED_ID");
        localStorage.removeItem("GroupName");
        localStorage.removeItem("GroupNameValue");
        localStorage.removeItem("SelectedPhase");
    </script>

    <?php
    include(BASE_PATH."assets/html/body-start.php");
    include(BASE_PATH."assets/icons-svg/icons.php");
    // include(BASE_PATH."assets/html/theme-selection.php");
     
    ?>
    <div class="background">
        <?php
        include(BASE_PATH."login/login-card.php");
        include(BASE_PATH."login/registration-toast.php");

        ?>
    </div>
</div>
<div class="dropdown position-fixed bottom-0 end-0 mb-3 me-3 bd-mode-toggle">
	<button class="btn btn-primary py-2 dropdown-toggle d-flex align-items-center" id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" aria-label="Toggle theme (auto)">
		<svg class="bi my-1 theme-icon-active" width="1em" height="1em"><use href="#circle-half"></use></svg>
		<span class="visually-hidden" id="bd-theme-text">Toggle theme</span>
	</button>
	<ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="bd-theme-text">
		<li>
			<button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="light" aria-pressed="false">
				<svg class="bi me-2 opacity-50" width="1em" height="1em"><use href="#sun-fill"></use></svg>
				Light
				<svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
			</button>
		</li>
		<li>
			<button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="dark" aria-pressed="false">
				<svg class="bi me-2 opacity-50" width="1em" height="1em"><use href="#moon-stars-fill"></use></svg>
				Dark
				<svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
			</button>
		</li>
		<li>
			<button type="button" class="dropdown-item d-flex align-items-center active" data-bs-theme-value="auto" aria-pressed="true">
				<svg class="bi me-2 opacity-50" width="1em" height="1em"><use href="#circle-half"></use></svg>
				Auto
				<svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
			</button>
		</li>
	</ul>
</div>
<?php
include(BASE_PATH."login/forgot-password.php");
?>
</body>
<script src="<?php echo BASE_PATH;?>assets/js/project/preloader.js"></script>
<script src="<?php echo BASE_PATH;?>assets/js/project/password-show-hide.js"></script>
<script src="<?php echo BASE_PATH;?>assets/js/project/password-send.js"></script>
</html>
