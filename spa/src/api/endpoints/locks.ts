import { api } from "@/api/client";
import type { LockOwner } from "@/types/api-resources";

/**
 * Concurrent-edit lock endpoints. Mirrors POST /locks, POST /locks/{id}/refresh,
 * and DELETE /locks/{id}. The server treats locks older than 5 minutes as stale,
 * so we refresh every ~2 minutes from the useLock hook.
 */

export interface LockHandle {
	expires_at: string;
	lock_id: number;
}

export interface LockConflictDetails {
	last_accessed: string;
	locked_by: LockOwner | null;
}

export const locksApi = {
	acquire: (payload: {
		table: string;
		item_id: string | number;
		title?: string;
		/** Forcibly take over a lock held by another user (the "Unlock" action). */
		force?: boolean;
	}) =>
		api.post<LockHandle>("/locks", {
			table: payload.table,
			item_id: String(payload.item_id),
			title: payload.title,
			force: payload.force,
		}),

	refresh: (lockId: number) => api.post<LockHandle>(`/locks/${lockId}/refresh`),

	release: (lockId: number) => api.delete<void>(`/locks/${lockId}`),
};
