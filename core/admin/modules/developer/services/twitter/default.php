<div class="container">
	<?
		$twitter = new BigTreeTwitterAPI;
		if (!$twitter->Connected) {
	?>
	<form method="post" action="<?=DEVELOPER_ROOT?>services/twitter/activate/" class="module">	
		<section>
			<p>To activate the Twitter API class you must follow these steps:</p>
			<hr />
			<ol>
				<li>Create a <a href="https://dev.twitter.com/apps" target="_blank">Twitter Application</a> at the Twitter Developers portal.</li>
				<li>Set the application's callback URL to <?=DOMAIN?></li>
				<li>Enter the application's "Consumer Key" and "Consumer Secret" below.</li>
				<li>Follow the OAuth process of allowing BigTree/your application access to your Twitter account.</li>
			</ol>
			<hr />
			<fieldset>
				<label>Consumer Key</label>
				<input type="text" name="key" value="<?=htmlspecialchars($twitter->Settings["key"])?>" />
			</fieldset>
			<fieldset>
				<label>Consumer Secret</label>
				<input type="text" name="secret" value="<?=htmlspecialchars($twitter->Settings["secret"])?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate Twitter API" />
		</footer>
	</form>
	<?
		} else {
	?>
	<section>
		<p>Currently connected to your account:</p>
		<div class="api_account_block">
			<img src="<?=$twitter->Settings["user_image"]?>" class="gravatar" />
			<strong><?=$twitter->Settings["user_name"]?></strong>
			#<?=$twitter->Settings["user_id"]?>
		</div>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>services/twitter/disconnect/" class="button red">Disconnect</a>
	</footer>
	<?
		}
	?>
</div>