/**
 * Thin WebAuthn glue used by the passkey register + login flows.
 *
 * The server's `/auth/passkey/(register/)?options` endpoints return the
 * PublicKeyCredential* options *almost* in the browser's shape — except
 * everything that should be an `ArrayBuffer` arrives base64url-encoded
 * because JSON. This module:
 *
 *   1. Decodes incoming buffers (`challenge`, `user.id`, every
 *      `excludeCredentials[].id` / `allowCredentials[].id`).
 *   2. Calls `navigator.credentials.{create,get}`.
 *   3. Re-encodes the response buffers to base64url so the server-side
 *      WebAuthn library can verify them.
 *
 * No external dependency — `@simplewebauthn/browser` would do the same in
 * ~30kb; this is ~120 LOC and matches the server's encoding choices exactly.
 */

/* ── base64url ↔ ArrayBuffer ─────────────────────────────────────────── */

const base64urlToBytes = (input: string): Uint8Array => {
	const padded = input.replace(/-/g, "+").replace(/_/g, "/");
	const padLength = (4 - (padded.length % 4)) % 4;
	const padding = "=".repeat(padLength);
	const binary = atob(padded + padding);
	const bytes = new Uint8Array(binary.length);

	for (let i = 0; i < binary.length; i++) {
		bytes[i] = binary.charCodeAt(i);
	}

	return bytes;
};

const bytesToBase64url = (bytes: ArrayBuffer | Uint8Array): string => {
	const view = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
	let binary = "";

	for (let i = 0; i < view.length; i++) {
		binary += String.fromCharCode(view[i] as number);
	}

	return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
};

const toBuffer = (input: string): ArrayBuffer => {
	const bytes = base64urlToBytes(input);

	return bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength) as ArrayBuffer;
};

/* ── Server payload shapes ───────────────────────────────────────────── */

interface ServerCredentialDescriptor {
	id: string;
	type?: PublicKeyCredentialType;
	transports?: AuthenticatorTransport[];
}

interface ServerRegistrationOptions {
	challenge: string;
	rp: PublicKeyCredentialRpEntity;
	user: { id: string; name: string; displayName: string };
	pubKeyCredParams?: PublicKeyCredentialParameters[];
	timeout?: number;
	attestation?: AttestationConveyancePreference;
	authenticatorSelection?: AuthenticatorSelectionCriteria;
	excludeCredentials?: ServerCredentialDescriptor[];
	extensions?: AuthenticationExtensionsClientInputs;
}

interface ServerAuthenticationOptions {
	challenge: string;
	timeout?: number;
	rpId?: string;
	userVerification?: UserVerificationRequirement;
	allowCredentials?: ServerCredentialDescriptor[];
	extensions?: AuthenticationExtensionsClientInputs;
}

/* ── Public API ──────────────────────────────────────────────────────── */

/** True when the browser exposes the WebAuthn API. */
export const isWebAuthnSupported = (): boolean =>
	typeof window !== "undefined" &&
	typeof window.PublicKeyCredential !== "undefined" &&
	typeof navigator.credentials?.create === "function" &&
	typeof navigator.credentials?.get === "function";

export interface RegistrationResponse {
	clientDataJSON: string;
	attestationObject: string;
}

/**
 * Run the browser-side half of passkey registration.
 *
 * @throws DOMException        when the user cancels or the authenticator errors
 * @throws Error               when registration produces an unexpected response shape
 */
export const registerPasskey = async (
	options: ServerRegistrationOptions
): Promise<RegistrationResponse> => {
	const publicKey: PublicKeyCredentialCreationOptions = {
		challenge: toBuffer(options.challenge),
		rp: options.rp,
		user: {
			id: toBuffer(options.user.id),
			name: options.user.name,
			displayName: options.user.displayName,
		},
		pubKeyCredParams: options.pubKeyCredParams ?? [
			{ type: "public-key", alg: -7 }, // ES256
			{ type: "public-key", alg: -257 }, // RS256
		],
		timeout: options.timeout,
		attestation: options.attestation ?? "none",
		authenticatorSelection: options.authenticatorSelection,
		excludeCredentials: (options.excludeCredentials ?? []).map((cred) => ({
			id: toBuffer(cred.id),
			type: cred.type ?? "public-key",
			transports: cred.transports,
		})),
		extensions: options.extensions,
	};

	const credential = (await navigator.credentials.create({
		publicKey,
	})) as PublicKeyCredential | null;

	if (!credential) {
		throw new Error("Authenticator returned no credential");
	}

	const response = credential.response as AuthenticatorAttestationResponse;

	return {
		clientDataJSON: bytesToBase64url(response.clientDataJSON),
		attestationObject: bytesToBase64url(response.attestationObject),
	};
};

export interface AuthenticationResponse {
	credentialId: string;
	clientDataJSON: string;
	authenticatorData: string;
	signature: string;
}

/**
 * Run the browser-side half of passkey login.
 *
 * @throws DOMException        when the user cancels or no credential matches
 * @throws Error               when the response shape is unexpected
 */
export const authenticatePasskey = async (
	options: ServerAuthenticationOptions
): Promise<AuthenticationResponse> => {
	const publicKey: PublicKeyCredentialRequestOptions = {
		challenge: toBuffer(options.challenge),
		timeout: options.timeout,
		rpId: options.rpId,
		userVerification: options.userVerification,
		allowCredentials: (options.allowCredentials ?? []).map((cred) => ({
			id: toBuffer(cred.id),
			type: cred.type ?? "public-key",
			transports: cred.transports,
		})),
		extensions: options.extensions,
	};

	const credential = (await navigator.credentials.get({
		publicKey,
		mediation: "optional",
	})) as PublicKeyCredential | null;

	if (!credential) {
		throw new Error("Authenticator returned no credential");
	}

	const response = credential.response as AuthenticatorAssertionResponse;

	return {
		credentialId: bytesToBase64url(new Uint8Array(credential.rawId)),
		clientDataJSON: bytesToBase64url(response.clientDataJSON),
		authenticatorData: bytesToBase64url(response.authenticatorData),
		signature: bytesToBase64url(response.signature),
	};
};
