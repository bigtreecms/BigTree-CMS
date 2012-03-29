<?
	if ($admin->Level < 1) {
?>
<h1>
	<span class="analytics"></span>Analytics: Access Denied
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>
<p>Analytics is not presently setup.  Please contact an administrator to setup Analytics before proceeding.</p>
<?
	} else {
		$breadcrumb[] = array("link" => "dashboard/vitals-statistics/analytics/choose-profile/", "title" => "Choose Profile");
		$ga = new BigTreeGoogleAnalytics;
		$accounts = $ga->getAvailableProfiles();
?>
<h1>
	<span class="analytics"></span>Analytics Setup
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>
<div class="form_container">
	<form method="post" action="<?=$mroot?>set-profile/" class="module">
		<section>
			<p>Please choose the correct site profile below, click Update, and wait while we gather your Google Analytics information.</p>
			<br />
			<fieldset>
				<label>Profile</label>
				<select name="profile">
					<?
						foreach ($accounts as $account => $profiles) {
							foreach ($profiles as $profile) {
					?>
					<option value="<?=$profile["id"]?>"><?=$account?> &mdash; <?=$profile["title"]?></option>
					<?
							}
						}
					?>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="blue" value="Update" />
		</footer>
	</form>
</div>
<?
	}
?>