<?php
	namespace BigTree;
	
	// Check whether our database is running the latest revision of BigTree or not.
	$current_revision = Setting::value("bigtree-internal-revision");
	
	if ($current_revision < BIGTREE_REVISION && Auth::user()->Level > 1) {
		Router::redirect(DEVELOPER_ROOT."upgrade/database/");
	}

	// Check for updates
	include "upgrade/_update-list.php";

	if (empty($showing_updates)) {
?>
<div class="container">
	<div class="container_summary"><h2><?=Text::translate("Create")?></h2></div>
	<section>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>templates/">
			<span class="templates"></span>
			<p><?=Text::translate("Templates")?></p>
		</a>
		
		<a class="box_select" href="<?=DEVELOPER_ROOT?>modules/">
			<span class="modules"></span>
			<p><?=Text::translate("Modules")?></p>
		</a>
		
		<a class="box_select" href="<?=DEVELOPER_ROOT?>callouts/">
			<span class="callouts"></span>
			<p><?=Text::translate("Callouts")?></p>
		</a>
		
		<a class="box_select" href="<?=DEVELOPER_ROOT?>field-types/">
			<span class="field_types"></span>
			<p><?=Text::translate("Field Types")?></p>
		</a>
		
		<a class="box_select" href="<?=DEVELOPER_ROOT?>feeds/">
			<span class="feeds"></span>
			<p><?=Text::translate("Feeds")?></p>
		</a>
		
		<a class="box_select" href="<?=DEVELOPER_ROOT?>settings/">
			<span class="settings"></span>
			<p><?=Text::translate("Settings")?></p>
		</a>
		<a class="box_select last" href="<?=DEVELOPER_ROOT?>extensions/">
			<span class="package"></span>
			<p><?=Text::translate("Extensions &amp; Packages")?></p>
		</a>
	</section>
</div>

<div class="container">
	<div class="container_summary"><h2><?=Text::translate("Configure")?></h2></div>
	<section>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>cloud-storage/">
			<span class="cloud"></span>
			<p><?=Text::translate("Cloud Storage")?></p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>payment-gateway/">
			<span class="payment"></span>
			<p><?=Text::translate("Payment Gateway")?></p>
		</a>
		<a class="box_select" href="<?=ADMIN_ROOT?>dashboard/vitals-statistics/analytics/configure/">
			<span class="analytics"></span>
			<p><?=Text::translate("Analytics")?></p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>geocoding/">
			<span class="geocoding"></span>
			<p><?=Text::translate("Geocoding")?></p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>email/">
			<span class="messages"></span>
			<p><?=Text::translate("Email Delivery")?></p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>services/">
			<span class="api"></span>
			<p><?=Text::translate("Service APIs")?></p>
		</a>
		<a class="box_select last" href="<?=DEVELOPER_ROOT?>media/">
			<span class="images"></span>
			<p><?=Text::translate("Media")?></p>
		</a>
		<a class="box_select second_row" href="<?=DEVELOPER_ROOT?>security/">
			<span class="lock"></span>
			<p><?=Text::translate("Security")?></p>
		</a>
		<a class="box_select second_row" href="<?=DEVELOPER_ROOT?>dashboard/">
			<span class="home"></span>
			<p><?=Text::translate("Dashboard")?></p>
		</a>
		<a class="box_select second_row" href="<?=DEVELOPER_ROOT?>cron-digest/">
			<span class="pending"></span>
			<p><?=Text::translate("Daily Digest<br />&amp; Cron")?></p>
		</a>
	</section>
</div>

<div class="container">
	<div class="container_summary"><h2><?=Text::translate("Debug")?></h2></div>
	<section>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>status/">
			<span class="vitals"></span>
			<p><?=Text::translate("Site Status")?></p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>audit/">
			<span class="trail"></span>
			<p><?=Text::translate("Audit Trail")?></p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>user-emulator/">
			<span class="users"></span>
			<p><?=Text::translate("User Emulator")?></p>
		</a>
		<a class="box_select last" href="<?=DEVELOPER_ROOT?>content-generator/">
			<span class="edit_page"></span>
			<p><?=Text::translate("Content Generator")?></p>
		</a>
	</section>
</div>
<?php
	}
