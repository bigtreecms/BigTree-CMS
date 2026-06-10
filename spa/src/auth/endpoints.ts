import { api } from "@/api/client";
import { useAuthStore, type AuthUser, type EmulatedBy } from "@/auth/store";
import { authenticatePasskey, registerPasskey } from "@/lib/webauthn";

/** Wire-shape of POST /auth/login responses. */
interface LoginTokenResponse {
	access_token: string;
	refresh_token: string;
	token_type: "Bearer";
	expires_in: number;
	user: AuthUser;
}

/** Wire-shape of POST /auth/emulate — a token bundle plus the acting developer. */
interface EmulateResponse extends LoginTokenResponse {
	emulated_by: EmulatedBy;
}

interface LoginMfaResponse {
	mfa_required: true;
	mfa_token: string;
}

/** Security policy mandates TOTP and this user hasn't enrolled — login pauses for enrollment. */
interface LoginSetupRequiredResponse {
	two_factor_setup_required: true;
	setup_token: string;
}

type LoginResponse = LoginTokenResponse | LoginMfaResponse | LoginSetupRequiredResponse;

/** GET /auth/2fa/setup — the enrollment ceremony payload. */
export interface TwoFactorSetup {
	secret: string;
	qr_image: string;
	otpauth_uri: string;
}

/** Returned by enable/disable — current TOTP state for the user. */
export interface TwoFactorState {
	id: number;
	two_factor_enabled: boolean;
}

/** GET /auth/login-policy — the policy slice the login screen needs pre-auth. */
export interface LoginPolicy {
	remember_disabled: boolean;
}

