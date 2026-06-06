import { useCallback, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";

import { ConfirmDialog } from "@/components/ui/ConfirmDialog";

import { autoModulesApi, type ModuleEntryRow } from "@/api/endpoints/auto-modules";
import { toast } from "@/lib/toast";

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
		mutationFn: (entryId: number) => autoModulesApi.delete(moduleId, entryId, { view: viewId }),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["module-entries", moduleId, viewId] });
			toast.success("Entry deleted");
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

	const dialog = confirmDelete ? (
		<ConfirmDialog
			open={true}
			onOpenChange={(open) => {
				if (!open) {
					setConfirmDelete(null);
				}
			}}
			title="Delete entry?"
			description="This action cannot be undone."
			confirmLabel="Delete"
			variant="danger"
			onConfirm={() => {
				const entryId = Number(confirmDelete.id);

				if (Number.isFinite(entryId) && entryId > 0) {
					deleteMutation.mutate(entryId);
				}
			}}
		/>
	) : null;

	return { requestDelete, dialog };
};
