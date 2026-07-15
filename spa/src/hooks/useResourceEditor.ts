import { useState, type Dispatch, type SetStateAction } from "react";
import { useNavigate, useParams } from "react-router-dom";
import {
	useMutation,
	useQuery,
	useQueryClient,
	type QueryKey,
	type UseQueryResult,
} from "@tanstack/react-query";

import { toast } from "@/lib/toast";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { useReturnTo } from "@/hooks/useReturnTo";
import { useSeededState } from "@/hooks/useSeededState";

export interface UseResourceEditorOptions<TData, TBody> {
	create: (body: TBody) => Promise<{ id: string }>;
	/** Where an add navigates on success (its own edit URL, `replace: true`). */
	editPath: (id: string) => string;
	/** Entity noun for the success toast ("Callout" → "Callout created" / "Callout saved"). */
	entityLabel: string;
	/** Form body in add mode; also the placeholder before the record seeds in edit mode. */
	initialBody: TBody;
	/** Query key invalidated on a successful save. */
	invalidateKey: QueryKey;
	/** List view the id-missing redirect and post-save `useReturnTo` fall back to. */
	listPath: string;
	/** Error path — pass `useFormSubmit`'s `onMutationError` to keep field-error binding. */
	onError: (err: unknown) => void;
	queryFn: () => Promise<TData>;
	/** Detail query for edit mode; skipped (`enabled: false`) in add mode. */
	queryKey: QueryKey;
	/** Map the loaded record into the form body. `idParam` is passed for list-shaped queries. */
	seed: (data: TData, idParam: string) => TBody;
	update: (id: string, body: TBody) => Promise<unknown>;
}

export interface UseResourceEditor<TData, TBody> {
	body: TBody;
	detailQ: UseQueryResult<TData>;
	idParam?: string;
	isAdd: boolean;
	isDirty: boolean;
	/** Fire the create-or-update mutation with the current body. */
	save: (body: TBody) => void;
	saving: boolean;
	/** False until the edit record has seeded the form (drives `useDirtyTracker`). */
	seeded: boolean;
	/** Shallow-merge a patch into the body. */
	set: (patch: Partial<TBody>) => void;
	setBody: Dispatch<SetStateAction<TBody>>;
}

/**
 * The add/edit scaffold every developer editor page (Callout, Template, Feed,
 * ModuleGroup, …) hand-rolled: `isAdd`/`idParam` from the route, a detail query
 * gated on edit mode, a `body` state seeded from the loaded record (with a
 * `seeded` flag), the create-vs-update mutation that invalidates + toasts +
 * navigates (add → its own edit URL, else `returnTo`), and `isDirty` tracking.
 *
 * The page keeps what genuinely differs: its detail→body `seed` mapping, its
 * `create`/`update` calls, and its error path (pass `useFormSubmit`'s
 * `onMutationError` as `onError` to preserve field-error binding — this hook
 * never switches an editor to error toasts).
 */
export const useResourceEditor = <TData, TBody extends object>(
	options: UseResourceEditorOptions<TData, TBody>
): UseResourceEditor<TData, TBody> => {
	const { id: idParam } = useParams<{ id: string }>();
	const isAdd = !idParam;
	const navigate = useNavigate();
	const returnTo = useReturnTo(options.listPath);
	const queryClient = useQueryClient();

	const detailQ = useQuery({
		queryKey: options.queryKey,
		queryFn: options.queryFn,
		enabled: !isAdd,
	});

	const [body, setBody] = useState<TBody>(options.initialBody);

	// Seed once from the detail query; `useSeededState` holds the latest `seed`
	// via a ref so a new options.seed identity each render doesn't re-run.
	const seededFromData = useSeededState(!isAdd ? detailQ.data : undefined, (data) => {
		setBody(options.seed(data, idParam as string));
	});
	const seeded = isAdd || seededFromData;

	const saveMutation = useMutation({
		mutationFn: (next: TBody) =>
			isAdd ? options.create(next) : options.update(idParam as string, next),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: options.invalidateKey });
			toast.success(
				isAdd ? `${options.entityLabel} created` : `${options.entityLabel} saved`
			);

			if (isAdd) {
				navigate(options.editPath((fresh as { id: string }).id), { replace: true });
			} else {
				navigate(returnTo);
			}
		},
		onError: options.onError,
	});

	const isDirty = useDirtyTracker(body, seeded) && !saveMutation.isPending;

	const set = (patch: Partial<TBody>) => setBody((prev) => ({ ...prev, ...patch }));

	return {
		idParam,
		isAdd,
		detailQ,
		body,
		setBody,
		set,
		seeded,
		save: saveMutation.mutate,
		saving: saveMutation.isPending,
		isDirty,
	};
};
