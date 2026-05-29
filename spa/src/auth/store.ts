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
	id: number;
	email: string;
	name: string;
	level: number;
	timezone?: string;
}

/** Identity of the developer currently emulating another user. */
export interface EmulatedBy {
	id: number;
	name: string;
	email: string;
}

const STORAGE_KEY = "bigtree:auth";
// The developer's own session, parked here while they emulate another user so a
// page reload mid-emulation can still restore it on "stop emulating".
const ORIGIN_STORAGE_KEY = "bigtree:auth:origin";

interface PersistedAuth {
	accessToken: string;
	refreshToken: string;
	expiresAt: number; // epoch ms
	user: AuthUser;
	emulatedBy?: EmulatedBy | null;
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
	refreshToken: string | null;
	expiresAt: number | null;
	user: AuthUser | null;
	/** Set while a developer is emulating another user; null otherwise. */
	emulatedBy: EmulatedBy | null;
	/**
	 * True until the initial localStorage read + optional refresh probe completes.
	 * Route guards key off this to avoid flashing the login screen.
	 */
	hydrating: boolean;

	setSession: (access: string, refresh: string, expiresInSeconds: number, user: AuthUser) => void;
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
	clear: () => void;
	setHydrated: () => void;
}

const initial = loadPersisted();

export const useAuthStore = create<AuthState>((set) => ({
	accessToken: initial?.accessToken ?? null,
	refreshToken: initial?.refreshToken ?? null,
	expiresAt: initial?.expiresAt ?? null,
	user: initial?.user ?? null,
	emulatedBy: initial?.emulatedBy ?? null,
	// If we already have a persisted access token, we're "hydrated enough" to
	// render protected routes immediately. We still kick off a background
	// refresh in the fetch layer if requests start returning 401.
	hydrating: !initial,

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
		});
	},

	setHydrated: () => set({ hydrating: false }),
}));

/** Imperative access for non-React code (the fetch wrapper). */
export const authStore = {
	getState: () => useAuthStore.getState(),
	subscribe: useAuthStore.subscribe,
};
