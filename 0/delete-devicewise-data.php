<?php
require_once 'config-path.php';
require_once '../session/session-manager.php';
SessionManager::checkSession();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

<head>
    <title>Delete Device Wise Data</title>
    <?php
    include(BASE_PATH . "assets/html/start-page.php");
    ?>
    <div class="d-flex flex-column flex-shrink-0 p-3 main-content ">
        <div class="container-fluid">
            <div class="row d-flex align-items-center">
                <div class="col-12 p-0">
                    <p class="m-0 p-0"><span class="text-body-tertiary">Pages / </span><span>Delete Device Wise Data</span></p>
                </div>
            </div>
            <?php
            include(BASE_PATH . "dropdown-selection/group-device-list.php");
            ?>
            <div class="row mt-3">
                <div class="col-12 col-md-6">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3 text-danger">⚠️ Delete Device Data</h5>
                            <p class="text-muted mb-3">This will permanently delete ALL data for the selected device from ALL collections</p>
                            <button class="btn btn-danger px-4" id="backup-excel" onclick="delete_devicedata('delete-data')">
                                <i class="bi bi-trash me-2"></i> Delete Data
                            </button>
                            <div class="mt-2">
                                <small class="text-danger">⚠️ This action cannot be undone</small>
                            </div>
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

        function delete_devicedata(parameter) {
            // Check if device ID is selected
            if (!device_id || device_id === "") {
                alert("Please select a device before deleting data.");
                return;
            }

            // Strong confirmation before deleting
            let confirmMessage = `⚠️ WARNING: This action will PERMANENTLY DELETE ALL DATA for device ${device_id} from ALL collections in the database.\n\nThis action CANNOT be undone!\n\nAre you absolutely sure you want to proceed?`;

            if (!confirm(confirmMessage)) {
                return; // User cancelled the operation
            }

            // Second confirmation for extra safety
            let secondConfirm = `This is your final warning!\n\nDevice ID: ${device_id}\nAction: DELETE ALL DATA\n\nType "DELETE" to confirm this destructive action:`;
            let userInput = prompt(secondConfirm);

            if (userInput !== "DELETE") {
                alert("Deletion cancelled. Data remains safe.");
                return;
            }

            // Show loading indicator
            $("#pre-loader").css('display', 'block');

            // Disable the delete button to prevent multiple clicks
            $("#backup-excel").prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i> Deleting...');

            // Create the data object to send
            let requestData = {
                D_ID: device_id,
                PARAMETER: parameter
            };

            // Using jQuery AJAX to handle the deletion
            $.ajax({
                url: '../data-backup/code/delete-device-data.php', // Update this path as needed
                type: 'POST',
                data: requestData,
                dataType: 'json',
                timeout: 600000, // 10 minutes timeout for large deletions
                success: function(response) {
                    if (response.success) {
                        // Show simple success message
                        let successMessage = `✅ Deletion Completed Successfully!\n\n`;
                        successMessage += `Device ID: ${device_id}\n`;
                        successMessage += `Total Documents Deleted: ${response.details.total_deleted}\n`;
                        successMessage += `Collections Processed: ${response.details.collections_processed}\n`;
                        successMessage += `Completed At: ${response.details.deletion_completed_at}\n`;

                        if (response.details.errors && response.details.errors.length > 0) {
                            successMessage += `\n⚠️ Note: ${response.details.errors.length} collections had errors during deletion.`;
                        }

                        alert(successMessage);

                        // Commented out - detailed breakdown not shown for now
                        /*
                        // Optionally show detailed breakdown
                        if (response.details.collection_details && confirm("Would you like to see detailed breakdown by collection?")) {
                            let detailMessage = "Deletion Details by Collection:\n\n";
                            response.details.collection_details.forEach(function(collection) {
                                detailMessage += `${collection.collection_name}: `;
                                if (collection.status === 'success') {
                                    detailMessage += `${collection.documents_deleted} documents deleted\n`;
                                } else if (collection.status === 'no_data') {
                                    detailMessage += `No data found\n`;
                                } else {
                                    detailMessage += `Error: ${collection.error}\n`;
                                }
                            });
                            alert(detailMessage);
                        }
                        */

                    } else {
                        alert("❌ Deletion Failed: " + (response.error || "Unknown error occurred"));
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = "❌ Deletion Failed!\n\n";

                    if (status === 'timeout') {
                        errorMessage += "The deletion operation timed out. This might be due to a large amount of data.\n";
                        errorMessage += "Some data may have been deleted. Please check the database and try again if needed.";
                    } else {
                        try {
                            let response = JSON.parse(xhr.responseText);
                            if (response && response.error) {
                                errorMessage += response.error;
                            } else {
                                errorMessage += `Error: ${error}\nStatus: ${status}`;
                            }
                        } catch (e) {
                            errorMessage += `Error: ${error}\nStatus: ${status}`;
                        }
                    }

                    alert(errorMessage);
                },
                complete: function() {
                    // Hide loading indicator and restore button
                    $("#pre-loader").css('display', 'none');
                    $("#backup-excel").prop('disabled', false).html('<i class="bi bi-trash me-2"></i> Delete Data');
                }
            });
        }

        // Optional: Add a function to show deletion progress (if you want to implement real-time updates)
        function showDeletionProgress(device_id) {
            // This could be used with Server-Sent Events or WebSockets for real-time progress
            // For now, it's just a placeholder
            console.log(`Monitoring deletion progress for device: ${device_id}`);
        }
    </script>
    <?php
    include(BASE_PATH . "assets/html/body-end.php");
    include(BASE_PATH . "assets/html/html-end.php");
    ?>