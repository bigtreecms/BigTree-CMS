import { useCallback, useRef, useState } from "react";

import { authStore } from "@/auth/store";
import { apiBase } from "@/lib/adminBoot";

export type UploadStatus = "pending" | "uploading" | "done" | "error" | "canceled";

export interface UploadItem {
	error?: string;
	file: File;
	id: number;
	progress: number;
	/** API-decoded response body once the upload finishes successfully. */
	result?: unknown;
	status: UploadStatus;
}

export interface UploadOptions {
	/** Extra form fields to send alongside the file. */
	extra?: Record<string, string | number | boolean | undefined | null>;
	/** Form field name carrying the file. Defaults to "file". */
	fieldName?: string;
	/** API path relative to {@link apiBase}, e.g. "/resources". */
	path: string;
}

/**
 * Multipart upload helper with per-file progress tracking. Returns the current
 * queue plus imperative `enqueue`/`cancel`/`reset` controls. Each upload uses
 * `XMLHttpRequest` (fetch doesn't expose upload progress) but otherwise mirrors
 * the auth conventions used by the central `api` client.
 *
 * Consumers should call `enqueue([files], opts)` from a drop handler / file
 * input change. The hook does NOT auto-invalidate any TanStack Query keys —
 * the caller decides when (e.g. on individual file `status === "done"`).
 */
export const useUploads = () => {
	const [items, setItems] = useState<UploadItem[]>([]);
	const xhrs = useRef<Map<number, XMLHttpRequest> | null>(null);

	// Lazy init so the Map isn't rebuilt and discarded on every render. Runs
	// during render before any callback below can fire, so `.current` is always
	// a Map by the time enqueue/cancel/reset read it.
	if (xhrs.current === null) {
		xhrs.current = new Map<number, XMLHttpRequest>();
	}

	const nextId = useRef(1);

	const update = (id: number, patch: Partial<UploadItem>) => {
		setItems((current) => current.map((it) => (it.id === id ? { ...it, ...patch } : it)));
	};

	const enqueue = useCallback((files: File[] | FileList, opts: UploadOptions) => {
		const list = Array.from(files);
		const added: UploadItem[] = list.map((file) => ({
			id: nextId.current++,
			file,
			progress: 0,
			status: "pending",
		}));

		setItems((current) => [...current, ...added]);

		for (const item of added) {
			startUpload(item, opts, update, xhrs.current!);
		}

		return added.map((i) => i.id);
	}, []);

	const cancel = useCallback((id: number) => {
		const xhr = xhrs.current!.get(id);

		if (xhr) {
			xhr.abort();
		}
	}, []);

	const reset = useCallback(() => {
		const map = xhrs.current!;

		for (const xhr of map.values()) {
			xhr.abort();
		}

		map.clear();
		setItems([]);
	}, []);

	return {
		items,
		enqueue,
		cancel,
		reset,
	};
};

const startUpload = (
	item: UploadItem,
	opts: UploadOptions,
	update: (id: number, patch: Partial<UploadItem>) => void,
	xhrs: Map<number, XMLHttpRequest>
): void => {
	const form = new FormData();
	form.append(opts.fieldName ?? "file", item.file);

	if (opts.extra) {
		for (const [key, value] of Object.entries(opts.extra)) {
			if (value === undefined || value === null) {
				continue;
			}

			form.append(key, String(value));
		}
	}

	const xhr = new XMLHttpRequest();
	xhrs.set(item.id, xhr);
	xhr.open("POST", `${apiBase()}${opts.path}`);

	const token = authStore.getState().accessToken;

	if (token) {
		xhr.setRequestHeader("Authorization", `Bearer ${token}`);
	}

	xhr.setRequestHeader("Accept", "application/json");

	xhr.upload.onprogress = (event) => {
		if (!event.lengthComputable) {
			return;
		}

		update(item.id, {
			status: "uploading",
			progress: Math.round((event.loaded / event.total) * 100),
		});
	};

	xhr.onload = () => {
		xhrs.delete(item.id);

		if (xhr.status >= 200 && xhr.status < 300) {
			let result: unknown = null;

			try {
				const parsed = JSON.parse(xhr.responseText);
				result = parsed?.data ?? parsed;
			} catch {
				// non-JSON response — leave result undefined
			}

			update(item.id, { status: "done", progress: 100, result });

			return;
		}

		let message = `Upload failed (${xhr.status})`;

		try {
			const payload = JSON.parse(xhr.responseText);
			message = payload?.errors?.[0]?.message ?? message;
		} catch {
			// keep default message
		}

		update(item.id, { status: "error", error: message });
	};

	xhr.onerror = () => {
		xhrs.delete(item.id);
		update(item.id, { status: "error", error: "Network error" });
	};

	xhr.onabort = () => {
		xhrs.delete(item.id);
		update(item.id, { status: "canceled" });
	};

	update(item.id, { status: "uploading" });
	xhr.send(form);
};
