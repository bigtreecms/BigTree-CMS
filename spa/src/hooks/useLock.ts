import { useEffect, useRef, useState } from "react";

import { locksApi } from "@/api/endpoints/locks";
import { ApiError } from "@/types/api";
import type { LockConflictDetails } from "@/api/endpoints/locks";
import type { LockOwner } from "@/types/api-resources";

interface UseLockOptions {
	/** Logical record being locked, e.g. "bigtree_pages" or "module:14". */
	table: string;
	/** Numeric or string id of the record being locked. */
	itemId: string | number;
	/** Title surfaced to the user holding a conflicting lock. */
	title?: string;
	/** If false the hook stays dormant — useful while data is still loading. */
	enabled?: boolean;
	/** Refresh interval in ms (default 2 minutes; server stale threshold is 5). */
	refreshIntervalMs?: number;
}

export interface UseLockResult {
	/** True once we hold the lock. */
	acquired: boolean;
	/** True when the server rejected acquire because someone else holds it. */
	ownedByOther: boolean;
	/** When ownedByOther: who has it (and when they last touched it). */
	lockOwner: LockOwner | null;
	/** Last-accessed timestamp of the conflicting lock, if any. */
	lockedAt: string | null;
	/** Generic acquire error message (network, server, etc.). */
	error: string | null;
	/** Manually release. Called automatically on unmount. */
	release: () => Promise<void>;
	/** Try to take the lock again — useful after a "force unlock" interaction. */
	retry: () => void;
}

/**
 * Acquire a `bigtree_locks` row for the lifetime of the component, periodically
 * refreshing it (defaults to every 2 minutes — server treats a lock as stale
 * after 5). The lock is released on unmount, or imperatively via `release()`.
 *
 * If another user already holds the lock, `ownedByOther` flips true and
 * `lockOwner` is populated so the screen can show a read-only state with
 * an attribution banner.
 */
export const useLock = ({
	table,
	itemId,
	title,
	enabled = true,
	refreshIntervalMs = 120_000,
}: UseLockOptions): UseLockResult => {
	const [acquired, setAcquired] = useState(false);
	const [ownedByOther, setOwnedByOther] = useState(false);
	const [lockOwner, setLockOwner] = useState<LockOwner | null>(null);
	const [lockedAt, setLockedAt] = useState<string | null>(null);
	const [error, setError] = useState<string | null>(null);
	const [retryToken, setRetryToken] = useState(0);

	const lockIdRef = useRef<number | null>(null);
	const cancelledRef = useRef(false);

	useEffect(() => {
		if (!enabled) {
			return;
		}

		cancelledRef.current = false;
		setAcquired(false);
		setOwnedByOther(false);
		setLockOwner(null);
		setLockedAt(null);
		setError(null);

		let refreshHandle: number | undefined;

		const acquire = async () => {
			try {
				const handle = await locksApi.acquire({ table, item_id: itemId, title });

				if (cancelledRef.current) {
					void locksApi.release(handle.lock_id).catch(() => {});

					return;
				}

				lockIdRef.current = handle.lock_id;
				setAcquired(true);

				refreshHandle = window.setInterval(() => {
					if (lockIdRef.current === null) {
						return;
					}

					locksApi.refresh(lockIdRef.current).catch(() => {
						// Refresh failure usually means someone forcibly took the lock.
						// Treat it as a soft conflict — the next interaction will surface.
					});
				}, refreshIntervalMs);
			} catch (err) {
				if (cancelledRef.current) {
					return;
				}

				if (err instanceof ApiError && err.status === 409) {
					const details = pickConflictDetails(err);
					setOwnedByOther(true);
					setLockOwner(details?.locked_by ?? null);
					setLockedAt(details?.last_accessed ?? null);

					return;
				}

				setError(err instanceof Error ? err.message : "Failed to acquire lock");
			}
		};

		void acquire();

		return () => {
			cancelledRef.current = true;

			if (refreshHandle !== undefined) {
				window.clearInterval(refreshHandle);
			}

			const heldId = lockIdRef.current;
			lockIdRef.current = null;

			if (heldId !== null) {
				void locksApi.release(heldId).catch(() => {});
			}
		};
	}, [table, itemId, title, enabled, refreshIntervalMs, retryToken]);

	const release = async () => {
		const heldId = lockIdRef.current;
		lockIdRef.current = null;
		setAcquired(false);

		if (heldId === null) {
			return;
		}

		await locksApi.release(heldId).catch(() => {});
	};

	const retry = () => {
		setRetryToken((n) => n + 1);
	};

	return {
		acquired,
		ownedByOther,
		lockOwner,
		lockedAt,
		error,
		release,
		retry,
	};
};

/**
 * The 409 payload puts {locked_by, last_accessed} in the first error's
 * `details` slot. We don't bother typing the whole ApiError shape; this
 * narrow read keeps the assumption local.
 */
const pickConflictDetails = (err: ApiError): LockConflictDetails | null => {
	const first = err.errors[0] as { details?: LockConflictDetails } | undefined;

	return first?.details ?? null;
};
