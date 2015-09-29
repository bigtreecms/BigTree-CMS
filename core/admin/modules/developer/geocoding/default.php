<?php
	if (!$admin->settingExists('bigtree-internal-geocoding-service')) {
	    $admin->createSetting(array(
			'id' => 'bigtree-internal-geocoding-service',
			'system' => 'on',
			'encrypted' => 'on',
		));
	    $admin->updateSettingValue('bigtree-internal-geocoding-service', array('service' => 'google', 'settings' => array()));
	}
	$gateway = $cms->getSetting('bigtree-internal-geocoding-service');
	$gateway['service'] = isset($gateway['service']) ? $gateway['service'] : '';
?>
<div class="table">
	<summary><h2>Configure</h2></summary>
	<section>
		<a class="box_select<?php if ($gateway['service'] == 'google' || !$gateway['service']) {
    ?> connected<?php 
} ?>" href="<?=DEVELOPER_ROOT?>geocoding/google/">
			<span class="google"></span>
			<p>Google</p>
		</a>
		<a class="box_select<?php if ($gateway['service'] == 'bing') {
    ?> connected<?php 
} ?>" href="<?=DEVELOPER_ROOT?>geocoding/bing/">
			<span class="bing"></span>
			<p>Bing</p>
		</a>
		<a class="box_select<?php if ($gateway['service'] == 'yahoo') {
    ?> connected<?php 
} ?>" href="<?=DEVELOPER_ROOT?>geocoding/yahoo/">
			<span class="yahoo"></span>
			<p>Yahoo</p>
		</a>
		<a class="box_select<?php if ($gateway['service'] == 'mapquest') {
    ?> connected<?php 
} ?>" href="<?=DEVELOPER_ROOT?>geocoding/mapquest/">
			<span class="mapquest"></span>
			<p>MapQuest</p>
		</a>
		<a class="box_select<?php if ($gateway['service'] == 'yahoo-boss') {
    ?> connected<?php 
} ?>" href="<?=DEVELOPER_ROOT?>geocoding/yahoo-boss/">
			<span class="yahooboss"></span>
			<p>Yahoo BOSS</p>
		</a>
	</section>
</div>