import { useEffect, useRef, useState } from "react";
import { useQuery, useQueryClient, type QueryKey } from "@tanstack/react-query";

import { useToastMutation } from "@/hooks/useToastMutation";
import { describeApiError } from "@/lib/errorHandling";

export interface UseConfigDraftOptions<TData, TDraft> {
	/** Cache key for the config query (also the `setQueryData` write-back target). */
	queryKey: QueryKey;
	queryFn: () => Promise<TData>;
	/** Map the loaded config into the editable draft (deep-clone what you edit). */
	seed: (data: TData) => TDraft;
	/** Persist the draft; the resolved config is written straight back to the cache. */
	save: (draft: TDraft) => Promise<TData>;
	successMessage: string;
	errorMessage: string;
}

/**
 * Shared scaffold for the `pages/developer/configure/*` editors: load the config,
 * seed a local draft from it, and save the draft back with a toast + a
 * server-supplied `generalError` string.
 *
 * The draft is `null` until the query resolves, and re-seeds automatically
 * whenever the cached config changes — including the `setQueryData` write-back
 * after a successful save, so the draft always tracks the persisted state.
 * Pages with secondary mutations (e.g. a certificate/key upload) reuse
 * `writeCache` to feed a fresh config through that same re-seed.
 */
export const useConfigDraft = <TData, TDraft>({
	queryKey,
	queryFn,
	seed,
	save,
	successMessage,
	errorMessage,
}: UseConfigDraftOptions<TData, TDraft>) => {
	const queryClient = useQueryClient();
	const detailQ = useQuery({ queryKey, queryFn });

	const [draft, setDraft] = useState<TDraft | null>(null);
	const [generalError, setGeneralError] = useState<string | null>(null);

	// Read the latest `seed` without re-running the effect on every render.
	const seedRef = useRef(seed);
	seedRef.current = seed;

	useEffect(() => {
		if (detailQ.data) {
			setDraft(seedRef.current(detailQ.data));
		}
	}, [detailQ.data]);

	const writeCache = (fresh: TData) => {
		queryClient.setQueryData(queryKey, fresh);
	};

	const saveMutation = useToastMutation({
		mutationFn: save,
		successMessage,
		errorMessage,
		onSuccess: (fresh) => {
			writeCache(fresh);
			setGeneralError(null);
		},
		onError: (err) => {
			setGeneralError(describeApiError(err, errorMessage));
		},
	});

	return {
		detailQ,
		draft,
		setDraft,
		generalError,
		setGeneralError,
		saveMutation,
		writeCache,
	};
};
