<?
	$breadcrumb[] = array("title" => "Profile", "link" => "#");
	$user = $admin->getUser($admin->ID);
	foreach ($user as $key => $val) {
		if (!is_array($val)) {
			$$key = htmlspecialchars($val);
		}
	}
?>
<h1><span class="users"></span>Profile</h1>
<div class="form_container">
	<form class="module" action="<?=$admin_root?>users/profile/update/" method="post">
		<section>
			<div class="left">
				<fieldset>
					<label>Name</label>
					<input type="text" name="name" value="<?=$name?>" tabindex="1" />
				</fieldset>
				<fieldset>
					<label>Password <small>(leave blank to remain unchanged)</small></label>
					<input type="password" name="password" value="" tabindex="3" />
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