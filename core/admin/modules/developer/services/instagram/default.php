<div class="container">
	<?
		$instagram = new BigTreeInstagramAPI;
		if (!$instagram->Connected) {
	?>
	<form method="post" action="<?=DEVELOPER_ROOT?>services/instagram/activate/" class="module">	
		<section>
			<p>To activate the Instagram API class you must follow these steps:</p>
			<hr />
			<ol>
				<li>Create an <a href="http://instagram.com/developer/clients/register/" target="_blank">Instagram Application</a> at the Instagram developer portal.</li>
				<li>Set the application's OAuth redirect_uri to <?=ADMIN_ROOT?>developer/services/instagram/return/</li>
				<li>Enter the application's "Client ID" and "Client Secret" below.</li>
				<li>Follow the OAuth process of allowing BigTree/your application access to your Instagram account.</li>
			</ol>
			<hr />
			<fieldset>
				<label>Client ID</label>
				<input type="text" name="key" value="<?=htmlspecialchars($instagram->Settings["key"])?>" />
			</fieldset>
			<fieldset>
				<label>Client Secret</label>
				<input type="text" name="secret" value="<?=htmlspecialchars($instagram->Settings["secret"])?>" />
			</fieldset>
			<fieldset>
				<label>Scope <small><a href="http://instagram.com/developer/authentication/#scope" target="_blank">?</a></small></label>
				<select name="scope">
					<option value="basic">Basic</option>
					<option value="comments">Comments</option>
					<option value="relationships">Relationships</option>
					<option value="likes">Likes</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate Instagram API" />
		</footer>
	</form>
	<?
		} else {
	?>
	<section>
		<p>Currently connected to your account:</p>
		<div class="api_account_block">
			<img src="<?=$instagram->Settings["user_image"]?>" class="gravatar" />
			<strong><?=$instagram->Settings["user_name"]?></strong>
			#<?=$instagram->Settings["user_id"]?>
		</div>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>services/instagram/disconnect/" class="button red">Disconnect</a>
	</footer>
	<?
		}
	?>
</div>