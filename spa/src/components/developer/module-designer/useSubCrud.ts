import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

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
	const queryClient = useQueryClient();
	const queryKey = ["modules", moduleId, resource];
	const [editingId, setEditingId] = useState<string | null>(null);

	const listQ = useQuery({
		queryKey,
		queryFn: () => listFn(moduleId),
	});

	const invalidate = () => queryClient.invalidateQueries({ queryKey });

	const saveMutation = useMutation({
		mutationFn: ({ sid, body }: { sid: string | null; body: Body }) =>
			sid && sid !== NEW_ROW
				? updateFn(moduleId, sid, body as Partial<Body>)
				: createFn(moduleId, body),
		onSuccess: () => {
			invalidate();
			setEditingId(null);
			toast.success(`${label} saved`);
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Save failed");
		},
	});

	const deleteMutation = useMutation({
		mutationFn: (sid: string) => deleteFn(moduleId, sid),
		onSuccess: () => {
			invalidate();
			toast.success(`${label} deleted`);
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Delete failed");
		},
	});

	return {
		items: listQ.data ?? [],
		isLoading: listQ.isLoading,
		editingId,
		startAdd: () => setEditingId(NEW_ROW),
		startEdit: (sid: string) => setEditingId(sid),
		cancel: () => setEditingId(null),
		save: (sid: string | null, body: Body) => saveMutation.mutate({ sid, body }),
		saving: saveMutation.isPending,
		remove: (sid: string) => deleteMutation.mutate(sid),
	};
};
