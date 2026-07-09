import { useCallback } from "react";

import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";

import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import { toast } from "@/lib/toast";
import { useToastMutation } from "@/hooks/useToastMutation";

import { isPersistedEntryId, statusFromRow } from "./viewHelpers";

/**
 * Shared delete flow for the module-view runtimes. Owns the confirm dialog, the
 * delete mutation, and view-cache invalidation so each view type doesn't have to
 * re-implement them. A view calls `requestDelete(row)` from its Trash button and
 * drops `dialog` somewhere in its tree.
 *
 * Originally lived inline in `SearchableView` / `ImagesGrid`; factored out here
 * so `DraggableView`, `GroupedView`, and `NestedView` can wire their (previously
 * dead) Trash buttons to the same flow.
 */
export const useEntryDelete = (moduleId: string, viewId: string) => {
	const deleteDialog = useConfirmDialog<ModuleEntryRow>();

	const deleteMutation = useToastMutation({
		mutationFn: (entryId: number | string) =>
			autoModulesApi.delete(moduleId, entryId, { view: viewId }),
		invalidate: [["module-entries", moduleId, viewId]],
		errorMessage: "Could not delete entry",
		onSuccess: (_data, entryId) => {
			toast.success(
				typeof entryId === "string" && entryId.startsWith("p")
					? "Pending entry deleted"
					: "Entry deleted"
			);
		},
		onSettled: () => {
			deleteDialog.close();
		},
	});

	const requestDelete = useCallback(
		(row: ModuleEntryRow) => {
			deleteDialog.open(row);
		},
		[deleteDialog]
	);

	const isPending = deleteDialog.item
		? statusFromRow(deleteDialog.item).key === "pending"
		: false;

	const dialog = deleteDialog.item ? (
		<ConfirmDialog
			open={deleteDialog.isOpen}
			onOpenChange={(v) => {
				if (!v) deleteDialog.close();
			}}
			title={isPending ? "Delete pending entry?" : "Delete entry?"}
			description={
				isPending
					? "This deletes the pending entry — it has never been published, so nothing live is affected."
					: "This action cannot be undone."
			}
			confirmLabel="Delete"
			variant="danger"
			onConfirm={() => {
				if (isPersistedEntryId(deleteDialog.item!.id)) {
					deleteMutation.mutate(deleteDialog.item!.id);
				}
			}}
		/>
	) : null;

	return { requestDelete, dialog };
};
