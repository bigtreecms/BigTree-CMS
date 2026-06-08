import { useState } from "react";
import { Lock } from "lucide-react";

import type { LockOwner } from "@/types/api-resources";
import { relativeTime } from "@/lib/time";

import { ConfirmDialog } from "./ConfirmDialog";

interface LockBannerProps {
	/** Who currently holds the lock (null if the server couldn't attribute it). */
	owner: LockOwner | null;
	/** Last-accessed timestamp of the conflicting lock, if known. */
	lockedAt?: string | null;
	/** Forcibly take over the lock — mirrors the legacy admin's "Unlock" button. */
	onUnlock: () => void;
}

/**
 * Read-only banner shown on an edit screen while another user holds the row's
 * edit lock. Offers a confirmed "Unlock" takeover, mirroring the legacy admin's
 * `_locked.php` interstitial (which evicts the holder via `?force=true`).
 */
export const LockBanner = ({ owner, lockedAt, onUnlock }: LockBannerProps) => {
	const [confirming, setConfirming] = useState(false);
	const name = owner?.name ?? "another user";
	const accessed = lockedAt ? relativeTime(lockedAt) : "";

	return (
		<div className="mb-3 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-md border border-border bg-surface-2 px-3 py-2 text-[12.5px] text-text-2">
			<Lock size={14} className="text-text-3" />
			<span className="flex-1">
				Locked by <strong className="font-medium text-text">{name}</strong> — editing is
				disabled.
				{accessed && <span className="text-text-3"> Last accessed {accessed}.</span>}
			</span>
			<button
				type="button"
				className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1 text-[12px] font-medium text-text-2 hover:bg-hover"
				onClick={() => setConfirming(true)}
			>
				Unlock
			</button>

			<ConfirmDialog
				open={confirming}
				onOpenChange={setConfirming}
				title="Unlock this entry?"
				description={`${name} currently has this entry locked for editing. Unlocking takes over the lock — any unsaved changes they have will be lost.`}
				confirmLabel="Unlock"
				variant="danger"
				onConfirm={onUnlock}
			/>
		</div>
	);
};
