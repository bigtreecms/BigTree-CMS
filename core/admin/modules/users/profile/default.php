<?php
	namespace BigTree;
	
	/**
	 * @global array $policy
	 * @global string $policy_text
	 */

	$user = new User(Auth::user()->ID);
	$bigtree["gravatar"] = $user->Email;
	$error = false;

	if (isset($_SESSION["bigtree_admin"]["update_profile"])) {
		$saved = $_SESSION["bigtree_admin"]["update_profile"];
		$user->Company = Text::htmlEncode($saved["company"]);
		$user->Name = Text::htmlEncode($saved["name"]);
		$user->DailyDigest = !empty($saved["daily_digest"]) ? true : false;
		
		unset($_SESSION["bigtree_admin"]["update_profile"]);
		$error = true;
	}
?>
<div class="container">
	<form class="module" action="<?=ADMIN_ROOT?>users/profile/update/" method="post">
		<?php CSRF::drawPOSTToken(); ?>
		<section>
			<p><?=Text::translate("<strong>Note:</strong> Changing your password will require you to login again.")?></p>
			<hr>
			<p class="error_message"<?php if (!$error) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
			
			<div class="left">
				<fieldset>
					<label for="user_field_name"><?=Text::translate("Name")?></label>
					<input id="user_field_name" type="text" name="name" value="<?=$user->Name?>" tabindex="1" />
				</fieldset>
				<fieldset<?php if ($error) { ?> class="form_error"<?php } ?>>
					<label for="user_field_password"><?=Text::translate("Password <small>(leave blank to remain unchanged)</small>")?> <?php if ($error) { ?><span class="form_error_reason"><?=Text::translate("Did Not Meet Requirements")?></span><?php } ?></label>
					<input id="user_field_password" type="password" name="password" value="" tabindex="3" autocomplete="off" <?php if ($policy_text) { ?> class="has_tooltip" data-tooltip="<?=htmlspecialchars($policy_text)?>"<?php } ?> />
					<?php if ($policy_text) { ?>
					<p class="password_policy"><?=Text::translate("Password Policy In Effect")?></p>
					<?php } ?>
				</fieldset>
			</div>
			<div class="right">
				<fieldset>
					<label for="user_field_company"><?=Text::translate("Company")?></label>
					<input id="user_field_company" type="text" name="company" value="<?=$user->Company?>" tabindex="2" />
				</fieldset>
				
				<br /><br />
				
				<fieldset>
					<input id="user_field_digest" type="checkbox" name="daily_digest" tabindex="4" <?php if ($user->DailyDigest) { ?> checked="checked"<?php } ?> />
					<label for="user_field_digest" class="for_checkbox"><?=Text::translate("Daily Digest Email")?></label>
				</fieldset>
			</div>			
		</section>
		<footer>
			<input type="submit" class="blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>
<script>
	BigTreePasswordInput("input[type=password]");
</script>