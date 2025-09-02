<div class="modal fade" id="menu_permission" tabindex="-1" aria-labelledby="menu_permission" aria-hidden="true">
	<div class="modal-dialog modal-md">
		<div class="modal-content">
			<div class="modal-header">
				<h1 class="modal-title fs-5" id="menu_permissions">#<span id="userid_menu"></span>-Permissions</h1>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="row">
					<div class="col-12">
						<div class="card">
							<div class="card-header bg-primary bg-opacity-25 fw-bold">
								Menu Permissions
							</div>
							<div class="card-body bg-light">
								<form id="menu-permissions-form">
									<?php
									try {
										$permissionsDoc = $user_db_conn->menu_permissions_list->findOne(['login_id' => (int)$user_login_id]);

										$permissions = "";
										if ($permissionsDoc !== null) {
											$permission_fields = [
												'device_dashboard' => 'Device Overview',
												'dashboard' => 'Dashboard Access',
												'devices_list' => 'Devices List',
												'onoff_control' => 'ON/OFF Control',
												'gis_map' => 'GIS Map',
												'data_report' => 'Data Report',
												'energy_consumption' => 'Energy Consumption',
												'thresholdsettings' => 'Threshold Settings',
												'group_creation' => 'Group Creation',
												'location_update' => 'Location Update',
												'notification_settings' => 'Notification Settings',
												'iotsettings' => 'IoT Settings',
												'pending_actions' => 'Pending Actions',
												'add_new_electrician_devices' => 'Add New Electrician Devices',
												'phase_alerts' => 'Phase Alerts',
												'alerts' => 'Alerts',
												'notification_mesages' => 'Notification Messages',
												'graphs' => 'Graphs',
												'up_down_time' => 'Up/Down Time',
												'glowing_time' => 'Glowing Time',
												'user_activity' => 'User Activity',
												'download' => 'Download',
												'complaints' => 'Complaints',
												'office_use' => 'Office Use',
												'users_list' => 'Users List'
											];

											$count = 0;
											foreach ($permission_fields as $key => $label) {
												if (isset($permissionsDoc[$key]) && intval($permissionsDoc[$key]) === 1) {
													echo '<div class="d-flex justify-content-between align-items-center">
													<label class="form-check-label" for="' . $key . '">' . $label . '</label>
													<div class="form-check form-switch ms-auto">
													<input class="form-check-input pointer" type="checkbox" name="permissions" id="' . $key . '" data-permission="' . $key . '" value="' . $key . '">
													</div>
													</div>
													<hr class="my-2">';
													$count++;
													$permissions .= $key . ', ';
												}
											}

											if ($count == 0) {
												echo '<p class="text-danger">Permissions List Not Available</p>';
											} else {
												$permissions = rtrim($permissions, ', ');
												$_SESSION['menu_permission_variables'] = $permissions;
											}
										} else {
											echo '<p class="text-danger">Permissions List Not Available</p>';
										}
									} catch (Exception $e) {
    // Handle exception if needed
									}
									?>

								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer mb-3">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
				<button type="button" class="btn btn-primary" onclick="updateSelectedMenuPermissions()">Save</button>
			</div>
		</div>
	</div>
</div>