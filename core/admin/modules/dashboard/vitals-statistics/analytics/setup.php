<?
	if ($admin->Level < 1) {
?>
<h1>
	<span class="analytics"></span>Analytics: Access Denied
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>
<?
	} else {
		$breadcrumb[] = array("link" => "dashboard/analytics/setup/", "title" => "Setup");
?>
<h1>
	<span class="analytics"></span>Analytics Setup
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>
<div class="form_container">
	<form method="post" action="<?=$mroot?>authenticate/" class="module">
		<section>
			<? if ($user) { ?>
			<p class="error_message">Your Google Analytics password has changed, please login again.</p>
			<? } ?>
			<p>Please enter your Google Analytics email address and password below.</p>
			<br />
			<? if (end($path) == "error") { ?>
			<p class="error_message">Google Login Failed.</p>
			<? } ?>
			<div class="left">
				<fieldset>
					<label>Email Address</label>
				<input type="text" name="email" />
				</fieldset>
				<fieldset>
					<label>Password</label>
					<input type="password" name="password" />
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" value="Authenticate" class="blue" />
		</footer>
	</form>
</div>
<?
	}
?>