<?
	$breadcrumb[] = array("link" => "users/add/", "title" => "Add User");
	
	$e = false;

	if (isset($_SESSION["bigtree"]["create_user"])) {
		BigTree::globalizeArray($_SESSION["bigtree"]["create_user"],array("htmlspecialchars"));
		$e = true;
		unset($_SESSION["bigtree"]["create_user"]);
	}
?>
<h1><span class="users"></span>Add User</h3>
<? include BigTree::path("admin/modules/users/_nav.php"); ?>
<div class="form_container">
	<form class="module" action="<?=$admin_root?>users/create/" method="post">	
		<section>
			<p class="error_message"<? if (!$e) { ?> style="display: none;"<? } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="left">
				<fieldset<? if ($e) { ?> class="form_error"<? } ?>>
					<label class="required">Email<? if ($e) { ?><span class="form_error_reason">Already In Use By Another User</span><? } ?></label>
					<input type="text" class="required email" name="email" value="<?=$email?>" tabindex="1" />
				</fieldset>
				
				<fieldset>
					<label class="required">Password</label>
					<input type="password" class="required" name="password" value="" tabindex="3" />
				</fieldset>
				
				<fieldset>
					<label class="required">User Level</label>
					<select name="level" tabindex="5">
						<option value="0">Normal User</option>
						<option value="1">Administrator</option>
						<? if ($admin->Level > 1) { ?><option value="2">Developer</option><? } ?>
					</select>
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
				
				<br /><br />
				<fieldset>
					<input type="checkbox" name="daily_digest" checked="checked" />
					<label class="for_checkbox">Daily Digest Email</label>
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" class="blue" value="Create" />
		</footer>
	</form>
</div>
<script type="text/javascript">
	new BigTreeFormValidator("form.module");
</script>