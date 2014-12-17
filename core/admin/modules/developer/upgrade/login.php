<?
	$method = $_SESSION["bigtree_admin"]["upgrade_method"];	
?>
<form method="post" action="<?=DEVELOPER_ROOT?>upgrade/install/">
	<input type="hidden" name="type" value="<?=htmlspecialchars($_GET["type"])?>" />
	<div class="container">
		<summary><h2>Upgrade BigTree</h2></summary>
		<section>
			<div class="alert">
				<span></span>
				<p>
					<strong>Login Failed:</strong>
					Please enter the correct <?=$method?> username and password below.
				</p>
			</div>
			<fieldset>
				<label><?=$method?> Username</label>
				<input type="text" name="username" autocomplete="off" />
			</fieldset>
			<fieldset>
				<label><?=$method?> Password</label>
				<input type="password" name="password" autocomplete="off" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="blue" value="Install" />
		</footer>
	</div>
</form>