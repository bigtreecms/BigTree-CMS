import { create } from "zustand";

/**
 * Auth state. Both tokens are persisted to localStorage:
 *
 *   - accessToken: short-lived JWT (~15min), used as Authorization: Bearer
 *   - refreshToken: long-lived rotation token, sent in body to /auth/refresh
 *
 * Why localStorage instead of HttpOnly cookies:
 *
 *   We initially used an HttpOnly+Secure+SameSite=Strict cookie for the refresh
 *   token. The argument was XSS resistance (a malicious script can't read the
 *   refresh token directly). The argument falls apart for an admin tool though
 *   — if XSS lands on the admin page, it can already act as the admin via the
 *   access token in memory, exfiltrate session data, perform actions, etc.
 *   The "buys time before access expires" theoretical benefit is small enough
 *   that the deployment friction (cookie path matching across install prefixes,
 *   proxy gymnastics in dev, CORS for multi-domain admins) wasn't worth it.
 *
 *   The standard SPA pattern is localStorage; we keep the existing rotation +
 *   theft-detection logic on the server, which catches stolen-token reuse
 *   regardless of where the token lives client-side.
 *
 * Storage hygiene:
 *
 *   - We clear localStorage on logout and on any 401 the fetch wrapper can't recover from.
 *   - The token_version mechanism on the server invalidates everything if the
 *     user changes their password or clicks "log out everywhere", so even a
 *     leaked localStorage token has a hard kill switch.
 */

export interface AuthUser {
	email: string;
	/** Server-driven capability flags (e.g. AI search when Configure → AI enables it). */
	features?: {
		ai_search?: boolean;
		ai_chat?: boolean;
	};
	id: number;
	level: number;
	/**
	 * Developer-only: true when core revision scripts still need to run.
	 * The SPA forces /developer/migrations until this clears.
	 */
	migrations_pending?: boolean;
	name: string;
	timezone?: string;
}

/** Identity of the developer currently emulating another user. */
export interface EmulatedBy {
	email: string;
	id: number;
	name: string;
}

const STORAGE_KEY = "bigtree:auth";
// The developer's own session, parked here while they emulate another user so a
// page reload mid-emulation can still restore it on "stop emulating".
const ORIGIN_STORAGE_KEY = "bigtree:auth:origin";

interface PersistedAuth {
	accessToken: string;
	emulatedBy?: EmulatedBy | null;
	expiresAt: number; // epoch ms
	refreshToken: string;
	user: AuthUser;
}

function loadPersisted(): PersistedAuth | null {
	try {
		const raw = window.localStorage.getItem(STORAGE_KEY);
		if (!raw) return null;
		const parsed = JSON.parse(raw) as PersistedAuth;
		if (!parsed.accessToken || !parsed.refreshToken || !parsed.user) return null;
		return parsed;
	} catch {
		return null;
	}
}

function savePersisted(p: PersistedAuth | null): void {
	try {
		if (p) window.localStorage.setItem(STORAGE_KEY, JSON.stringify(p));
		else window.localStorage.removeItem(STORAGE_KEY);
	} catch {
		// localStorage disabled (private mode) — silent no-op; in-memory state
		// still works for the current tab.
	}
}

function loadOrigin(): PersistedAuth | null {
	try {
		const raw = window.localStorage.getItem(ORIGIN_STORAGE_KEY);
		if (!raw) return null;
		const parsed = JSON.parse(raw) as PersistedAuth;
		if (!parsed.accessToken || !parsed.refreshToken || !parsed.user) return null;
		return parsed;
	} catch {
		return null;
	}
}

function saveOrigin(p: PersistedAuth | null): void {
	try {
		if (p) window.localStorage.setItem(ORIGIN_STORAGE_KEY, JSON.stringify(p));
		else window.localStorage.removeItem(ORIGIN_STORAGE_KEY);
	} catch {
		// see savePersisted
	}
}

