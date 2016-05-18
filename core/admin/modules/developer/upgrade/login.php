<?php
	namespace BigTree;
	
	$method = $_SESSION["bigtree_admin"]["upgrade_method"];	
?>
<form method="post" action="<?=DEVELOPER_ROOT?>upgrade/install/">
	<input type="hidden" name="type" value="<?=htmlspecialchars($_GET["type"])?>" />
	<div class="container">
		<summary><h2><?=Text::translate("Upgrade BigTree")?></h2></summary>
		<section>
			<div class="alert">
				<span></span>
				<p><?=Text::translate("<strong>Login Failed:</strong> Please enter the correct :update_method: username and password below.", false, array(":update_method:" => $method))?></p>
			</div>
			<fieldset>
				<label><?=Text::translate(":update_method: Username", false, array(":update_method:" => $method))?></label>
				<input type="text" name="username" autocomplete="off" />
			</fieldset>
			<fieldset>
				<label><?=Text::translate(":update_method: Password", false, array(":update_method:" => $method))?></label>
				<input type="password" name="password" autocomplete="off" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="blue" value="<?=Text::translate("Install", true)?>" />
		</footer>
	</div>
</form>