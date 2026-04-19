<?php
	/**
	 * @global array $bigtree
	 * @global string $login_root
	 */

	if (isset($_GET["error"])) {
		$failure = true;
		$user = htmlspecialchars($_SESSION["bigtree_admin"]["failed_login_email"] ?? "");
	} else {
		$user = "";
		$failure = false;
	}
?>
<form method="post" action="<?=ADMIN_ROOT?>login/process/" class="module">
	<?php
		if (!empty($bigtree["ban_expiration"])) {
	?>
	<p class="error_message clear">You are temporarily banned due to failed login attempts.<br />You may try logging in again after <?=$bigtree["ban_expiration"]?>.</p>
	<?php
			if (!empty($bigtree["ban_is_user"])) {
	?>
	<fieldset>
		<p>You may <a href="<?=$login_root?>forgot-password/">reset your password</a> to remove your ban.</p>
	</fieldset>
	<br />
	<?php
			}
		} else {
			if ($failure) {
	?>
	<p class="error_message clear">You've entered an invalid email address and/or password.</p>
	<?php
			}

			if (!empty($_REQUEST["domain"])) {
	?>
	<input type="hidden" name="domain" value="<?=BigTree::safeEncode($_REQUEST["domain"])?>" />
	<?php
			}
	?>
	<fieldset>
		<label>Email</label>
		<input type="email" id="user" name="user" class="text" value="<?=$user?>"<?=BigTreeAdmin::passkeysEnabled() ? ' autocomplete="username webauthn"' : ""?> />
	</fieldset>
	<fieldset>
		<label>Password</label>
		<input type="password" id="password" name="password" class="text" />
		<?php
			if (empty($bigtree["security-policy"]["remember_disabled"]) || $bigtree["security-policy"]["remember_disabled"] != "on") {
		?>
		<p><input type="checkbox" name="stay_logged_in" checked="checked" /> Remember Me</p>
		<?php
			}
		?>
	</fieldset>
	<fieldset class="lower">
		<a href="<?=$login_root?>forgot-password/" class="forgot_password">Forgot Password?</a>
		<input type="submit" class="button blue" value="Login" />
	</fieldset>
	<?php
			if (BigTreeAdmin::passkeysEnabled()) {
	?>
	<fieldset class="lower" style="margin-top:.5em;border-top:1px solid #ddd;padding-top:1em">
		<a href="<?=ADMIN_ROOT?>login/passkey/" class="button">Sign In with Passkey</a>
	</fieldset>
	<?php
			}
		}
	?>
</form>
<?php
	if (BigTreeAdmin::passkeysEnabled()) {
?>
<script>
// Passkey conditional UI: silently watches for the user to pick a passkey from the
// email field's browser autofill dropdown. Resolves immediately if they do; otherwise
// it coexists with normal password login with no visible UI of its own.
(function() {
	if (!window.PublicKeyCredential || !PublicKeyCredential.isConditionalMediationAvailable) return;

	PublicKeyCredential.isConditionalMediationAvailable().then(function(available) {
		if (!available) return;

		// Fetch a challenge from the server
		fetch("<?=ADMIN_ROOT?>login/passkey/challenge/", { method: "POST" })
			.then(function(r) { return r.json(); })
			.then(function(options) {
				options.challenge = base64urlToBuffer(options.challenge);
				if (options.allowCredentials) {
					options.allowCredentials = options.allowCredentials.map(function(c) {
						return Object.assign({}, c, { id: base64urlToBuffer(c.id) });
					});
				}

				// mediation:"conditional" shows passkeys inline in the autofill dropdown
				// without any modal. The promise resolves only if the user picks one.
				return navigator.credentials.get({
					mediation: "conditional",
					publicKey: options
				});
			})
			.then(function(assertion) {
				if (!assertion) return;

				var payload = {
					id:                assertion.id,
					clientDataJSON:    bufferToBase64url(assertion.response.clientDataJSON),
					authenticatorData: bufferToBase64url(assertion.response.authenticatorData),
					signature:         bufferToBase64url(assertion.response.signature)
				};

				return fetch("<?=ADMIN_ROOT?>login/passkey/verify/", {
					method: "POST",
					headers: { "Content-Type": "application/json" },
					body: JSON.stringify(payload)
				}).then(function(r) { return r.json(); });
			})
			.then(function(result) {
				if (result && result.success) {
					window.location = result.redirect || "<?=ADMIN_ROOT?>";
				}
			})
			.catch(function() {
				// Silently ignore — user cancelled or the API is unavailable
			});
	});

	function base64urlToBuffer(b) {
		var padded = b + "===".slice(0, (4 - b.length % 4) % 4);
		var bin = atob(padded.replace(/-/g, "+").replace(/_/g, "/"));
		var buf = new Uint8Array(bin.length);
		for (var i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
		return buf.buffer;
	}

	function bufferToBase64url(buffer) {
		var bytes = new Uint8Array(buffer), bin = "";
		for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
		return btoa(bin).replace(/\+/g, "-").replace(/\//g, "_").replace(/=/g, "");
	}
})();
</script>
<?php
	}
?>