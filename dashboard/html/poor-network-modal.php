<div class="modal fade" id="activePoorNetworkModal" tabindex="-1" aria-labelledby="activePoorNetworkModalLabel" aria-hidden="true" >
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-warning-emphasis text-opacity-25" id="activePoorNetworkModalLabel">Poor Network Devices</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="col-12 p-0">
                    <div class="table-responsive-1 rounded mt-2 border ">
                        <table class="table table-striped table-type-1 w-100 text-center">
                            <thead>
                                <tr>
                                    <th class="bg-warning-subtle" scope="col">Device ID</th>
                                    <th class="bg-warning-subtle" scope="col">Device Name</th>                                    
                                    <th class="bg-warning-subtle col-size-1" scope="col">Last Record Updated</th>
                                    <th class="bg-warning-subtle col-size-1" scope="col">Last Communication at</th>
                                    <th class="bg-warning-subtle" scope="col">Status</th>
                                    <th class="bg-warning-subtle" scope="col">Location</th>
                                </tr>
                            </thead>
                            <tbody id="poor_nw_list_table">
                                <!-- Rows of active poor network devices data -->

                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-12 p-0">
                    <div class="pagination-wrapper mt-2">
                        <div class="row">
                            <div class="col">
                                <div class="row g-2 align-items-center d-flex">
                                    <div class="col-auto">
                                        <label for="items-per-page-poor" class="form-label">Items per page:</label>
                                    </div>
                                    <div class="col-auto">
                                        <select id="items-per-page-poor" class="form-select">
                                            <option value="10">10</option>
                                            <option value="20" selected>20</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                            <option value="200">200</option>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <span id="record-count-poor" class="text-muted small"></span>
                                    </div>
                                </div>

                            </div>
                            <div class="col">
                                <div class="pagination-container">
                                    <nav>
                                        <ul class="pagination justify-content-end " id="pagination-poor">
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>