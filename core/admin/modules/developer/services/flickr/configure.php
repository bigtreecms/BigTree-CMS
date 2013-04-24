<div class="container">
	<form method="post" action="<?=$mroot?>set-config/" class="module">	
		<section>
			<p>You will first need to create a new <strong>Flickr App</strong>, if you have not already done so.</p>
			<p>Once your app is all set up, enter the app's <strong>Key</strong> and <strong>Secret</strong> below.</p>
			<br />
			<hr />
			<fieldset>
				<label>Key</label>
				<input type="text" name="key" autocomplete="off" />
			</fieldset>
			<fieldset>
				<label>Secret</label>
				<input type="text" name="secret" autocomplete="off" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Save" />
		</footer>
	</form>
</div>