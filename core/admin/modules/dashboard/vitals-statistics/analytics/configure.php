<?
	if ($admin->Level < 1) {
?>
<div class="container">
	<section>
		<p>You are not authorized to view this section.</p>
	</section>
</div>
<?
	} else {
?>
<div class="container">
	<?
		if ($analytics->Settings["token"]) {
			$profiles = $analytics->getProfiles();
	?>
	<form method="post" action="<?=MODULE_ROOT?>set-profile/" class="module">
		<section>
			<fieldset>
				<label>Choose A Profile From The List Below</label>
				<?
					if (count($profiles->Results)) {
				?>
				<select name="profile">
					<? foreach ($profiles->Results as $profile) { ?>
					<option value="<?=$profile->ID?>"><?=$profile->WebsiteURL?> &mdash; <?=$profile->Name?></option>
					<? } ?>
				</select>
				<?
					} else {
				?>
				<p class="error_message">No profiles were found in your Google Analytics account.</p>
				<?  	
					}
				?>
			</fieldset>
		</section>
		<footer>
			<input type="submit" value="Set Profile" class="blue" id="set_button" />
			<a href="#" class="button" id="ga_disconnect">Disconnect</a>
		</footer>
	</form>
	
	<?
		} else {
			$auth_url = $analytics->AuthorizeURL.
				"?client_id=".urlencode($analytics->ClientID).
				"&redirect_uri=".urlencode($analytics->ReturnURL).
				"&response_type=code".
				"&scope=".urlencode($analytics->Scope).
				"&approval_prompt=force".
				"&access_type=offline";
	?>
	<form method="get" action="<?=MODULE_ROOT?>set-token/" class="module">	
		<section>
			<p>To connect Google Analytics you will need to login to your Google Analytics account by clicking the Authenticate button below. Once you have logged in you will be taken to a screen with a code in a box. Copy that code into the field that appears below to allow BigTree to access your Google Analytics information.</p>
			<fieldset>
				<input type="text" name="code" placeholder="Enter Code Here" />
			</fieldset>
		</section>
		<footer>
			<a href="<?=$auth_url?>" class="button" id="google_button" target="_blank">Authenticate</a>
			<input type="submit" class="button blue" id="profile_button" value="Save Code" style="display: none;" />
		</footer>
	</form>
	<?
		}
	?>		
</div>
<script>
	$("#google_button").click(function() {
		$(this).hide();
		$("#profile_button").show();
	});
	
	$("#ga_disconnect").click(function() {
		new BigTreeDialog("Disconnect Google Analytics","<p>Are you sure you want to disconnect your Google Analytics account? <br/ >This will remove all analytics data and can not be undone.</p>",function() {
			window.location.href = "<?=MODULE_ROOT?>disconnect/";
		},"delete",false,"Disconnect");
		return false;
	});
</script>
<?
	}
?>