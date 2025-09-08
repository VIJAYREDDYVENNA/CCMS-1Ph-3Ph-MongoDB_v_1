<div class="modal fade" id="installedModal" tabindex="-1" aria-labelledby="installedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-success" id="installedModalLabel">Installed Devices</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" >
                <div class="col-12 p-0"  id="installedDevicesModal">
                    <div class="table-responsive-1 rounded mt-2 border ">
                        <table class="table table-striped table-type-1 w-100 text-center"id="installedDeviceTable">
                            <thead>
                                <tr>
                                    <th class="bg-primary-subtle" scope="col"> <input type="checkbox" id="selectAll-installed" class="select_all" onclick="select_devices('selectAll-installed', 'selected_count-installed')" > Select All</th>
                                    <th class="bg-primary-subtle" scope="col">Device ID</th>
                                    <th class="bg-primary-subtle" scope="col">Device Name</th>

                                    <th class="bg-primary-subtle" scope="col">Installation Status</th>
                                    <th class="bg-primary-subtle" scope="col">Installed Date</th>
                                    <th class="bg-primary-subtle" scope="col">Location</th>
                                </tr>
                            </thead>
                            <tbody id="installed_device_list_table">
                            </tbody>
                        </table>
                    </div>
                    <span>Selected: <span id="selected_count-installed" class="selected_count">0</span></span>
                </div>

                 <div class="col-12 p-0">
                    <div class="pagination-wrapper mt-2">
                        <div class="row">
                            <div class="col">
                                <div class="row g-2 align-items-center d-flex">
                                    <div class="col-auto">
                                        <label for="items-per-page-install" class="form-label">Items per page:</label>
                                    </div>
                                    <div class="col-auto">
                                        <select id="items-per-page-install" class="form-select">
                                            <option value="10">10</option>
                                            <option value="20" selected>20</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                            <option value="200">200</option>
                                        </select>
                                    </div>
                                </div>

                            </div>
                            <div class="col">
                                <div class="pagination-container">
                                    <nav>
                                        <ul class="pagination justify-content-end " id="pagination-install">
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="openBatchConfirmModal('uninstall', 'installedDeviceTable')">Uninstall Selected</button>
                <button type="button" class="btn btn-primary" onclick="openBatchConfirmModal('install', 'installedDeviceTable')">Installed date Update</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
