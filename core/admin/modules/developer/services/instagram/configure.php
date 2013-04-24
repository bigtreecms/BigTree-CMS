<div class="container">
	<form method="post" action="<?=$mroot?>set-config/" class="module">	
		<section>
			<p>You will first need to create a new Instagram app, if you have not already done so. Be sure to set the <strong>Redirect URI</strong> to:</p>
			<p style="margin-left: 20px;"><strong><?=$mroot?>return/</strong></p>
			<p>Once your app is all set up, enter the app's <strong>Client ID</strong> and <strong>Client Secret</strong> below.</p>
			<br />
			<hr />
			<fieldset>
				<label>Client ID</label>
				<input type="text" name="key" autocomplete="off" />
			</fieldset>
			<fieldset>
				<label>Client Secret</label>
				<input type="text" name="secret" autocomplete="off" />
			</fieldset>
			<fieldset>
				<label>Scope <small><a href="http://instagram.com/developer/authentication/#scope" target="_blank">Learn More</a></small></label>
				<select name="scope">
					<option value="basic">Basic</option>
					<option value="comments">Comments</option>
					<option value="relationships">Relationships</option>
					<option value="likes">Likes</option>
				</select>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Save" />
		</footer>
	</form>
</div>