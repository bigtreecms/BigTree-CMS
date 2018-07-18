<?php
	$error = "";
	
	if (isset($_SESSION["bigtree_admin"]["create_user"])) {
		BigTree::globalizeArray($_SESSION["bigtree_admin"]["create_user"],array("htmlspecialchars"));
		$daily_digest = isset($daily_digest) ? $daily_digest : false;
		unset($_SESSION["bigtree_admin"]["create_user"]);
	} else {
		$email = "";
		$name = "";
		$company = "";
		$daily_digest = "on";
		$level = 0;
	}

	$timezone_list = DateTimeZone::listIdentifiers();
?>
<div class="container">
	<form class="module" action="<?=ADMIN_ROOT?>users/create/" method="post">
		<?php $admin->drawCSRFToken() ?>
		<section>
			<p class="error_message"<?php if (!$error) { ?> style="display: none;"<?php } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="left">
				<fieldset<?php if ($error == "email") { ?> class="form_error"<?php } ?> style="position: relative;">
					<label class="required">Email <small>(Profile images from <a href="http://www.gravatar.com/" target="_blank">Gravatar</a>)</small> <?php if ($error == "email") { ?><span class="form_error_reason">Already In Use</span><?php } ?></label>
					<input type="text" class="required email" name="email" autocomplete="off" value="<?=$email?>" tabindex="1" />
					<span class="gravatar"<?php if ($email != "") echo ' style="display: block;"'; ?>><img src="<?=BigTree::gravatar($email, 36)?>" alt="" /></span>
				</fieldset>
				
				<?php
					$tab_index_offset = true;
					
					if (empty($policy["invitations"])) {
						$tab_index_offset = false;
				?>
				<fieldset<?php if ($error == "password") { ?> class="form_error"<?php } ?>>
					<label class="required">Password <?php if ($error == "password") { ?><span class="form_error_reason">Did Not Meet Requirements</span><?php } ?></label>
					<input type="password" class="required<?php if ($policy_text) { ?> has_tooltip" data-tooltip="<?=htmlspecialchars($policy_text)?><?php } ?>" name="password" value="" tabindex="3" />
					<?php if ($policy_text) { ?>
					<p class="password_policy">Password Policy In Effect</p>
					<?php } ?>
				</fieldset>
				<?php
					}
				?>
				
				<fieldset>
					<label class="required">User Level</label>
					<select name="level" tabindex="<?=($tab_index_offset ? 3 : 5)?>">
						<option value="0">Normal User</option>
						<option value="1"<?php if ($level == 1) { ?> selected="selected"<?php } ?>>Administrator</option>
						<?php if ($admin->Level > 1) { ?><option value="2"<?php if ($level == 2) { ?> selected="selected"<?php } ?>>Developer</option><?php } ?>
					</select>
				</fieldset>

				<br />
				
				<fieldset>
					<input type="checkbox" name="daily_digest" tabindex="<?=($tab_index_offset ? 5 : 7)?>" checked="checked" />
					<label class="for_checkbox">Daily Digest Email</label>
				</fieldset>
			</div>
			<div class="right">
				<fieldset>
					<label>Name</label>
					<input type="text" name="name" value="<?=$name?>" tabindex="2" />
				</fieldset>
				
				<fieldset>
					<label>Company</label>
					<input type="text" name="company" value="<?=$company?>" tabindex="4" />
				</fieldset>

				<fieldset>
					<label for="profile_field_timezone">Timezone</label>
					<select name="timezone" id="profile_field_timezone" tabindex="6">
						<option value="">Default (<?=date_default_timezone_get()?>)</option>
						<?php
							$last_continent = "";

							foreach ($timezone_list as $tz) {
								list($continent, $city, $locality) = explode("/", $tz);

								if ($continent != $last_continent) {
									if ($last_continent) {
										echo "</optgroup>";
									}

									echo '<optgroup label="'.$continent.'">';
									$last_continent = $continent;
								}

								if (!$city) {
									$city = "UTC";
								}

								$city = str_replace("_", " ", $city);
						?>
						<option value="<?=$tz?>"><?=$city?><?php if ($locality) { echo " - ".str_replace("_", " ", $locality); } ?></option>
						<?php
							}

							echo "</optgroup>";
						?>
					</select>
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" class="blue" value="Create" />
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