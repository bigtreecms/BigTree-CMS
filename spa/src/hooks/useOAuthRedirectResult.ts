import { useEffect, useRef } from "react";
import { useSearchParams } from "react-router-dom";
import { useQueryClient, type QueryKey } from "@tanstack/react-query";

export interface UseOAuthRedirectResultOptions {
	/** Refetched once the redirect has been consumed, so the page shows the new connection. */
	invalidate: QueryKey;
	/** Called with the `connected` param (the provider the broker connected). */
	onConnected: (provider: string) => void;
	/** Called with the `error` param (the broker's failure code, e.g. `oauth_failed`). */
	onError: (code: string) => void;
}

/**
 * Consumes the OAuth broker's redirect result: the broker bounces back to the
 * configure page with a `connected` or `error` search param, which this hook
 * reports through the caller's callbacks (the copy is page-specific), strips
 * from the URL, and follows with an invalidation of the page's query.
 *
 * The callbacks and key are read through a ref so a caller can pass inline
 * closures without re-running the effect on every render — it must fire once
 * per redirect, or the toast repeats.
 */
export const useOAuthRedirectResult = (options: UseOAuthRedirectResultOptions) => {
	const queryClient = useQueryClient();
	const [searchParams, setSearchParams] = useSearchParams();

	const optionsRef = useRef(options);
	optionsRef.current = options;

	useEffect(() => {
		const connected = searchParams.get("connected");
		const error = searchParams.get("error");

		if (connected) {
			optionsRef.current.onConnected(connected);
		} else if (error) {
			optionsRef.current.onError(error);
		}

		if (connected || error) {
			searchParams.delete("connected");
			searchParams.delete("error");
			setSearchParams(searchParams, { replace: true });
			queryClient.invalidateQueries({ queryKey: optionsRef.current.invalidate });
		}
	}, [searchParams, setSearchParams, queryClient]);
};
