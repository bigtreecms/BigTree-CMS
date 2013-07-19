<div class="container">
	<?
		$flickr = new BigTreeFlickrAPI;
		if (!$flickr->Connected) {
	?>
	<form method="post" action="<?=DEVELOPER_ROOT?>services/flickr/activate/" class="module">	
		<section>
			<p>To activate the Flickr API class you must follow these steps:</p>
			<hr />
			<ol>
				<li><a href="http://www.flickr.com/services/apps/create/apply/" target="_blank">Create a Flickr app</a> in The App Garden.</li>
				<li>Enter your Key and Secret that you receive below.</li>
				<li>Follow the OAuth process of allowing BigTree/your application access to your Flickr account.</li>
			</ol>
			<hr />
			<fieldset>
				<label>Key</label>
				<input type="text" name="key" value="<?=htmlspecialchars($flickr->Settings["key"])?>" />
			</fieldset>
			<fieldset>
				<label>Secret</label>
				<input type="text" name="secret" value="<?=htmlspecialchars($flickr->Settings["secret"])?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate Flickr API" />
		</footer>
	</form>
	<?
		} else {
	?>
	<section>
		<p>Currently connected as:</p>
		<div class="api_account_block">
			<img src="<?=$flickr->Settings["user_image"]?>" class="gravatar" />
			<strong><?=$flickr->Settings["user_name"]?></strong>
			#<?=$flickr->Settings["user_id"]?>
		</div>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>services/flickr/disconnect/" class="button red">Disconnect</a>
	</footer>
	<?
		}
	?>
</div>