export const authApi = {
	/**
	 * Security-policy flags that shape the login UI (e.g. hiding "Remember me").
	 * Cosmetic only — the server clamps regardless of what the client sends.
	 */
	loginPolicy: (): Promise<LoginPolicy> =>
		api.get<LoginPolicy>("/auth/login-policy", { skipAuth: true }),

	/**
	 * Submit credentials. Returns either a token bundle (immediate login) or
	 * an mfa_required envelope (login continues via twoFactor()).
	 */
	login: async (email: string, password: string, remember = false): Promise<LoginResponse> => {
		const data = await api.post<LoginResponse>(
			"/auth/login",
			{ email, password, remember },
			{ skipAuth: true }
		);
		if ("access_token" in data) {
			useAuthStore
				.getState()
				.setSession(data.access_token, data.refresh_token, data.expires_in, data.user);
		}
		return data;
	},

	/** Complete 2FA with the TOTP code. */
	twoFactor: async (mfaToken: string, code: string): Promise<LoginTokenResponse> => {
		const data = await api.post<LoginTokenResponse>(
			"/auth/2fa",
			{ mfa_token: mfaToken, code },
			{ skipAuth: true }
		);
		useAuthStore
			.getState()
			.setSession(data.access_token, data.refresh_token, data.expires_in, data.user);
		return data;
	},

	/**
	 * Begin self-service TOTP enrollment — returns a fresh secret, a QR image
	 * (data URI) and the raw otpauth:// URI for manual entry. The secret is
	 * held client-side and posted back to enableTwoFactor() once the user
	 * proves they can generate a valid code.
	 */
	twoFactorSetup: () => api.get<TwoFactorSetup>("/auth/2fa/setup"),

	/** Finish enrollment: verify the code against the pending secret + store it. */
	enableTwoFactor: (secret: string, code: string) =>
		api.post<TwoFactorState>("/auth/2fa/enable", { secret, code }),

	/**
	 * Forced-enrollment ceremony start (policy mandates 2FA, user has no secret).
	 * Authenticated solely by the setup token from the login response.
	 */
	twoFactorSetupRequired: (setupToken: string) =>
		api.post<TwoFactorSetup>(
			"/auth/2fa/setup-required",
			{ setup_token: setupToken },
			{ skipAuth: true }
		),

	/**
	 * Finish forced enrollment: verify the code, persist the secret, and complete
	 * the login — the response is a full token bundle, installed like any login.
	 */
	enableTwoFactorRequired: async (
		setupToken: string,
		secret: string,
		code: string
	): Promise<LoginTokenResponse> => {
		const data = await api.post<LoginTokenResponse>(
			"/auth/2fa/enable-required",
			{ setup_token: setupToken, secret, code },
			{ skipAuth: true }
		);
		useAuthStore
			.getState()
			.setSession(data.access_token, data.refresh_token, data.expires_in, data.user);

		return data;
	},

	/** Turn off TOTP for the current user (requires a valid current code). */
	disableTwoFactor: (code: string) => api.post<TwoFactorState>("/auth/2fa/disable", { code }),

	/** End the session — revokes the refresh token server-side, clears localStorage. */
	logout: async (): Promise<void> => {
		const refresh = useAuthStore.getState().refreshToken;
		try {
			await api.post<void>("/auth/logout", refresh ? { refresh_token: refresh } : undefined, {
				skipAuth: true,
			});
		} catch {
			// best effort; even if the server call fails we still want to clear state locally
		}
		useAuthStore.getState().clear();
	},

	/** Sign out from every device (bumps token_version). */
	logoutAll: async (): Promise<void> => {
		await api.post<void>("/auth/logout-all");
		useAuthStore.getState().clear();
	},

	me: async (): Promise<AuthUser> => api.get<AuthUser>("/auth/me"),

	/**
	 * Developer-only: assume another user's identity. Parks the developer's own
	 * session and installs the emulated one. Returning is local-only via
	 * stopEmulating() — the developer's parked tokens are restored, no server
	 * round-trip needed.
	 */
	emulate: async (userId: number): Promise<EmulateResponse> => {
		const data = await api.post<EmulateResponse>("/auth/emulate", { user_id: userId });
		useAuthStore
			.getState()
			.startEmulation(
				data.access_token,
				data.refresh_token,
				data.expires_in,
				data.user,
				data.emulated_by
			);

		return data;
	},

	/** Drop the emulated session and restore the developer's own. */
	stopEmulating: (): void => {
		useAuthStore.getState().stopEmulation();
	},

	forgotPassword: async (email: string): Promise<void> => {
		await api.post<void>("/auth/forgot-password", { email }, { skipAuth: true });
	},

	resetPassword: async (token: string, password: string): Promise<void> => {
		await api.post<void>("/auth/reset-password", { token, password }, { skipAuth: true });
	},

	/**
	 * Sign in with a passkey. Runs the discoverable-credential WebAuthn flow:
	 *
	 *   1. GET /auth/passkey/options       → challenge + (optional) allowCredentials
	 *   2. navigator.credentials.get(…)    → user picks a credential / biometric
	 *   3. POST /auth/passkey/verify       → server returns a token bundle
	 *   4. Session set into the auth store, same shape as password login.
	 *
	 * Throws on cancellation / unsupported / server rejection — the caller is
	 * responsible for surfacing a friendly error.
	 */
	loginWithPasskey: async (): Promise<LoginTokenResponse> => {
		const options = await api.get<PasskeyChallenge>("/auth/passkey/options", {
			skipAuth: true,
		});
		const assertion = await authenticatePasskey(options.options);
		const data = await api.post<LoginTokenResponse>(
			"/auth/passkey/verify",
			{
				challenge_id: options.challenge_id,
				credential_id: assertion.credentialId,
				client_data_json: assertion.clientDataJSON,
				authenticator_data: assertion.authenticatorData,
				signature: assertion.signature,
			},
			{ skipAuth: true }
		);

		useAuthStore
			.getState()
			.setSession(data.access_token, data.refresh_token, data.expires_in, data.user);

		return data;
	},
};

/* — Passkey management (Profile → Security) ─────────────────────────────── */

interface PasskeyChallenge {
	challenge_id: string;
	options: Parameters<typeof authenticatePasskey>[0];
}

interface PasskeyRegisterChallenge {
	challenge_id: string;
	options: Parameters<typeof registerPasskey>[0];
}

export interface PasskeyRecord {
	id: number;
	name: string;
	aaguid: string;
	transports: string;
	created_at: string;
	last_used: string | null;
}

export interface RegisteredPasskey {
	id: number;
	name: string;
	credential_id: string;
	aaguid: string;
}

export const passkeysApi = {
	list: () => api.get<PasskeyRecord[]>("/auth/passkeys"),

	delete: (id: number) => api.delete<void>(`/auth/passkeys/${id}`),

	/**
	 * Run the full register flow:
	 *   1. ask server for options
	 *   2. invoke the authenticator
	 *   3. POST the attestation back with a user-supplied display name
	 */
	register: async (name: string): Promise<RegisteredPasskey> => {
		const challenge = await api.get<PasskeyRegisterChallenge>("/auth/passkey/register/options");
		const attestation = await registerPasskey(challenge.options);

		return api.post<RegisteredPasskey>("/auth/passkey/register/verify", {
			challenge_id: challenge.challenge_id,
			client_data_json: attestation.clientDataJSON,
			attestation_object: attestation.attestationObject,
			name,
		});
	},
};
