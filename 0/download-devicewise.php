<?php
require_once 'config-path.php';
require_once '../session/session-manager.php';
SessionManager::checkSession();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

<head>
    <title>Download Device Wise Data</title>
    <?php
    include(BASE_PATH . "assets/html/start-page.php");
    ?>
    <div class="d-flex flex-column flex-shrink-0 p-3 main-content ">
        <div class="container-fluid">
            <div class="row d-flex align-items-center">
                <div class="col-12 p-0">
                    <p class="m-0 p-0"><span class="text-body-tertiary">Pages / </span><span>Download Device Wise Data</span></p>
                </div>
            </div>
            <?php
            include(BASE_PATH . "dropdown-selection/group-device-list.php");
            ?>
            <div class="row mt-3">
                <div class="col-12 col-md-6">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">Download Device Data</h5>
                            <button class="btn btn-primary px-4" id="backup-excel" onclick="data_backup('backup-excel')">
                                <i class="bi bi-download me-2"></i> Backup in Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    </main>
    <script src="<?php echo BASE_PATH; ?>assets/js/sidebar-menu.js"></script>
    <script>
        var group_name = localStorage.getItem("GroupNameValue")
        if (group_name == "" || group_name == null) {
            group_name = "ALL";
        }

        let device_id = localStorage.getItem("SELECTED_ID");
        if (!device_id) {
            device_id = document.getElementById('device_id').value;
        }

        let device_id_list = document.getElementById('device_id');
        if (device_id_list) {
            device_id_list.addEventListener('change', function() {
                device_id = document.getElementById('device_id').value;
            });
        }

        function data_backup(parameter) {
            // Check if device ID is selected
            if (!device_id || device_id === "") {
                alert("Please select a device before downloading backup.");
                return;
            }

            // Confirmation before downloading
            let fileType = parameter === "backup-sql" ? "SQL" : "CSV";
            let confirmMessage = `Are you sure you want to download ${fileType} backup for device ${device_id}?`;

            if (!confirm(confirmMessage)) {
                return; // User cancelled the operation
            }

            // Show loading indicator
            $("#pre-loader").css('display', 'block');

            // Determine the URL based on parameter 
            // let url = parameter === "backup-sql" ?
            //     '../data-backup/code/data-backup.php' :
            //     '../data-backup/code/excel-backup.php';
            let url = '../data-backup/code/download-device-wise-data.php';
            // Create the data object to send
            let requestData = {
                D_ID: device_id,
                PARAMETER: parameter
            };

            // Using jQuery AJAX with xhr to handle binary data
            $.ajax({
                url: url,
                type: 'POST',
                data: requestData,
                xhrFields: {
                    responseType: 'blob' // Important for handling binary data
                },
                success: function(data, status, xhr) {
                    // Get the filename from the Content-Disposition header if available
                    let filename = "backup_" + device_id + "_" + new Date().toISOString().replace(/[:.]/g, "-") +
                        (parameter === "backup-sql" ? ".sql" : ".csv");

                    let contentDisposition = xhr.getResponseHeader('Content-Disposition');
                    if (contentDisposition) {
                        let filenameMatch = contentDisposition.match(/filename="(.+)"/);
                        if (filenameMatch && filenameMatch[1]) {
                            filename = filenameMatch[1];
                        }
                    }

                    // Create a download link and trigger it
                    let blobUrl = window.URL.createObjectURL(data);
                    let link = document.createElement('a');
                    link.href = blobUrl;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();

                    // Clean up
                    window.URL.revokeObjectURL(blobUrl);
                    document.body.removeChild(link);

                    // Show success message
                    setTimeout(function() {
                        alert(`${fileType} backup for ${device_id} has been successfully downloaded.`);
                    }, 500);
                },
                error: function(xhr, status, error) {
                    // Parse error response if possible
                    let errorMessage = "Download failed. Please try again.";

                    try {
                        let response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMessage = response.message;
                        }
                    } catch (e) {
                        // If parsing fails, use the default message
                    }

                    alert("Error: " + errorMessage);
                },
                complete: function() {
                    // Hide loading indicator when done (success or error)
                    $("#pre-loader").css('display', 'none');
                }
            });
        }
    </script>
    <?php
    include(BASE_PATH . "assets/html/body-end.php");
    include(BASE_PATH . "assets/html/html-end.php");
    ?>