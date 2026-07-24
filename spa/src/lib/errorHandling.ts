import { ApiError } from "@/types/api";

/**
 * Extract a human-readable message from any thrown value, falling back to
 * `fallback` when the error carries no message of its own.
 */
export function describeApiError(err: unknown, fallback: string): string {
	if (err instanceof ApiError) {
		return err.message || fallback;
	}

	if (err instanceof Error) {
		return err.message || fallback;
	}

	return fallback;
}

/** Whether a thrown value is an API 404. */
export function isNotFound(err: unknown): boolean {
	return err instanceof ApiError && err.status === 404;
}

/**
 * Map a thrown value onto form error state: when it's an `ApiError` carrying
 * field errors, push those onto `setFieldErrors` and surface its message;
 * otherwise fall back to `describeApiError(err, fallback)`. This is the
 * `onError` block shared by every save mutation — `useFormSubmit` and the
 * hand-rolled editors both delegate here.
 */
export function applyApiFieldErrors(
	err: unknown,
	opts: {
		setFieldErrors: (fe: Record<string, string>) => void;
		setError: (msg: string) => void;
		fallback: string;
	}
): void {
	if (err instanceof ApiError) {
		const fe = err.fieldErrors();

		if (Object.keys(fe).length > 0) {
			opts.setFieldErrors(fe);
		}

		opts.setError(err.message || opts.fallback);
	} else {
		opts.setError(describeApiError(err, opts.fallback));
	}
}

/**
 * Like `describeApiError` but also translates the DOMException names thrown
 * by the WebAuthn/credential APIs into plain-English messages.
 */
export function describeWebAuthnError(err: unknown, fallback: string): string {
	if (err instanceof DOMException) {
		if (err.name === "NotAllowedError") {
			return "Cancelled — no passkey was registered.";
		}

		if (err.name === "InvalidStateError") {
			return "This authenticator already has a passkey for this account.";
		}

		if (err.name === "NotSupportedError") {
			return "Your authenticator doesn't support the required passkey settings.";
		}
	}

	return describeApiError(err, fallback);
}
