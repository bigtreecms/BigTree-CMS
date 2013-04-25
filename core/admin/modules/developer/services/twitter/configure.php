<div class="container">
	<form method="post" action="<?=$mroot?>set-config/" class="module">	
		<section>
			<p>You will first need to create a new Twitter app, if you have not already done so. Be sure to set the <strong>Callback URL</strong> to:</p>
			<p style="margin-left: 20px;"><strong><?=WWW_ROOT?></strong></p>
			<p>Once your app is all set up, enter the app's <strong>Consumer Key</strong> and <strong>Consumer Secret</strong> below.</p>
			<br />
			<hr />
			<fieldset>
				<label>Consumer Key</label>
				<input type="text" name="key" autocomplete="off" />
			</fieldset>
			<fieldset>
				<label>Consumer Secret</label>
				<input type="text" name="secret" autocomplete="off" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Save" />
		</footer>
	</form>
</div>