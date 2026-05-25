/**
 * Theme management. The prototype drives light/dark + density + accent hue
 * via three data-* attributes on <html>:
 *   data-theme="light"|"dark"
 *   data-density="compact"|"regular"|"comfy"
 *   data-accent-h="<number>"   (mutates the --accent-h CSS variable)
 *
 * We persist preferences to localStorage but never to a cookie — they're
 * device-local UX preferences, not auth state. Initial value falls back to the
 * OS preference (matchMedia("prefers-color-scheme: dark")).
 */

export type Theme = "light" | "dark";
export type Density = "compact" | "regular" | "comfy";

const STORAGE = {
	theme: "bigtree:theme",
	density: "bigtree:density",
	accent: "bigtree:accent-h",
};

function safeGet(key: string): string | null {
	try {
		return window.localStorage.getItem(key);
	} catch {
		return null;
	}
}

function safeSet(key: string, value: string): void {
	try {
		window.localStorage.setItem(key, value);
	} catch {
		// localStorage disabled (private mode, etc.) — silently no-op
	}
}

export function resolveInitialTheme(): Theme {
	const stored = safeGet(STORAGE.theme);
	if (stored === "light" || stored === "dark") return stored;
	if (typeof window !== "undefined" && window.matchMedia?.("(prefers-color-scheme: dark)").matches) {
		return "dark";
	}
	return "light";
}

export function applyTheme(theme: Theme): void {
	document.documentElement.dataset.theme = theme;
	safeSet(STORAGE.theme, theme);
}

export function resolveInitialDensity(): Density {
	const stored = safeGet(STORAGE.density);
	if (stored === "compact" || stored === "comfy") return stored;
	return "regular";
}

export function applyDensity(density: Density): void {
	if (density === "regular") {
		// "regular" is the default; clear the attribute so the base @theme tokens apply
		delete document.documentElement.dataset.density;
	} else {
		document.documentElement.dataset.density = density;
	}
	safeSet(STORAGE.density, density);
}

export function resolveInitialAccent(): number {
	const stored = safeGet(STORAGE.accent);
	const n = stored ? Number(stored) : NaN;
	return Number.isFinite(n) && n >= 0 && n < 360 ? n : 155;
}

export function applyAccent(hue: number): void {
	const clamped = Math.max(0, Math.min(359, hue));
	document.documentElement.style.setProperty("--accent-h", String(clamped));
	safeSet(STORAGE.accent, String(clamped));
}

/** Apply all three on boot, before React mounts, to avoid theme flash. */
export function bootstrapTheme(): void {
	applyTheme(resolveInitialTheme());
	applyDensity(resolveInitialDensity());
	applyAccent(resolveInitialAccent());
}
