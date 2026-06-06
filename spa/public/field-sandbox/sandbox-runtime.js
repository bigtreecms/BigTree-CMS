/**
 * BigTree field sandbox runtime. Runs inside the sandboxed iframe (opaque
 * origin, no admin DOM/cookie access) and bridges an untrusted field-type
 * module to the parent SPA over postMessage. See:
 *   - spa/src/renderer/forms/fieldSandboxProtocol.ts (message shapes)
 *   - spa/src/renderer/forms/fieldModuleContract.ts  (the module contract)
 *
 * Flow: announce "ready" → receive "init" → fetch the module, verify its SRI
 * hash, import it from a blob, render it via the contract → "mounted". The
 * module's onChange posts "change"; host value/disabled/error arrive as
 * "update". Any failure posts "error" and the parent falls back to a stub.
 */
const channel = new URLSearchParams(location.hash.slice(1)).get("c") || "";
const mount = document.getElementById("mount") || document.body;

let instance = null;
let state = { value: undefined, field: {}, disabled: false, error: undefined };

const send = (msg) => parent.postMessage({ ...msg, channel }, "*");

const buildHost = () => ({
	element: mount,
	value: state.value,
	field: state.field,
	disabled: !!state.disabled,
	error: state.error,
	onChange: (next) => send({ type: "change", value: next }),
});

async function verifyIntegrity(buffer, integrity) {
	if (!integrity || typeof integrity !== "string") {
		throw new Error("Refusing to run an unsigned module in the sandbox.");
	}

	const match = integrity.match(/^sha(256|384|512)-(.+)$/);

	if (!match) {
		throw new Error("Unsupported integrity format (expected sha256/384/512).");
	}

	const algo = { 256: "SHA-256", 384: "SHA-384", 512: "SHA-512" }[match[1]];
	const digest = await crypto.subtle.digest(algo, buffer);
	const actual = btoa(String.fromCharCode(...new Uint8Array(digest)));

	if (actual !== match[2]) {
		throw new Error("Integrity check failed — module hash does not match.");
	}
}

async function loadAndRender(init) {
	state = { value: init.value, field: init.field, disabled: init.disabled, error: init.error };

	const response = await fetch(init.assetUrl, { credentials: "omit", mode: "cors" });

	if (!response.ok) {
		throw new Error("Failed to fetch module (" + response.status + ").");
	}

	const buffer = await response.arrayBuffer();
	await verifyIntegrity(buffer, init.integrity);

	const url = URL.createObjectURL(new Blob([buffer], { type: "text/javascript" }));
	let mod;

	try {
		mod = await import(url);
	} finally {
		URL.revokeObjectURL(url);
	}

	const def = mod.default;

	if (!def || typeof def.render !== "function" || typeof def.contractVersion !== "number") {
		throw new Error("Module did not default-export a valid field contract.");
	}

	if (Math.floor(def.contractVersion) > init.contractVersion) {
		throw new Error("Module needs a newer contract than the host supports.");
	}

	instance = def.render(buildHost()) || null;
	send({ type: "mounted" });
}

window.addEventListener("message", async (event) => {
	if (event.source !== parent) {
		return;
	}

	const data = event.data;

	if (!data || data.channel !== channel || typeof data.type !== "string") {
		return;
	}

	if (data.type === "init") {
		try {
			await loadAndRender(data);
		} catch (err) {
			send({ type: "error", message: String((err && err.message) || err) });
		}
	} else if (data.type === "update") {
		state = { ...state, value: data.value, disabled: data.disabled, error: data.error };

		try {
			if (instance && typeof instance.update === "function") {
				instance.update(buildHost());
			}
		} catch (err) {
			send({ type: "error", message: String((err && err.message) || err) });
		}
	}
});

// Report content height so the parent can size the iframe to fit.
if (typeof ResizeObserver === "function") {
	new ResizeObserver(() => {
		send({ type: "resize", height: document.documentElement.scrollHeight });
	}).observe(document.documentElement);
}

send({ type: "ready" });
