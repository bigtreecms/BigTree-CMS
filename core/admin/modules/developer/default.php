<?
	// Check whether our database is running the latest revision of BigTree or not.
	$current_revision = $cms->getSetting("bigtree-internal-revision");
	if ($current_revision < BIGTREE_REVISION && $admin->Level > 1) {
		BigTree::redirect(ADMIN_ROOT."dashboard/update/");
	}
?>
<div class="table">
	<summary><h2>Create</h2></summary>
	<section>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>templates/">
			<span class="templates"></span>
			<p>Templates</p>
		</a>
		
		<a class="box_select" href="<?=DEVELOPER_ROOT?>modules/">
			<span class="modules"></span>
			<p>Modules</p>
		</a>
		
		<a class="box_select" href="<?=DEVELOPER_ROOT?>callouts/">
			<span class="callouts"></span>
			<p>Callouts</p>
		</a>
		
		<a class="box_select" href="<?=DEVELOPER_ROOT?>field-types/">
			<span class="field_types"></span>
			<p>Field Types</p>
		</a>
		
		<a class="box_select" href="<?=DEVELOPER_ROOT?>feeds/">
			<span class="feeds"></span>
			<p>Feeds</p>
		</a>
		
		<a class="box_select" href="<?=DEVELOPER_ROOT?>settings/">
			<span class="settings"></span>
			<p>Settings</p>
		</a>
		<a class="box_select last" href="<?=DEVELOPER_ROOT?>foundry/install/">
			<span class="package"></span>
			<p>Install Package</p>
		</a>
	</section>
</div>


<div class="table">
	<summary><h2>Configure</h2></summary>
	<section>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>cloud-storage/">
			<span class="cloud"></span>
			<p>Cloud Storage</p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>payment-gateway/">
			<span class="payment"></span>
			<p>Payment Gateway</p>
		</a>
		<a class="box_select" href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/analytics/configure/">
			<span class="analytics"></span>
			<p>Analytics</p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>geocoding/">
			<span class="geocoding"></span>
			<p>Geocoding</p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>services/">
			<span class="api"></span>
			<p>Service APIs</p>
		</a>
		<a class="box_select last" href="<?=DEVELOPER_ROOT?>status/">
			<span class="vitals"></span>
			<p>Site Status</p>
		</a>
	</section>
</div>

