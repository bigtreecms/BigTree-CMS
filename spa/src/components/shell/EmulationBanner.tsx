import { Eye } from "lucide-react";

import { authApi } from "@/auth/endpoints";
import { useAuthStore } from "@/auth/store";

const stop = () => {
	authApi.stopEmulating();
	window.location.reload();
};

/**
 * Persistent banner shown while a developer is emulating another user. Stopping
 * restores the developer's parked session and hard-reloads so every React Query
 * cache is rebuilt under the restored identity (rather than leaking the
 * emulated user's data across the swap).
 */
export const EmulationBanner = () => {
	const emulatedBy = useAuthStore((s) => s.emulatedBy);
	const user = useAuthStore((s) => s.user);

	if (!emulatedBy) {
		return null;
	}

	return (
		<div className="flex items-center justify-center gap-3 border-b border-warn/30 bg-warn-bg px-4 py-1.5 text-[12.5px] font-medium text-warn">
			<Eye className="shrink-0" size={13} />
			<span>
				Emulating <strong>{user?.name || user?.email}</strong> — actions are performed as
				this user.
			</span>
			<button
				className="rounded-md border border-warn/40 bg-warn/10 px-2 py-0.5 font-semibold text-warn hover:bg-warn/20"
				type="button"
				onClick={stop}
			>
				Stop emulating
			</button>
		</div>
	);
};
