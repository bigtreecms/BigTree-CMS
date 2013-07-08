<div class="container">
	<?
		$youtube = new BigTreeYouTubeAPI;
		if (!$youtube->Connected) {
	?>
	<form method="post" action="<?=DEVELOPER_ROOT?>services/youtube/activate/" class="module">	
		<section>
			<p>To activate the YouTube API class you must follow these steps:</p>
			<hr />
			<ol>
				<li>Login to the <a href="https://code.google.com/apis/console/">Google API Console</a> and enable access to the YouTube Data API.</li>
				<li>Choose the "API Access" tab in the API Console and create an OAuth 2.0 client ID if you have not already done so.</li>
				<li>Add <?=ADMIN_ROOT?>developer/services/youtube/return/ as an Authorized Redirect URI.</li>
				<li>Enter your Client ID and Client Secret from the API Console below.</li>
				<li>Follow the OAuth process of allowing BigTree/your application access to your YouTube account.</li>
			</ol>
			<hr />
			<fieldset>
				<label>Client ID</label>
				<input type="text" name="key" value="<?=htmlspecialchars($youtube->Settings["key"])?>" />
			</fieldset>
			<fieldset>
				<label>Client Secret</label>
				<input type="text" name="secret" value="<?=htmlspecialchars($youtube->Settings["secret"])?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate YouTube API" />
		</footer>
	</form>
	<?
		} else {
	?>
	<section>
		<p>Currently connected as:</p>
		<div class="api_account_block">
			<img src="<?=$youtube->Settings["user_image"]?>" class="gravatar" />
			<strong><?=$youtube->Settings["user_name"]?></strong>
			#<?=$youtube->Settings["user_id"]?>
		</div>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>services/youtube/disconnect/" class="button red">Disconnect</a>
	</footer>
	<?
		}
	?>
</div>