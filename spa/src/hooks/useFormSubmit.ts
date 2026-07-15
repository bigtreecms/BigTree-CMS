import { useState } from "react";

import { applyApiFieldErrors } from "@/lib/errorHandling";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";

export interface UseFormSubmitOptions {
	/** Message set when client-side validation fails. Default: required-fields copy. */
	requiredMessage?: string;
}

export interface UseFormSubmitReturn {
	error: string | null;
	fieldErrors: Record<string, string>;
	handleSubmit: (
		e: React.FormEvent,
		validate: () => Record<string, string>,
		submit: () => void
	) => void;
	onMutationError: (err: unknown, fallback?: string) => void;
	setError: (e: string | null) => void;
	setFieldErrors: (fe: Record<string, string>) => void;
}

/**
 * Shared form state for create/edit forms: error + field errors + scroll-to-first-error.
 * Call handleSubmit from your FormShell's onSubmit — it runs validation, sets errors, then
 * calls submit() if clean. Pass onMutationError to useMutation's onError to extract field
 * errors from ApiError responses.
 */
export const useFormSubmit = (options: UseFormSubmitOptions = {}): UseFormSubmitReturn => {
	const requiredMessage = options.requiredMessage ?? "Please fill in the required fields.";
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
			setError(requiredMessage);

			return;
		}

		setFieldErrors({});
		setError(null);
		submit();
	};

	const onMutationError = (err: unknown, fallback = "An error occurred.") => {
		applyApiFieldErrors(err, { setFieldErrors, setError, fallback });
	};

	return { error, setError, fieldErrors, setFieldErrors, handleSubmit, onMutationError };
};
