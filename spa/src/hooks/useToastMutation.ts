import {
	useMutation,
	useQueryClient,
	type QueryKey,
	type UseMutationOptions,
	type UseMutationResult,
} from "@tanstack/react-query";

import { toast } from "@/lib/toast";
import { ApiError } from "@/types/api";

interface ToastMutationOptions<TData, TError, TVariables, TContext>
	extends Omit<UseMutationOptions<TData, TError, TVariables, TContext>, "onSuccess" | "onError"> {
	/** Query keys to invalidate on success. */
	invalidate?: QueryKey[];
	/** Toast shown on success. Omit to suppress. */
	successMessage?: string;
	/** Fallback toast shown on error when the API returns no message. */
	errorMessage?: string;
	/** Extra work to run after invalidation + toast (e.g. navigate, setState). */
	onSuccess?: (data: TData, variables: TVariables, context: TContext) => void | Promise<void>;
	/** Extra work to run after the error toast. */
	onError?: (error: TError, variables: TVariables, context: TContext | undefined) => void;
}

/**
 * Wraps `useMutation` with the project-standard invalidate + toast plumbing so
 * each call-site only provides `mutationFn`, the keys to invalidate, and the
 * success/error messages.  Pass `onSuccess`/`onError` for side-effects that go
 * beyond the built-in invalidation (navigate, local setState, etc.).
 */
export const useToastMutation = <
	TData = unknown,
	TError = unknown,
	TVariables = void,
	TContext = unknown,
>(
	options: ToastMutationOptions<TData, TError, TVariables, TContext>
): UseMutationResult<TData, TError, TVariables, TContext> => {
	const { invalidate, successMessage, errorMessage, onSuccess, onError, ...rest } = options;

	const queryClient = useQueryClient();

	return useMutation({
		...rest,
		onSuccess: async (data, variables, context) => {
			if (invalidate) {
				await Promise.all(
					invalidate.map((key) => queryClient.invalidateQueries({ queryKey: key }))
				);
			}

			if (successMessage) {
				toast.success(successMessage);
			}

			await onSuccess?.(data, variables, context);
		},
		onError: (error, variables, context) => {
			const message =
				error instanceof ApiError && error.message
					? error.message
					: (errorMessage ?? "Something went wrong");

			toast.error(message);

			onError?.(error, variables, context);
		},
	});
};
