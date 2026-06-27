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
