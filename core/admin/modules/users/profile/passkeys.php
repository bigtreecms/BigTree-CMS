<?php
	/**
	 * Passkey management page — accessible at /admin/users/profile/passkeys/
	 * Lists, registers, and deletes WebAuthn credentials for the current user.
	 */
	if (!BigTreeAdmin::passkeysEnabled()) {
?>
<div class="container">
	<div class="module">
		<section>
			<p class="error_message clear">Passkeys are not available on this server (the OpenSSL PHP extension is required).</p>
		</section>
		<footer>
			<a href="<?=ADMIN_ROOT?>users/profile/" class="button">Back to Profile</a>
		</footer>
	</div>
</div>
<?php
	} else {
		$passkeys = BigTreeAdmin::getUserPasskeys($admin->ID);
?>
<div class="container">
	<div class="module">
		<section>
			<p>Passkeys let you sign in using your device's biometrics or PIN instead of a password. Each device or security key registers separately.</p>
			<hr>
			<div id="passkeys_list">
				<?php if (empty($passkeys)) { ?>
				<p id="passkeys_empty">No passkeys registered yet.</p>
				<?php } else { ?>
				<table class="table" id="passkeys_table">
					<thead>
						<tr>
							<th>Name</th>
							<th>Registered</th>
							<th>Last Used</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($passkeys as $pk) { ?>
						<tr data-id="<?=(int)$pk["id"]?>">
							<td><?=htmlspecialchars($pk["name"])?></td>
							<td><?=date("M j, Y", strtotime($pk["created_at"]))?></td>
							<td><?=$pk["last_used"] ? date("M j, Y", strtotime($pk["last_used"])) : "Never"?></td>
							<td style="text-align:right">
								<button type="button" class="button small red passkey_delete_btn" data-id="<?=(int)$pk["id"]?>">Remove</button>
							</td>
						</tr>
						<?php } ?>
					</tbody>
				</table>
				<?php } ?>
			</div>
			<div id="passkey_register_error" class="error_message" style="display:none;margin-top:1em"></div>
		</section>
		<footer>
			<a href="<?=ADMIN_ROOT?>users/profile/" class="button">Back to Profile</a>
			<button type="button" id="passkey_add_btn" class="button blue">Add a Passkey</button>
		</footer>
	</div>
</div>
<script>
(function() {
	var addBtn = document.getElementById("passkey_add_btn");
	var errBox = document.getElementById("passkey_register_error");

	// ── Register a new passkey ──────────────────────────────────────────────
	addBtn.addEventListener("click", async function() {
		errBox.style.display = "none";
		addBtn.disabled = true;
		addBtn.textContent = "Waiting for authenticator…";

		try {
			// 1. Fetch registration challenge
			var challengeResp = await fetch("<?=ADMIN_ROOT?>users/profile/passkeys/challenge/", {
				method: "POST",
				headers: { "Content-Type": "application/json" }
			});
			var options = await challengeResp.json();

			// 2. Convert base64url fields to ArrayBuffers
			options.challenge = base64urlToBuffer(options.challenge);
			options.user.id   = base64urlToBuffer(options.user.id);
			if (options.excludeCredentials) {
				options.excludeCredentials = options.excludeCredentials.map(function(c) {
					return Object.assign({}, c, { id: base64urlToBuffer(c.id) });
				});
			}

			// 3. Invoke authenticator
			var credential = await navigator.credentials.create({ publicKey: options });

			// 4. Ask for a friendly name
			var name = prompt("Give this passkey a name (e.g. \"Work MacBook Touch ID\"):", "My passkey");
			if (name === null) {
				addBtn.disabled = false;
				addBtn.textContent = "Add a Passkey";
				return;
			}

			// 5. Send response to server
			var transports = [];
			if (credential.response.getTransports) {
				transports = credential.response.getTransports();
			}

			var payload = {
				id:               credential.id,
				clientDataJSON:   bufferToBase64url(credential.response.clientDataJSON),
				attestationObject: bufferToBase64url(credential.response.attestationObject),
				transports:       transports,
				name:             name
			};

			var finishResp = await fetch("<?=ADMIN_ROOT?>users/profile/passkeys/finish/", {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify(payload)
			});
			var result = await finishResp.json();

			if (result.success) {
				window.location.reload();
			} else {
				showError(result.error || "Registration failed. Please try again.");
				addBtn.disabled = false;
				addBtn.textContent = "Add a Passkey";
			}
		} catch (err) {
			if (err.name !== "NotAllowedError") {
				showError(err.message || "An error occurred. Please try again.");
			}
			addBtn.disabled = false;
			addBtn.textContent = "Add a Passkey";
		}
	});

	// ── Delete a passkey ────────────────────────────────────────────────────
	document.addEventListener("click", async function(e) {
		var btn = e.target.closest(".passkey_delete_btn");
		if (!btn) return;

		if (!confirm("Remove this passkey? You will no longer be able to sign in with it.")) return;

		var id = btn.dataset.id;
		btn.disabled = true;

		try {
			var resp = await fetch("<?=ADMIN_ROOT?>users/profile/passkeys/delete/", {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify({ id: parseInt(id, 10) })
			});
			var result = await resp.json();

			if (result.success) {
				var row = btn.closest("tr");
				if (row) row.remove();
				// If no rows left, show empty message
				var tbody = document.querySelector("#passkeys_table tbody");
				if (tbody && !tbody.querySelector("tr")) {
					document.getElementById("passkeys_list").innerHTML = '<p id="passkeys_empty">No passkeys registered yet.</p>';
				}
			} else {
				showError(result.error || "Could not remove passkey.");
				btn.disabled = false;
			}
		} catch (err) {
			showError("An error occurred. Please try again.");
			btn.disabled = false;
		}
	});

	function showError(msg) {
		errBox.textContent = msg;
		errBox.style.display = "block";
	}

	function base64urlToBuffer(base64url) {
		var padded = base64url + "===".slice(0, (4 - base64url.length % 4) % 4);
		var binary = atob(padded.replace(/-/g, "+").replace(/_/g, "/"));
		var buf = new Uint8Array(binary.length);
		for (var i = 0; i < binary.length; i++) buf[i] = binary.charCodeAt(i);
		return buf.buffer;
	}

	function bufferToBase64url(buffer) {
		var bytes = new Uint8Array(buffer);
		var binary = "";
		for (var i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
		return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=/g, "");
	}
})();
</script>
<?php
	}
?>