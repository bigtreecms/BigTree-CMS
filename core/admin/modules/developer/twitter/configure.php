<div class="container">
	<form method="post" action="<?=$mroot?>set-config/" class="module">	
		<section>
			<p>Please enter your Twitter app's Consumer Key and Consumer Secret below:</p>
			<fieldset>
				<label>Consumer Key</label>
				<input type="text" name="key" />
			</fieldset>
			<fieldset>
				<label>Consumer Secret</label>
				<input type="text" name="secret" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Save" />
		</footer>
	</form>
</div>