import { useCallback, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";

import { ConfirmDialog } from "@/components/ui/ConfirmDialog";

import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import { toast } from "@/lib/toast";

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
	const queryClient = useQueryClient();
	const [confirmDelete, setConfirmDelete] = useState<ModuleEntryRow | null>(null);

	const deleteMutation = useMutation({
		mutationFn: (entryId: number | string) =>
			autoModulesApi.delete(moduleId, entryId, { view: viewId }),
		onSuccess: (_data, entryId) => {
			queryClient.invalidateQueries({ queryKey: ["module-entries", moduleId, viewId] });
			toast.success(
				typeof entryId === "string" && entryId.startsWith("p")
					? "Pending entry deleted"
					: "Entry deleted"
			);
		},
		onError: () => {
			toast.error("Could not delete entry");
		},
		onSettled: () => {
			setConfirmDelete(null);
		},
	});

	const requestDelete = useCallback((row: ModuleEntryRow) => {
		setConfirmDelete(row);
	}, []);

	const isPending = confirmDelete ? statusFromRow(confirmDelete).key === "pending" : false;

	const dialog = confirmDelete ? (
		<ConfirmDialog
			open={true}
			onOpenChange={(open) => {
				if (!open) {
					setConfirmDelete(null);
				}
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
				if (isPersistedEntryId(confirmDelete.id)) {
					deleteMutation.mutate(confirmDelete.id);
				}
			}}
		/>
	) : null;

	return { requestDelete, dialog };
};
