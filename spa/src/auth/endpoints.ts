import { api } from "@/api/client";
import { useAuthStore, type AuthUser } from "@/auth/store";

/** Wire-shape of POST /auth/login responses. */
interface LoginTokenResponse {
	access_token: string;
	refresh_token: string;
	token_type: "Bearer";
	expires_in: number;
	user: AuthUser;
}

interface LoginMfaResponse {
	mfa_required: true;
	mfa_token: string;
}

type LoginResponse = LoginTokenResponse | LoginMfaResponse;

export const authApi = {
	/**
	 * Submit credentials. Returns either a token bundle (immediate login) or
	 * an mfa_required envelope (login continues via twoFactor()).
	 */
	login: async (email: string, password: string, remember = false): Promise<LoginResponse> => {
		const data = await api.post<LoginResponse>(
			"/auth/login",
			{ email, password, remember },
			{ skipAuth: true },
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
			{ skipAuth: true },
		);
		useAuthStore
			.getState()
			.setSession(data.access_token, data.refresh_token, data.expires_in, data.user);
		return data;
	},

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

	forgotPassword: async (email: string): Promise<void> => {
		await api.post<void>("/auth/forgot-password", { email }, { skipAuth: true });
	},

	resetPassword: async (token: string, password: string): Promise<void> => {
		await api.post<void>("/auth/reset-password", { token, password }, { skipAuth: true });
	},
};
