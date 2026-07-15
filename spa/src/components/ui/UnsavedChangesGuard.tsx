import { useRef } from "react";

import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { useUnsavedChangesWarning } from "@/hooks/useUnsavedChangesWarning";

interface UnsavedChangesGuardProps {
	/**
	 * True while the form holds unsaved edits. Callers should drop this back to
	 * false while a save is in flight so the post-save navigation isn't blocked.
	 */
	isDirty: boolean;
}

/**
 * Drop-in guard for any create/update form: warns the user before they navigate
 * away (in-app or via a full-page exit) while `isDirty` is true. Mount one per
 * page — the underlying router blocker only supports a single active instance.
 */
export const UnsavedChangesGuard = ({ isDirty }: UnsavedChangesGuardProps) => {
	const blocker = useUnsavedChangesWarning(isDirty);

	// ConfirmDialog routes a confirm through onConfirm *and* onOpenChange(false).
	// Without this flag the close handler would call reset() right after
	// proceed() — cancelling the navigation and re-arming the blocker, so the
	// dialog immediately reappears. The ref lets the confirm-driven close skip
	// the reset while still resetting on a genuine dismiss (Stay / ESC / overlay).
	const confirmingRef = useRef(false);

	return (
		<ConfirmDialog
			cancelLabel="Stay"
			confirmLabel="Leave page"
			description="You have unsaved changes that will be lost if you leave this page."
			open={blocker.state === "blocked"}
			title="Discard unsaved changes?"
			variant="danger"
			onConfirm={() => {
				confirmingRef.current = true;
				blocker.proceed?.();
			}}
			onOpenChange={(open) => {
				if (open) {
					return;
				}

				if (confirmingRef.current) {
					confirmingRef.current = false;

					return;
				}

				if (blocker.state === "blocked") {
					blocker.reset();
				}
			}}
		/>
	);
};
