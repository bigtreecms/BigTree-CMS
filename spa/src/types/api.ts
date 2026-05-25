/**
 * Shared types for the BigTree REST API envelope.
 *
 * Every endpoint returns either { data, meta } or { errors, meta }. The Kernel
 * never mixes the two, so a typed discriminated union would be overkill —
 * we model success and error separately and the client decides which path
 * to take based on HTTP status.
 */

export interface ApiMeta {
	request_id?: string;
	page?: number;
	per_page?: number;
	total?: number;
	pages?: number;
	next_cursor?: string | null;
	[key: string]: unknown;
}

export interface ApiSuccess<T> {
	data: T;
	meta?: ApiMeta;
}

export interface ApiFieldError {
	code: string;
	field?: string;
	message: string;
	[key: string]: unknown;
}

export interface ApiErrorPayload {
	errors: ApiFieldError[];
	meta?: ApiMeta;
}

/**
 * Thrown by the fetch wrapper on any non-2xx response. Carries everything the
 * UI needs to surface a useful message: the HTTP status, the first error code
 * (machine-stable), the first error message (human), and per-field errors for
 * form binding.
 */
export class ApiError extends Error {
	readonly status: number;
	readonly code: string;
	readonly errors: ApiFieldError[];
	readonly requestId?: string;

	constructor(status: number, payload: ApiErrorPayload | null, fallback?: string) {
		const errors = payload?.errors ?? [];
		const first = errors[0];
		const message = first?.message ?? fallback ?? `HTTP ${status}`;
		super(message);
		this.name = "ApiError";
		this.status = status;
		this.code = first?.code ?? "unknown_error";
		this.errors = errors;
		this.requestId = payload?.meta?.request_id;
	}

	/**
	 * Convenience: returns a map of field → message for binding to form
	 * libraries (react-hook-form's setError, etc.).
	 */
	fieldErrors(): Record<string, string> {
		const out: Record<string, string> = {};
		for (const e of this.errors) {
			if (e.field) out[e.field] = e.message;
		}
		return out;
	}
}
