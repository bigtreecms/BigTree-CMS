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
		<a class="box_select" href="templates/">
			<span class="templates"></span>
			<p>Templates</p>
		</a>
		
		<a class="box_select" href="modules/">
			<span class="modules"></span>
			<p>Modules</p>
		</a>
		
		<a class="box_select" href="callouts/">
			<span class="callouts"></span>
			<p>Callouts</p>
		</a>
		
		<a class="box_select" href="field-types/">
			<span class="field_types"></span>
			<p>Field Types</p>
		</a>
		
		<a class="box_select" href="feeds/">
			<span class="feeds"></span>
			<p>Feeds</p>
		</a>
		
		<a class="box_select" href="settings/">
			<span class="settings"></span>
			<p>Settings</p>
		</a>
		<a class="box_select last" href="foundry/install/">
			<span class="package"></span>
			<p>Install Package</p>
		</a>
	</section>
</div>


<div class="table">
	<summary><h2>Configure</h2></summary>
	<section>
		<a class="box_select" href="cloud-storage/">
			<span class="cloud"></span>
			<p>Cloud Storage</p>
		</a>
		<a class="box_select" href="payment-gateway/">
			<span class="payment"></span>
			<p>Payment Gateway</p>
		</a>
		<a class="box_select" href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/analytics/configure/">
			<span class="analytics"></span>
			<p>Analytics</p>
		</a>
		<a class="box_select" href="status/">
			<span class="vitals"></span>
			<p>Site Status</p>
		</a>
	</section>
</div>

