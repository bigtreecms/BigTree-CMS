import { ApiError } from "@/types/api";
import { QueryClient } from "@tanstack/react-query";

/**
 * Project-wide TanStack Query configuration.
 *
 * - Don't retry on 4xx (client errors won't get better with another attempt;
 *   retrying a 403 is pointless and noisy). Do retry on 5xx and network errors.
 * - Default staleTime = 30s so navigation back to a screen doesn't refetch
 *   immediately. Per-query overrides apply where freshness matters more.
 * - Throw errors so error boundaries / hook returns can catch them; the fetch
 *   wrapper has already converted them to typed ApiError instances.
 */
export const queryClient = new QueryClient({
	defaultOptions: {
		queries: {
			retry: (failureCount, error) => {
				if (error instanceof ApiError && error.status >= 400 && error.status < 500) {
					return false;
				}
				return failureCount < 2;
			},
			staleTime: 30_000,
			refetchOnWindowFocus: false,
			throwOnError: false,
		},
		mutations: {
			retry: false,
		},
	},
});
