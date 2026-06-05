import { useCallback, useState } from "react";

/**
 * Per-browser "ignore updates" flags for extensions, mirroring the legacy admin
 * which stored these in a cookie (`bigtree_admin[ignored_extension_updates]`).
 * Kept client-side on purpose — it's a personal dismissal, not server state.
 */

const STORAGE_KEY = "bigtree.ignoredExtensionUpdates";

const read = (): Set<string> => {
	try {
		const raw = localStorage.getItem(STORAGE_KEY);

		if (!raw) {
			return new Set();
		}

		const parsed = JSON.parse(raw);

		return Array.isArray(parsed) ? new Set(parsed.map(String)) : new Set();
	} catch {
		return new Set();
	}
};

const write = (ids: Set<string>) => {
	try {
		localStorage.setItem(STORAGE_KEY, JSON.stringify([...ids]));
	} catch {
		// Storage unavailable (private mode / quota) — ignore is best-effort.
	}
};

export const useIgnoredExtensionUpdates = () => {
	const [ignored, setIgnored] = useState<Set<string>>(read);

	const isIgnored = useCallback((id: string) => ignored.has(id), [ignored]);

	const ignore = useCallback((id: string) => {
		setIgnored((prev) => {
			const next = new Set(prev);
			next.add(id);
			write(next);

			return next;
		});
	}, []);

	const unignore = useCallback((id: string) => {
		setIgnored((prev) => {
			const next = new Set(prev);
			next.delete(id);
			write(next);

			return next;
		});
	}, []);

	return { isIgnored, ignore, unignore };
};
