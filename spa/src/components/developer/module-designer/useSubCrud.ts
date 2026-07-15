import { useEffect, useState, type Dispatch, type SetStateAction } from "react";
import { useQuery } from "@tanstack/react-query";

import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { useToastMutation } from "@/hooks/useToastMutation";
import { validateRequired, type RequiredRule } from "@/lib/formValidation";

/**
 * Shared list + create/update/delete plumbing for the module designer's
 * sub-resource tabs. Each tab supplies the API callbacks and its own draft
 * editor UI; this hook owns the query, the mutations, and the "which row is
 * open" selection state.
 *
 * `editingId` is null when nothing is open, "new" when adding, or the
 * sub-resource id when editing an existing row.
 */

export const NEW_ROW = "new" as const;

interface UseSubCrudArgs<T, Body, Draft> {
	createFn: (moduleId: string, body: Body) => Promise<T>;
	deleteFn: (moduleId: string, sid: string) => Promise<void>;
	draftFromItem?: (item: T) => Draft;
	emptyDraft?: (table: string) => Draft;
	label: string;
	listFn: (moduleId: string) => Promise<T[]>;
	moduleId: string;
	/**
	 * Optional draft management. When `emptyDraft` and `draftFromItem` are both
	 * supplied the hook owns the editor draft state and keeps it in sync with the
	 * selection: reset to `emptyDraft(moduleTable)` when a new row opens, seeded
	 * from `draftFromItem(item)` when an existing row opens.
	 */
	moduleTable?: string;
	/**
	 * Fired alongside each draft sync — receives the item being edited, or `null`
	 * when a new row opens. Lets a tab track selection-derived state (e.g.
	 * ModuleReportsTab's `builtForTable` ref) without duplicating the sync effect.
	 * Must be referentially stable (wrap in `useCallback`).
	 */
	onSync?: (item: T | null) => void;
	resource: string;
	updateFn: (moduleId: string, sid: string, body: Partial<Body>) => Promise<T>;
}

export const useSubCrud = <T extends { id: string }, Body, Draft = unknown>({
	moduleId,
	resource,
	label,
	listFn,
	createFn,
	updateFn,
	deleteFn,
	moduleTable = "",
	emptyDraft,
	draftFromItem,
	onSync,
}: UseSubCrudArgs<T, Body, Draft>) => {
	const queryKey = ["modules", moduleId, resource];
	const [editingId, setEditingId] = useState<string | null>(null);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	const [draft, setDraft] = useState<Draft>(() =>
		emptyDraft ? emptyDraft(moduleTable) : (undefined as Draft)
	);

	useScrollToFirstError(fieldErrors);

	const listQ = useQuery({
		queryKey,
		queryFn: () => listFn(moduleId),
	});

	// Keep the draft in sync with the current selection. No-op unless the caller
	// opted into draft management by supplying the mapper functions.
	useEffect(() => {
		if (!emptyDraft || !draftFromItem) {
			return;
		}

		if (editingId === NEW_ROW) {
			setDraft(emptyDraft(moduleTable));
			onSync?.(null);
		} else if (editingId) {
			const found = (listQ.data ?? []).find((it) => it.id === editingId);

			if (found) {
				setDraft(draftFromItem(found));
				onSync?.(found);
			}
		}
	}, [editingId, listQ.data, moduleTable, emptyDraft, draftFromItem, onSync]);

	const saveMutation = useToastMutation({
		mutationFn: ({ sid, body }: { sid: string | null; body: Body }) =>
			sid && sid !== NEW_ROW
				? updateFn(moduleId, sid, body as Partial<Body>)
				: createFn(moduleId, body),
		invalidate: [queryKey],
		successMessage: `${label} saved`,
		errorMessage: "Save failed",
		onSuccess: () => {
			setEditingId(null);
		},
	});

	const deleteMutation = useToastMutation({
		mutationFn: (sid: string) => deleteFn(moduleId, sid),
		invalidate: [queryKey],
		successMessage: `${label} deleted`,
		errorMessage: "Delete failed",
	});

	const open = (id: string) => {
		setFieldErrors({});
		setEditingId(id);
	};

	return {
		items: listQ.data ?? [],
		isLoading: listQ.isLoading,
		editingId,
		fieldErrors,
		draft,
		setDraft: setDraft as Dispatch<SetStateAction<Draft>>,
		startAdd: () => open(NEW_ROW),
		startEdit: (sid: string) => open(sid),
		cancel: () => {
			setFieldErrors({});
			setEditingId(null);
		},
		/**
		 * Persist the draft. When `rules` are supplied, required fields are
		 * validated client-side first: any empty field populates `fieldErrors`
		 * (rendered inline by the editor's inputs) and the mutation is blocked,
		 * matching the static edit forms rather than relying on a server 422.
		 *
		 * `extraInvalid` lets the caller veto the save for a validation the hook
		 * doesn't own (e.g. nested field-settings errors surfaced separately):
		 * the `fieldErrors` for `rules` are still computed/cleared so both error
		 * sources show together, but the mutation is held back.
		 */
		save: (sid: string | null, body: Body, rules?: RequiredRule[], extraInvalid?: boolean) => {
			let blocked = Boolean(extraInvalid);

			if (rules && rules.length > 0) {
				const errors = validateRequired(rules);
				setFieldErrors(errors);

				if (Object.keys(errors).length > 0) {
					blocked = true;
				}
			} else {
				setFieldErrors({});
			}

			if (blocked) {
				return;
			}

			saveMutation.mutate({ sid, body });
		},
		saving: saveMutation.isPending,
		remove: (sid: string) => deleteMutation.mutate(sid),
	};
};
