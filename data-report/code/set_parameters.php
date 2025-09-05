<?php
$normal='class=""';
$red='class="text-danger fw-bold"'; 
$orange='class="text-warning fw-bold"'; 
$green='class="text-success fw-bold"'; 
$class_r=$normal;
$class_y=$normal;
$class_b=$normal;
$class_ir=$normal;
$class_iy=$normal;
$class_ib=$normal;

$class_pf=$normal;
$class_temp_1=$normal;
$class_temp_2=$normal;
$class_temp_3=$normal;
$class_temp_4=$normal;
$class_temp_5=$normal;
$class_load=$normal;
$class_load_r=$normal;
$class_load_y=$normal;
$class_load_b=$normal;
$class_on_off_status=$normal;
$temp_fail=1;

$v_min_r=180;
$v_min_y=180;
$v_min_b=180;
$v_max_r=250;
$v_max_y=250;
$v_max_b=250;
$c_max_r=20;
$c_max_y=20;
$c_max_b=20;
$temp=45;
$pf1=0.85;
$pf2=-0.85;
$load=80;

//$sql_set_parma="SELECT  `l_r`,`l_y`,`l_b`,`u_r`,`u_y`,`u_b`,`i_r`,`i_y`,`i_b`,`pf` FROM `$central_db`.`thresholds` WHERE device_id='$device_id'";
/*$params_collection = $devices_db_conn->thresholds ?? null;

if ($params_collection) {
	$params_doc = $params_collection->findOne(['device_id' => $device_id]);

	if ($params_doc) {
		$v_min_r = $params_doc['l_r'] ?? 0;
		$v_min_y = $params_doc['l_y'] ?? 0;
		$v_min_b = $params_doc['l_b'] ?? 0;

		$v_max_r = $params_doc['u_r'] ?? 0;
		$v_max_y = $params_doc['u_y'] ?? 0;
		$v_max_b = $params_doc['u_b'] ?? 0;

		$c_max_r = $params_doc['i_r'] ?? 0;
		$c_max_y = $params_doc['i_y'] ?? 0;
		$c_max_b = $params_doc['i_b'] ?? 0;

		$v_max_lr = $v_max_r - ($v_max_r * 0.04);
		$v_max_ly = $v_max_y - ($v_max_y * 0.04);
		$v_max_lb = $v_max_b - ($v_max_b * 0.04);

        $pf2 = round((1 - ($pf1 ?? 0) + 1) - 2, 3); // Adjust $pf1 initialization accordingly
    } 
} */

$v_max_lr=$v_max_r - $v_max_r*(0.04);
$v_max_ly=$v_max_y - $v_max_y*(0.04);
$v_max_lb=$v_max_b - $v_max_b*(0.04);
$pf2 = round((1 - $pf1 + 1)-2, 3);
?>