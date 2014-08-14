<?
	$user = $admin->getUser($admin->ID);
	$bigtree["gravatar"] = $user["email"];
	BigTree::globalizeArray($user,array("htmlspecialchars"));

	$error = false;
	if (isset($_SESSION["bigtree_admin"]["update_profile"])) {
		BigTree::globalizeArray($_SESSION["bigtree_admin"]["update_profile"],array("htmlspecialchars"));
		$daily_digest = isset($daily_digest) ? $daily_digest : false;
		unset($_SESSION["bigtree_admin"]["update_profile"]);
		$error = true;
	}
?>
<div class="container">
	<form class="module" action="<?=ADMIN_ROOT?>users/profile/update/" method="post">
		<section>
			<p class="error_message"<? if (!$error) { ?> style="display: none;"<? } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="left">
				<fieldset>
					<label>Name</label>
					<input type="text" name="name" value="<?=$name?>" tabindex="1" />
				</fieldset>
				<fieldset<? if ($error) { ?> class="form_error"<? } ?>>
					<label>Password <small>(leave blank to remain unchanged)</small> <? if ($error) { ?><span class="form_error_reason">Did Not Meet Requirements</span><? } ?></label>
					<input type="password" name="password" value="" tabindex="3" autocomplete="off" <? if ($policy) { ?> class="has_tooltip" data-tooltip="<?=htmlspecialchars($policy_text)?>"<? } ?> />
					<? if ($policy) { ?>
					<p class="password_policy">Password Policy In Effect</p>
					<? } ?>
				</fieldset>
			</div>
			<div class="right">
				<fieldset>
					<label>Company</label>
					<input type="text" name="company" value="<?=$company?>" tabindex="2" />
				</fieldset>
				
				<br /><br />
				
				<fieldset>
					<input type="checkbox" name="daily_digest" tabindex="4" <? if ($daily_digest) { ?> checked="checked"<? } ?> />
					<label class="for_checkbox">Daily Digest Email</label>
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