<div class="container">
	<form method="post" action="<?=$mroot?>set-config/" class="module">	
		<section>
			<p>Please enter your Instagram app's Client ID and Client Secret below:</p>
			<fieldset>
				<label>Client ID</label>
				<input type="text" name="id" />
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