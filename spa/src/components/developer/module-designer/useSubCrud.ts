import { useState } from "react";
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

interface UseSubCrudArgs<T, Body> {
	moduleId: string;
	resource: string;
	label: string;
	listFn: (moduleId: string) => Promise<T[]>;
	createFn: (moduleId: string, body: Body) => Promise<T>;
	updateFn: (moduleId: string, sid: string, body: Partial<Body>) => Promise<T>;
	deleteFn: (moduleId: string, sid: string) => Promise<void>;
}

export const useSubCrud = <T, Body>({
	moduleId,
	resource,
	label,
	listFn,
	createFn,
	updateFn,
	deleteFn,
}: UseSubCrudArgs<T, Body>) => {
	const queryKey = ["modules", moduleId, resource];
	const [editingId, setEditingId] = useState<string | null>(null);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

	useScrollToFirstError(fieldErrors);

	const listQ = useQuery({
		queryKey,
		queryFn: () => listFn(moduleId),
	});

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
