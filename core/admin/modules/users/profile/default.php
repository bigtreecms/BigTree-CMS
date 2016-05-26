<?php
	namespace BigTree;

	$user = $admin->getUser($admin->ID);
	$bigtree["gravatar"] = $user["email"];
	Globalize::arrayObject($user,array("htmlspecialchars"));

	$error = false;
	if (isset($_SESSION["bigtree_admin"]["update_profile"])) {
		Globalize::arrayObject($_SESSION["bigtree_admin"]["update_profile"],array("htmlspecialchars"));
		$daily_digest = isset($daily_digest) ? $daily_digest : false;
		unset($_SESSION["bigtree_admin"]["update_profile"]);
		$error = true;
	}
?>
<div class="container">
	<form class="module" action="<?=ADMIN_ROOT?>users/profile/update/" method="post">
		<section>
			<p class="error_message"<?php if (!$error) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
			<div class="left">
				<fieldset>
					<label><?=Text::translate("Name")?></label>
					<input type="text" name="name" value="<?=$name?>" tabindex="1" />
				</fieldset>
				<fieldset<?php if ($error) { ?> class="form_error"<?php } ?>>
					<label><?=Text::translate("Password <small>(leave blank to remain unchanged)</small>")?> <?php if ($error) { ?><span class="form_error_reason"><?=Text::translate("Did Not Meet Requirements")?></span><?php } ?></label>
					<input type="password" name="password" value="" tabindex="3" autocomplete="off" <?php if ($policy) { ?> class="has_tooltip" data-tooltip="<?=htmlspecialchars($policy_text)?>"<?php } ?> />
					<?php if ($policy) { ?>
					<p class="password_policy"><?=Text::translate("Password Policy In Effect")?></p>
					<?php } ?>
				</fieldset>
			</div>
			<div class="right">
				<fieldset>
					<label><?=Text::translate("Company")?></label>
					<input type="text" name="company" value="<?=$company?>" tabindex="2" />
				</fieldset>
				
				<br /><br />
				
				<fieldset>
					<input type="checkbox" name="daily_digest" tabindex="4" <?php if ($daily_digest) { ?> checked="checked"<?php } ?> />
					<label class="for_checkbox"><?=Text::translate("Daily Digest Email")?></label>
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