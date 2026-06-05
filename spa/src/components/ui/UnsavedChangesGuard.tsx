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

	return (
		<ConfirmDialog
			open={blocker.state === "blocked"}
			onOpenChange={(open) => {
				if (!open && blocker.state === "blocked") {
					blocker.reset();
				}
			}}
			title="Discard unsaved changes?"
			description="You have unsaved changes that will be lost if you leave this page."
			confirmLabel="Leave page"
			cancelLabel="Stay"
			variant="danger"
			onConfirm={() => blocker.proceed?.()}
		/>
	);
};
