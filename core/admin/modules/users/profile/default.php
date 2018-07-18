<?php
	$user = $admin->getUser($admin->ID);
	$bigtree["gravatar"] = $user["email"];
	BigTree::globalizeArray($user,array("htmlspecialchars"));

	$error = false;
	$timezone_list = DateTimeZone::listIdentifiers();

	if (isset($_SESSION["bigtree_admin"]["update_profile"])) {
		BigTree::globalizeArray($_SESSION["bigtree_admin"]["update_profile"],array("htmlspecialchars"));
		$daily_digest = isset($daily_digest) ? $daily_digest : false;
		unset($_SESSION["bigtree_admin"]["update_profile"]);
		$error = true;
	}
?>
<div class="container">
	<form class="module" action="<?=ADMIN_ROOT?>users/profile/update/" method="post">
		<?php $admin->drawCSRFToken(); ?>
		<section>
			<p><strong>Note:</strong> Changing your password will require you to login again.</p>
			<hr>
			<p class="error_message"<?php if (!$error) { ?> style="display: none;"<?php } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="left">
				<fieldset>
					<label>Name</label>
					<input type="text" name="name" value="<?=$name?>" tabindex="1" />
				</fieldset>

				<fieldset<?php if ($error) { ?> class="form_error"<?php } ?>>
					<label>Password <small>(leave blank to remain unchanged)</small> <?php if ($error) { ?><span class="form_error_reason">Did Not Meet Requirements</span><?php } ?></label>
					<input type="password" name="password" value="" tabindex="3" autocomplete="off" <?php if ($policy_text) { ?> class="has_tooltip" data-tooltip="<?=htmlspecialchars($policy_text)?>"<?php } ?> />
					<?php if ($policy_text) { ?>
					<p class="password_policy">Password Policy In Effect</p>
					<?php } ?>
				</fieldset>

				<fieldset>
					<input type="checkbox" name="daily_digest" tabindex="5" <?php if ($daily_digest) { ?> checked="checked"<?php } ?> />
					<label class="for_checkbox">Daily Digest Email</label>
				</fieldset>
			</div>
			<div class="right">
				<fieldset>
					<label>Company</label>
					<input type="text" name="company" value="<?=$company?>" tabindex="2" />
				</fieldset>
				
				<fieldset>
					<label for="profile_field_timezone">Timezone</label>
					<select name="timezone" id="profile_field_timezone" tabindex="4">
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
						<option value="<?=$tz?>"<?php if ($timezone == $tz) { ?> selected<?php } ?>><?=$city?><?php if ($locality) { echo " - ".str_replace("_", " ", $locality); } ?></option>
						<?php
							}

							echo "</optgroup>";
						?>
					</select>
				</fieldset>
			</div>			
		</section>
		<footer>
			<input type="submit" class="blue" value="Update" />
		</footer>
	</form>
</div>
<script>
	BigTreePasswordInput("input[type=password]");
</script>