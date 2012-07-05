<?
	if ($admin->Level < 1) {
?>
<h1>
	<span class="analytics"></span>Analytics: Access Denied
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>
<?
	} else {
		if (!$user || !$pass || !$profile) {
			BigTree::redirect($mroot."setup/");
		}
		
		$breadcrumb[] = array("link" => "dashboard/analytics/configure/", "title" => "Configure");
?>
<h1>
	<span class="analytics"></span>Configure
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>
<div class="form_container">
	<form method="post" action="<?=$mroot?>authenticate/" class="module">
		<section>
			<? if ($user && false) { ?>
			<p class="error_message">Your Google Analytics password has changed, please login again.</p>
			<? } ?>
			<p>Edit your Google Analytics email address and password below, or disconnect your account from BigTree.</p>
			<br />
			<? if (end($bigtree["path"]) == "error") { ?>
			<p class="error_message">Google Login Failed.</p>
			<? } ?>
			<div class="left">
				<fieldset>
					<label>Email Address</label>
				<input type="text" name="email" value="<?=$user?>" />
				</fieldset>
				<fieldset>
					<label>Password</label>
					<input type="password" name="password" value="<?=$pass?>" />
				</fieldset>
				<fieldset>
				<label>Active Profile</label>
				<?
					$ga = new BigTreeGoogleAnalytics;
					$accounts = $ga->getAvailableProfiles();
					foreach ($accounts as $account => $profiles) {
						foreach ($profiles as $pro) {
							if ($pro["id"] == $profile) {
				?>
				<p>
					<strong><?=$account?> &mdash; <?=$pro["title"]?></strong> <a href="<?=$mroot?>choose-profile">(Change)</a>
				</p>
				<?
							}
						}
					}
				?>
			</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" value="Re-Authenticate" class="blue" />
			<a href="<?=$mroot?>disconnect/" class="button" id="ga_disconnect">Disconnect</a>
		</footer>
		<script>
			$(document).ready(function() {
				$("#ga_disconnect").click(function() {
					var href = $(this).attr("href");
					var popup = new BigTreeDialog("Disconnect Google Analytics","Are you sure you want to disconnect your Google Analytics account? <br/ >This will remove all analytics data and can not be undone.",function() {
						window.location.href = href;
					},"delete",false,"Disconnect");
					return false;
				});
			});
		</script>
	</form>
</div>
<?
	}
?>