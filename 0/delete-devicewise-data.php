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
                            <button class="btn btn-danger px-4" id="backup-excel" onclick="showDeleteConfirmationModal()">
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
    </div>
</div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteConfirmationModalLabel">⚠️ Confirm Data Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" role="alert">
                        <strong>WARNING:</strong> This action will PERMANENTLY DELETE ALL DATA for device <strong id="modalDeviceId"></strong> from ALL collections in the database.
                    </div>
                    <p class="text-danger fw-bold">This action CANNOT be undone!</p>
                    <hr>
                    <div class="mb-3">
                        <label for="confirmDeviceId" class="form-label">To confirm deletion, please re-enter the Device ID:</label>
                        <input type="text" class="form-control" id="confirmDeviceId" placeholder="Enter Device ID">
                        <div class="form-text">Device ID must match exactly to proceed with deletion.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn" onclick="proceedWithDeletion()">
                        <i class="bi bi-trash me-2"></i> Delete All Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">✅ Deletion Successful</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="successModalBody">
                    <!-- Success message will be populated here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">❌ Deletion Failed</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="errorModalBody">
                    <!-- Error message will be populated here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="noDataToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-warning text-dark">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong class="me-auto">No Data Found</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                No data found for this device ID, deletion cannot proceed
            </div>
        </div>

        <div id="noPermissionToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-danger text-white">
                <i class="bi bi-shield-exclamation me-2"></i>
                <strong class="me-auto">Permission Denied</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                Deletion permission is denied for this device ID
            </div>
        </div>

        <div id="deviceMismatchToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-danger text-white">
                <i class="bi bi-x-circle-fill me-2"></i>
                <strong class="me-auto">Device ID Mismatch</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                Device ID not matched, deletion cannot proceed
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

        function showDeleteConfirmationModal() {
            // Check if device ID is selected
            if (!device_id || device_id === "") {
                showToast('deviceMismatchToast', 'Please select a device before deleting data.');
                return;
            }

            // Set device ID in modal
            document.getElementById('modalDeviceId').textContent = device_id;
            document.getElementById('confirmDeviceId').value = '';
            
            // Show the modal
            var modal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
            modal.show();
        }

        function proceedWithDeletion() {
            const enteredDeviceId = document.getElementById('confirmDeviceId').value.trim();
            
            // Validate entered device ID
            if (enteredDeviceId !== device_id) {
                // Hide the modal first
                var modal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmationModal'));
                modal.hide();
                
                // Show toast notification
                setTimeout(function() {
                    showToast('deviceMismatchToast');
                }, 300);
                return;
            }

            // Hide the confirmation modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmationModal'));
            modal.hide();

            // Proceed with deletion
            delete_devicedata('delete-data');
        }

        function delete_devicedata(parameter) {
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
                url: '../data-backup/code/delete-device-data.php',
                type: 'POST',
                data: requestData,
                dataType: 'json',
                timeout: 600000, // 10 minutes timeout for large deletions
                success: function(response) {
                    if (response.success) {
                        // Handle different success scenarios
                        if (response.no_data) {
                            showToast('noDataToast');
                        } else {
                            // Show success modal
                            let successMessage = `<div class="alert alert-success" role="alert">`;
                            successMessage += `<h6>Deletion Completed Successfully!</h6>`;
                            successMessage += `<hr>`;
                            successMessage += `<p><strong>Device ID:</strong> ${device_id}</p>`;
                            successMessage += `<p><strong>Total Documents Deleted:</strong> ${response.details.total_deleted}</p>`;
                            successMessage += `<p><strong>Collections Processed:</strong> ${response.details.collections_processed}</p>`;
                            successMessage += `<p><strong>Completed At:</strong> ${response.details.deletion_completed_at}</p>`;

                            if (response.details.errors && response.details.errors.length > 0) {
                                successMessage += `<div class="alert alert-warning mt-2">⚠️ Note: ${response.details.errors.length} collections had errors during deletion.</div>`;
                            }
                            successMessage += `</div>`;

                            document.getElementById('successModalBody').innerHTML = successMessage;
                            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                            successModal.show();
                        }
                    } else {
                        // Handle specific error types
                        if (response.no_permission) {
                            showToast('noPermissionToast');
                        } else if (response.no_data) {
                            showToast('noDataToast');
                        } else {
                            showErrorModal("Deletion Failed: " + (response.error || "Unknown error occurred"));
                        }
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = "Deletion Failed!<br><br>";

                    if (status === 'timeout') {
                        errorMessage += "The deletion operation timed out. This might be due to a large amount of data.<br>";
                        errorMessage += "Some data may have been deleted. Please check the database and try again if needed.";
                    } else {
                        try {
                            let response = JSON.parse(xhr.responseText);
                            if (response && response.error) {
                                errorMessage += response.error;
                            } else {
                                errorMessage += `Error: ${error}<br>Status: ${status}`;
                            }
                        } catch (e) {
                            errorMessage += `Error: ${error}<br>Status: ${status}`;
                        }
                    }

                    showErrorModal(errorMessage);
                },
                complete: function() {
                    // Hide loading indicator and restore button
                    $("#pre-loader").css('display', 'none');
                    $("#backup-excel").prop('disabled', false).html('<i class="bi bi-trash me-2"></i> Delete Data');
                }
            });
        }

        function showErrorModal(message) {
            document.getElementById('errorModalBody').innerHTML = `<div class="alert alert-danger">${message}</div>`;
            var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            errorModal.show();
        }

        function showToast(toastId, customMessage = null) {
            const toastElement = document.getElementById(toastId);
            if (customMessage) {
                const toastBody = toastElement.querySelector('.toast-body');
                toastBody.textContent = customMessage;
            }
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
        }

        // Clear the input when modal is hidden
        document.getElementById('deleteConfirmationModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('confirmDeviceId').value = '';
        });
    </script>
    <?php
    include(BASE_PATH . "assets/html/body-end.php");
    include(BASE_PATH . "assets/html/html-end.php");
    ?>