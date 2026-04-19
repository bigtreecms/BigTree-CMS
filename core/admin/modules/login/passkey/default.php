<?php
	/**
	 * Passkey login UI — shown at /admin/login/passkey/
	 * Performs a discoverable-credential WebAuthn ceremony and POSTs the assertion
	 * to /admin/login/passkey/verify/, which establishes the session and redirects.
	 */

	if (!BigTreeAdmin::passkeysEnabled()) {
?>
<div id="passkey_login">
	<div class="module">
		<fieldset>
			<p class="error_message clear">Passkeys are not available on this server (the OpenSSL PHP extension is required).</p>
		</fieldset>
		<fieldset class="lower">
			<a href="<?=ADMIN_ROOT?>login/" class="button">Back to Login</a>
		</fieldset>
	</div>
</div>
<?php
	} else {
?>
<div id="passkey_login">
	<?php if (isset($_GET["error"])) { ?>
	<p class="error_message clear"><?=htmlspecialchars($_GET["error"] ?? "Authentication failed. Please try again.")?></p>
	<?php } ?>
	<div class="module">
		<fieldset>
			<p>Sign in using a passkey saved to this device or a nearby device.</p>
		</fieldset>
		<fieldset class="lower">
			<a href="<?=ADMIN_ROOT?>login/" class="forgot_password">Use password instead</a>
			<button type="button" id="passkey_btn" class="button blue">Sign In with Passkey</button>
		</fieldset>
	</div>
</div>
<script>
(function() {
	document.getElementById("passkey_btn").addEventListener("click", async function() {
		const btn = this;
		btn.disabled = true;
		btn.textContent = "Waiting for authenticator…";

		try {
			// 1. Fetch challenge from server
			const challengeResp = await fetch("<?=ADMIN_ROOT?>login/passkey/challenge/", {
				method: "POST",
				headers: { "Content-Type": "application/json" }
			});
			const options = await challengeResp.json();

			// 2. Convert base64url challenge to ArrayBuffer for the WebAuthn API
			options.challenge = base64urlToBuffer(options.challenge);
			if (options.allowCredentials) {
				options.allowCredentials = options.allowCredentials.map(function(c) {
					return Object.assign({}, c, { id: base64urlToBuffer(c.id) });
				});
			}

			// 3. Invoke browser's WebAuthn authenticator
			const assertion = await navigator.credentials.get({ publicKey: options });

			// 4. Encode response fields back to base64url for transmission
			const payload = {
				id:                 assertion.id,
				clientDataJSON:     bufferToBase64url(assertion.response.clientDataJSON),
				authenticatorData:  bufferToBase64url(assertion.response.authenticatorData),
				signature:          bufferToBase64url(assertion.response.signature),
				stay_logged_in:     false
			};

			// 5. POST to verify endpoint — on success it will redirect via header()
			const verifyResp = await fetch("<?=ADMIN_ROOT?>login/passkey/verify/", {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify(payload)
			});
			const result = await verifyResp.json();

			if (result.success) {
				window.location = result.redirect || "<?=ADMIN_ROOT?>";
			} else {
				window.location = "<?=ADMIN_ROOT?>login/passkey/?error=" + encodeURIComponent(result.error || "Authentication failed");
			}
		} catch (err) {
			if (err.name === "NotAllowedError") {
				btn.disabled = false;
				btn.textContent = "Sign In with Passkey";
			} else {
				window.location = "<?=ADMIN_ROOT?>login/passkey/?error=" + encodeURIComponent(err.message || "Authentication error");
			}
		}
	});

	function base64urlToBuffer(base64url) {
		var padded = base64url + "===".slice(0, (4 - base64url.length % 4) % 4);
		var binary = atob(padded.replace(/-/g, "+").replace(/_/g, "/"));
		var buf = new Uint8Array(binary.length);
		for (var i = 0; i < binary.length; i++) {
			buf[i] = binary.charCodeAt(i);
		}
		return buf.buffer;
	}

	function bufferToBase64url(buffer) {
		var bytes = new Uint8Array(buffer);
		var binary = "";
		for (var i = 0; i < bytes.length; i++) {
			binary += String.fromCharCode(bytes[i]);
		}
		return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=/g, "");
	}
})();
</script>
<?php
	}
?>