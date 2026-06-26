import { useState } from "react";

import { ApiError } from "@/types/api";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";

export interface UseFormSubmitReturn {
	error: string | null;
	setError: (e: string | null) => void;
	fieldErrors: Record<string, string>;
	setFieldErrors: (fe: Record<string, string>) => void;
	handleSubmit: (
		e: React.FormEvent,
		validate: () => Record<string, string>,
		submit: () => void
	) => void;
	onMutationError: (err: unknown, fallback?: string) => void;
}

/**
 * Shared form state for create/edit forms: error + field errors + scroll-to-first-error.
 * Call handleSubmit from your FormShell's onSubmit — it runs validation, sets errors, then
 * calls submit() if clean. Pass onMutationError to useMutation's onError to extract field
 * errors from ApiError responses.
 */
export const useFormSubmit = (): UseFormSubmitReturn => {
	const [error, setError] = useState<string | null>(null);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

	useScrollToFirstError(fieldErrors);

	const handleSubmit = (
		e: React.FormEvent,
		validate: () => Record<string, string>,
		submit: () => void
	) => {
		e.preventDefault();

		const errors = validate();

		if (Object.keys(errors).length > 0) {
			setFieldErrors(errors);
			setError("Please fill in the required fields.");

			return;
		}

		setFieldErrors({});
		setError(null);
		submit();
	};

	const onMutationError = (err: unknown, fallback = "An error occurred.") => {
		if (err instanceof ApiError) {
			const fe = err.fieldErrors();

			if (Object.keys(fe).length > 0) {
				setFieldErrors(fe);
			}

			setError(err.message || fallback);
		} else {
			setError(err instanceof Error ? err.message : fallback);
		}
	};

	return { error, setError, fieldErrors, setFieldErrors, handleSubmit, onMutationError };
};
