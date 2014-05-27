<?	
	$e = false;

	if (isset($_SESSION["bigtree_admin"]["create_user"])) {
		BigTree::globalizeArray($_SESSION["bigtree_admin"]["create_user"],array("htmlspecialchars"));
		$daily_digest = isset($daily_digest) ? $daily_digest : false;
		$e = true;
		unset($_SESSION["bigtree_admin"]["create_user"]);
	} else {
		$email = "";
		$name = "";
		$company = "";
		$daily_digest = "on";
		$level = 0;
	}
?>
<div class="container">
	<form class="module" action="<?=ADMIN_ROOT?>users/create/" method="post">	
		<section>
			<p class="error_message"<? if (!$e) { ?> style="display: none;"<? } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="left">
				<fieldset<? if ($e) { ?> class="form_error"<? } ?> style="position: relative;">
					<label class="required">Email <small>(Profile images from <a href="http://www.gravatar.com/" target="_blank">Gravatar</a>)</small> <? if ($e) { ?><span class="form_error_reason">Already In Use</span><? } ?></label>
					<input type="text" class="required email" name="email" autocomplete="off" value="<?=$email?>" tabindex="1" />
					<span class="gravatar"<? if ($email != "") echo ' style="display: block;"'; ?>><img src="<?=BigTree::gravatar($email, 36)?>" alt="" /></span>
				</fieldset>
				
				<fieldset>
					<label class="required">Password</label>
					<input type="password" class="required" name="password" value="" tabindex="3" />
				</fieldset>
				
				<fieldset>
					<label class="required">User Level</label>
					<select name="level" tabindex="5">
						<option value="0">Normal User</option>
						<option value="1"<? if ($level == 1) { ?> selected="selected"<? } ?>>Administrator</option>
						<? if ($admin->Level > 1) { ?><option value="2"<? if ($level == 2) { ?> selected="selected"<? } ?>>Developer</option><? } ?>
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
				
				<br />
				<fieldset>
					<input type="checkbox" name="daily_digest" <? if ($daily_digest) { ?>checked="checked" <? } ?>/>
					<label class="for_checkbox">Daily Digest Email</label>
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" class="blue" value="Create" />
		</footer>
	</form>
</div>
<script>
	new BigTreeFormValidator("form.module");
	new BigTreePasswordInput("input[type=password]");
	
	$(document).ready(function() {
		$("input.email").blur(function() {
			$(this).parent("fieldset").find(".gravatar").show().find("img").attr("src", 'http://www.gravatar.com/avatar/' + md5($(this).val().trim()) + '?s=36&d=' + encodeURIComponent("<?=ADMIN_ROOT?>images/icon_default_gravatar.jpg") + '&rating=pg');
		});
	});
</script>