interface AuthState {
	accessToken: string | null;
	clear: () => void;
	/**
	 * Set when the API rejects a request with the "developer_mode" code — the
	 * admin is in maintenance and limited to developers. Not persisted; the
	 * Shell renders a lockout screen while this is true.
	 */
	developerLockout: boolean;
	/** Set while a developer is emulating another user; null otherwise. */
	emulatedBy: EmulatedBy | null;
	expiresAt: number | null;
	/**
	 * True until the initial localStorage read + optional refresh probe completes.
	 * Route guards key off this to avoid flashing the login screen.
	 */
	hydrating: boolean;
	refreshToken: string | null;

	setDeveloperLockout: (locked: boolean) => void;
	setHydrated: () => void;
	setSession: (access: string, refresh: string, expiresInSeconds: number, user: AuthUser) => void;
	/** Update the cached user without touching tokens (e.g. after /auth/me). */
	setUser: (user: AuthUser) => void;
	/**
	 * Begin emulating `user`: parks the developer's current session in origin
	 * storage, then installs the emulated session as the active one.
	 */
	startEmulation: (
		access: string,
		refresh: string,
		expiresInSeconds: number,
		user: AuthUser,
		emulatedBy: EmulatedBy
	) => void;
	/** Restore the developer's parked session. No-op if not emulating. */
	stopEmulation: () => void;
	user: AuthUser | null;
}

const initial = loadPersisted();

export const useAuthStore = create<AuthState>((set) => ({
	accessToken: initial?.accessToken ?? null,
	refreshToken: initial?.refreshToken ?? null,
	expiresAt: initial?.expiresAt ?? null,
	user: initial?.user ?? null,
	emulatedBy: initial?.emulatedBy ?? null,
	// Always wait for bootstrapSession (re-fetches /auth/me) before route
	// guards read user flags like migrations_pending — otherwise a stale
	// localStorage flag can flash the migrations gate then disappear.
	hydrating: true,
	developerLockout: false,

	setSession: (access, refresh, expiresInSeconds, user) => {
		const expiresAt = Date.now() + expiresInSeconds * 1000;
		const next: PersistedAuth = {
			accessToken: access,
			refreshToken: refresh,
			expiresAt,
			user,
		};
		savePersisted(next);
		set({ ...next, emulatedBy: null, hydrating: false });
	},

	setUser: (user) => {
		const current = loadPersisted();

		if (!current) {
			set({ user });

			return;
		}

		const next: PersistedAuth = { ...current, user };
		savePersisted(next);
		set({ user });
	},

	startEmulation: (access, refresh, expiresInSeconds, user, emulatedBy) => {
		const current = loadPersisted();

		// Park the developer's own session so we can come back to it. Never
		// overwrite an already-parked origin (no nested emulation).
		if (current && !current.emulatedBy && !loadOrigin()) {
			saveOrigin(current);
		}

		const expiresAt = Date.now() + expiresInSeconds * 1000;
		const next: PersistedAuth = {
			accessToken: access,
			refreshToken: refresh,
			expiresAt,
			user,
			emulatedBy,
		};
		savePersisted(next);
		set({ ...next, hydrating: false });
	},

	stopEmulation: () => {
		const origin = loadOrigin();
		saveOrigin(null);

		if (!origin) {
			return;
		}

		savePersisted(origin);
		set({
			accessToken: origin.accessToken,
			refreshToken: origin.refreshToken,
			expiresAt: origin.expiresAt,
			user: origin.user,
			emulatedBy: null,
			hydrating: false,
		});
	},

	clear: () => {
		savePersisted(null);
		saveOrigin(null);
		set({
			accessToken: null,
			refreshToken: null,
			expiresAt: null,
			user: null,
			emulatedBy: null,
			hydrating: false,
			developerLockout: false,
		});
	},

	setHydrated: () => set({ hydrating: false }),

	setDeveloperLockout: (locked) => set({ developerLockout: locked }),
}));

/** Imperative access for non-React code (the fetch wrapper). */
export const authStore = {
	getState: () => useAuthStore.getState(),
	subscribe: useAuthStore.subscribe,
};
