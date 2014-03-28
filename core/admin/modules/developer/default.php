<?
	// Check whether our database is running the latest revision of BigTree or not.
	$current_revision = $cms->getSetting("bigtree-internal-revision");
	if ($current_revision < BIGTREE_REVISION && $admin->Level > 1) {
		BigTree::redirect(DEVELOPER_ROOT."upgrade/database/");
	}
	// Check for newer versions of BigTree
	$ignored_all = true;
	if (!$_COOKIE["bigtree_admin"]["deferred_update"]) {
		$updates = @json_decode(BigTree::cURL("http://www.bigtreecms.org/ajax/version-check/?current_version=".BIGTREE_VERSION),true);
		// See if we've ignored these updates
		$ignorable = array();
		foreach ($updates as $update) {
			if (!$_COOKIE["bigtree_admin"]["ignored_update"][$update["version"]]) {
				$ignored_all = false;
			}
			$ignorable[] = $update["version"];
		}
	}
	// If we're ignoring updates through config, still ignore them
	if (!empty($bigtree["config"]["ignore_admin_updates"])) {
		$ignored_all = true;
	}

	// Updates are available and we didn't ignore them
	if (!$ignored_all && count($updates)) {
?>
<div class="container">
	<summary><h2>Update Available</h2></summary>
	<section>
		<p>You are currently running BigTree <?=BIGTREE_VERSION?>. The following update<? if (count($updates) > 1) { ?>s are<? } else { ?> is<? } ?> available:</p>
		<ul>
			<?
				foreach ($updates as $type => $update) {
					if (!$_COOKIE["bigtree_admin"]["ignored_update"][$update["version"]]) {
			?>
			<li>
				<strong><?=$update["version"]?></strong> &mdash; Released <?=date("F j, Y",strtotime($update["release_date"]))?> &mdash; 
				<?
					if ($type == "revision") {
						echo "This is a bugfix release and is recommended for all users.";
					} elseif ($type == "minor") {
						echo "This is a feature release. Though it should be backwards compatible it is recommended that you test the update on your development site before running it on your live site.";
					} elseif ($type == "major") {
						echo "This is a major update and is not backwards compatible. You must install this release manually.";
					}
				?>
			</li>
			<?
					}
				}
			?>
		</ul>
	</section>
	<footer>
		<?
			foreach ($updates as $type => $update) {
				if ($type != "major" && !$_COOKIE["bigtree_admin"]["ignored_update"][$update["version"]]) {
		?>
		<a class="button<? if ($type == "revision") { ?> blue<? } ?>" href="<?=DEVELOPER_ROOT?>upgrade/init/?type=<?=$type?>">Upgrade To <?=$update["version"]?></a>
		<?
				}
			}
		?>
		<a class="button" href="<?=DEVELOPER_ROOT?>upgrade/remind/">Remind Me In 1 Week</a>
		<a class="button red" href="<?=DEVELOPER_ROOT?>upgrade/ignore/?versions=<?=urlencode(json_encode($ignorable))?>">Ignore These Updates</a>
	</footer>
</div>
<?
	} else {
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
		<a class="box_select last" href="<?=DEVELOPER_ROOT?>packages/">
			<span class="package"></span>
			<p>Packages</p>
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
	</section>
</div>

<div class="table">
	<summary><h2>Debug</h2></summary>
	<section>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>status/">
			<span class="vitals"></span>
			<p>Site Status</p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>audit/">
			<span class="trail"></span>
			<p>Audit Trail</p>
		</a>
		<a class="box_select last" href="<?=DEVELOPER_ROOT?>user-emulator/">
			<span class="users"></span>
			<p>User Emulator</p>
		</a>
	</section>
</div>
<?
	}
?>