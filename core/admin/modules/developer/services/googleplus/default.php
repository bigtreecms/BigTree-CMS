<div class="container">
	<?
		$googleplus = new BigTreeGooglePlusAPI;
		if (!$googleplus->Connected) {
	?>
	<form method="post" action="<?=DEVELOPER_ROOT?>services/googleplus/activate/" class="module">	
		<section>
			<p>To activate the Google+ API class you must follow these steps:</p>
			<hr />
			<ol>
				<li>Login to the <a href="https://code.google.com/apis/console/">Google API Console</a> and enable access to the Google+ API.</li>
				<li>Choose the "API Access" tab in the API Console and create an OAuth 2.0 client ID if you have not already done so.</li>
				<li>Add <?=ADMIN_ROOT?>developer/services/googleplus/return/ as an Authorized Redirect URI.</li>
				<li>Enter your Client ID and Client Secret from the API Console below.</li>
				<li>Follow the OAuth process of allowing BigTree/your application access to your Google+ account.</li>
			</ol>
			<hr />
			<fieldset>
				<label>Client ID</label>
				<input type="text" name="key" value="<?=htmlspecialchars($googleplus->Settings["key"])?>" />
			</fieldset>
			<fieldset>
				<label>Client Secret</label>
				<input type="text" name="secret" value="<?=htmlspecialchars($googleplus->Settings["secret"])?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate Google+ API" />
		</footer>
	</form>
	<?
		} else {
	?>
	<section>
		<p>Currently connected to your account:</p>
		<div class="api_account_block">
			<img src="<?=$googleplus->Settings["user_image"]?>" class="gravatar" />
			<strong><?=$googleplus->Settings["user_name"]?></strong>
			#<?=$googleplus->Settings["user_id"]?>
		</div>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>services/googleplus/disconnect/" class="button red">Disconnect</a>
	</footer>
	<?
		}
	?>
</div>