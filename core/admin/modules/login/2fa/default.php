<form method="post" action="<?=ADMIN_ROOT?>login/2fa/verify/" class="module">
	<?php
		if (isset($_GET["error"])) {
	?>
	<p class="error_message clear">The code you entered expired or was incorrect.</p>
	<?php
		}
	?>
	<fieldset>
		<label>Authenticator Code</label>
		<input type="number" name="code" class="text" />
	</fieldset>
	<fieldset class="lower">
		<input type="submit" class="button blue" value="Verify" />
	</fieldset>
</form>