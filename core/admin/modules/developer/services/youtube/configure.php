<div class="container">
	<form method="post" action="<?=$mroot?>set-config/" class="module">	
		<section>
			<p>You will first need to create a new <strong>Web Application</strong> in your <strong>Google APIs Console</strong>, if you have not already done so. <br />Be sure to add the following to the list of <strong>Authorized Redirect URIs</strong>:</p>
			<p style="margin-left: 20px;"><strong><?=$mroot?>return/</strong></p>
			<p>Once your app is all set up, enter the app's <strong>Consumer Key</strong> and <strong>Consumer Secret</strong> below.</p>
			<br />
			<hr />
			<fieldset>
				<label>Client ID</label>
				<input type="text" name="key" />
			</fieldset>
			<fieldset>
				<label>Client Secret</label>
				<input type="text" name="secret" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Save" />
		</footer>
	</form>
</div>