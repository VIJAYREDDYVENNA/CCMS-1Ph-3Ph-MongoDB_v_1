<div class="modal fade" id="permission" tabindex="-1" aria-labelledby="permission" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="permissions">#<span id="userid_per"></span>-Permissions</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-primary bg-opacity-25 fw-bold">
                                Permissions
                            </div>
                            <div class="card-body bg-light">
                                <form id="permissions-form">
                                    <?php
                                    try {
                                        $permissionsDoc = $user_db_conn->user_permissions->findOne(['login_id' => (int)$user_login_id]);

                                        if ($permissionsDoc !== null) {
                                            $count = 0;
                                            $permissions = "";

                                            $permission_fields = [
                                                'on_off_control' => [
                                                    'label' => 'ON/OFF Control',
                                                    'info' => 'Allows users to instantly control the on/off functionality of street lights directly from the On/Off Control page.'
                                                ],
                                                'on_off_mode' => [
                                                    'label' => 'ON-OFF Operational Modes',
                                                    'info' => 'Allows users to edit the operational mode for controlling how the device will operate to turn on street lights.'
                                                ],
                                                'device_info_update' => [
                                                    'label' => 'Device Info Update',
                                                    'info' => 'Enables users to modify existing device details such as Device ID or Name, Location, and Installation Date.'
                                                ],
                                                'threshold_settings' => [
                                                    'label' => 'Threshold Settings',
                                                    'info' => 'Allows the user to update the devices threshold settings (e.g., voltage, current, etc.)'
                                                ],
                                                'iot_settings' => [
                                                    'label' => 'IoT-Settings',
                                                    'info' => 'Allows the user to update the IOT settings (e.g., Device Id, Energy, Hysteresis etc.).'
                                                ],
                                                'lights_info_update' => [
                                                    'label' => 'Lights Info Update',
                                                    'info' => 'Allows the user to add lights to the device on the Device List page.'
                                                ],
                                                'device_add_remove' => [
                                                    'label' => 'Devices Add/Remove Updates',
                                                    'info' => 'Allows the user to add or remove devices.'
                                                ],
                                                'user_details_updates' => [
                                                    'label' => 'Manage Users',
                                                    'info' => 'Enables the user to add new users based on the desired hierarchy on the Users Page.'
                                                ],
                                                'create_group' => [
                                                    'label' => 'Create Group/Area',
                                                    'info' => 'Enables the user to create a New Group/Area.'
                                                ],
                                                'add_remove_electrician' => [
                                                    'label' => 'Add/Remove Electrician',
                                                    'info' => 'Enables the user add or remove Electrician.'
                                                ],
                                                'notification_update' => [
                                                    'label' => 'Notification Settings',
                                                    'info' => 'Allows the user to update parameters on the notification settings page to receive alerts for parameters like voltage, current, etc'
                                                ],
                                                'installation_status_update' => [
                                                    'label' => 'Install & Uninstall Status Update',
                                                    'info' => 'Allows the user to update the installation/uninstallation details of devices on the Dashboard.'
                                                ],
                                                'download_data' => [
                                                    'label' => 'Downloads',
                                                    'info' => 'Allows the User to Download the data from the Downloads page.'
                                                ],
                                                'user_permissions' => [
                                                    'label' => 'User Permission',
                                                    'info' => 'Allows the user to manage and control the actions of users working under them, including restricting certain functionalities.'
                                                ]
                                            ];

                                            foreach ($permission_fields as $key => $data) {
                                                if (isset($permissionsDoc[$key]) && intval($permissionsDoc[$key]) === 1) {
                                                    echo '<div class="d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center">
                                                    <label class="form-check-label" for="'.$key.'" onclick="event.preventDefault();">'.$data['label'].'</label>
                                                    <a tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-title="Info"
                                                    data-bs-content="'.$data['info'].'" class="ms-2">
                                                    <i class="bi bi-info-circle"></i>
                                                    </a>
                                                    </div>
                                                    <div class="form-check form-switch ms-auto">
                                                    <input class="form-check-input pointer" type="checkbox" name="permissions" id="'.$key.'" data-permission="'.$key.'" value="'.$key.'">
                                                    </div>
                                                    </div>
                                                    <hr class="my-2">';
                                                    $count++;
                                                    $permissions .= $key.', ';
                                                }
                                            }

                                            if ($count === 0) {
                                                echo '<p class="text-danger">Permissions List Not Available </p>';
                                            } else {
                                                $permissions = rtrim($permissions, ', ');
                                                $_SESSION['permission_variables'] = $permissions;
                                            }
                                        } else {
                                            echo '<p class="text-danger">Permissions List Not Available </p>';
                                        }
                                    } catch (Exception $e) {

                                    }
                                    ?>

                                </form>
                            </div>

                        </div>
                    </div>
                    <div class="col-lg-2 col-xl-2 col-md-2 col-sm-4 col-xs-4"></div>
                </div>
            </div>
            <div class="modal-footer mb-3">

                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="updateSelectedPermissions()">Save</button>
            </div>

        </div>
    </div>
</div>