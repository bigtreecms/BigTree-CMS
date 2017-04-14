<?php
	namespace BigTree;
	
	/**
	 * @global array $policy
	 * @global string $policy_text
	 */

	$error = $email = $name = $company = "";
	$daily_digest = "on";
	$level = 0;
	
	if (isset($_SESSION["bigtree_admin"]["create_user"])) {
		Globalize::arrayObject($_SESSION["bigtree_admin"]["create_user"], array("htmlspecialchars"));
		$daily_digest = isset($daily_digest) ? $daily_digest : false;
		unset($_SESSION["bigtree_admin"]["create_user"]);
	}
?>
<div class="container">
	<form class="module" action="<?=ADMIN_ROOT?>users/create/" method="post">
		<?php CSRF::drawPOSTToken(); ?>
		<section>
			<p class="error_message"<?php if (!$error) { ?> style="display: none;"<?php } ?>><?=Text::translate("Errors found! Please fix the highlighted fields before submitting.")?></p>
			<div class="left">
				<fieldset<?php if ($error == "email") { ?> class="form_error"<?php } ?> style="position: relative;">
					<label for="user_field_email" class="required"><?=Text::translate("Email")?> <small>(<?=Text::translate("Profile images from")?> <a href="http://www.gravatar.com/" target="_blank">Gravatar</a>)</small> <?php if ($error == "email") { ?><span class="form_error_reason"><?=Text::translate("Already In Use")?></span><?php } ?></label>
					<input id="user_field_email" type="text" class="required email" name="email" autocomplete="off" value="<?=$email?>" tabindex="1" />
					<span class="gravatar"<?php if ($email != "") echo ' style="display: block;"'; ?>><img src="<?=Image::gravatar($email, 36)?>" alt="" /></span>
				</fieldset>
				
				<fieldset<?php if ($error == "password") { ?> class="form_error"<?php } ?>>
					<label for="user_field_password" class="required"><?=Text::translate("Password")?> <?php if ($error == "password") { ?><span class="form_error_reason"><?=Text::translate("Did Not Meet Requirements")?></span><?php } ?></label>
					<input id="user_field_password" type="password" class="required<?php if ($policy) { ?> has_tooltip" data-tooltip="<?=htmlspecialchars($policy_text)?><?php } ?>" name="password" value="" tabindex="3" />
					<?php if ($policy) { ?>
					<p class="password_policy"><?=Text::translate("Password Policy In Effect")?></p>
					<?php } ?>
				</fieldset>
				
				<fieldset>
					<label for="user_field_level" class="required"><?=Text::translate("User Level")?></label>
					<select id="user_field_level" name="level" tabindex="5">
						<option value="0"><?=Text::translate("Normal User")?></option>
						<option value="1"<?php if ($level == 1) { ?> selected="selected"<?php } ?>><?=Text::translate("Administrator")?></option>
						<?php if (Auth::user()->Level > 1) { ?><option value="2"<?php if ($level == 2) { ?> selected="selected"<?php } ?>><?=Text::translate("Developer")?></option><?php } ?>
					</select>
				</fieldset>
			</div>
			<div class="right">
				<fieldset>
					<label for="user_field_name"><?=Text::translate("Name")?></label>
					<input id="user_field_name" type="text" name="name" value="<?=$name?>" tabindex="2" />
				</fieldset>
				
				<fieldset>
					<label for="user_field_company"><?=Text::translate("Company")?></label>
					<input id="user_field_company" type="text" name="company" value="<?=$company?>" tabindex="4" />
				</fieldset>
				
				<br />
				<fieldset>
					<input id="user_field_digest" type="checkbox" name="daily_digest" <?php if (!empty($daily_digest)) { ?>checked="checked" <?php } ?>/>
					<label for="user_field_digest" class="for_checkbox"><?=Text::translate("Daily Digest Email")?></label>
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" class="blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</form>
</div>
<script>
	BigTreeFormValidator("form.module");
	BigTreePasswordInput("input[type=password]");
	
	$(document).ready(function() {
		$("input.email").blur(function() {
			$(this).parent("fieldset").find(".gravatar").show().find("img").attr("src", 'http://www.gravatar.com/avatar/' + md5($(this).val().trim()) + '?s=36&d=' + encodeURIComponent("<?=ADMIN_ROOT?>images/icon_default_gravatar.jpg") + '&rating=pg');
		});
	});
</script>