import { apiBase } from "@/lib/adminBoot";
import { authStore } from "@/auth/store";
import type { ChatProposal, ChatToolActivity } from "@/api/endpoints/ai";
import type { SearchResultGroups } from "@/api/endpoints/search";

/**
 * Streaming transport for POST /ai/chat/stream (Phase 5). The core `api` client
 * assumes a JSON envelope, so streaming lives here: a bare fetch that reads the
 * Server-Sent-Events body and dispatches typed events to the caller.
 *
 * The `done` event is authoritative — the caller renders the final message from
 * it — so the token stream is only a live preview. Any transport failure throws;
 * callers fall back to the buffered aiApi.chat(), which owns the refresh-on-401
 * retry the raw stream fetch deliberately does not.
 */

/** The terminal `done` payload — mirrors the buffered ChatTurn response. */
export interface ChatStreamDone {
	artifacts: SearchResultGroups;
	conversation_id: number;
	message: {
		id: number;
		role: "assistant";
		content: string;
		tool_activity: ChatToolActivity[];
		proposals: ChatProposal[];
		created_at: string;
	};
	rounds: number;
	title: string;
}

export interface ChatStreamHandlers {
	/** Terminal success — render from this and stop. */
	onDone: (done: ChatStreamDone) => void;
	/**
	 * The turn's conversation id (and title), emitted before any tokens. Lets a
	 * brand-new thread reconcile if the connection later drops. Not an "answer
	 * received" signal — arriving before content, it must not suppress the
	 * buffered fallback.
	 */
	onMeta?: (meta: { conversation_id: number; title: string }) => void;
	/** Discard the streamed draft: that round became tool calls, not the answer. */
	onReset: () => void;
	/** An answer token arrived — append it to the visible draft. */
	onToken: (text: string) => void;
	/** A tool ran (name + AIToolResult status). */
	onTool: (tool: { name: string; status: string }) => void;
}

/** Thrown when the stream can't start or the server sent an `error` event. */
export class ChatStreamError extends Error {
	readonly status?: number;

	constructor(message: string, status?: number) {
		super(message);
		this.name = "ChatStreamError";
		this.status = status;
	}
}

interface SseEvent {
	data: string;
	event: string;
}

/** Parse one raw SSE block ("event: x\ndata: y") into its event name + data. */
const parseSseBlock = (block: string): SseEvent => {
	let event = "message";
	const dataLines: string[] = [];

	for (const line of block.split("\n")) {
		if (line.startsWith("event:")) {
			event = line.slice(6).trim();
		} else if (line.startsWith("data:")) {
			dataLines.push(line.slice(5).replace(/^ /, ""));
		}
	}

	return { event, data: dataLines.join("\n") };
};

/**
 * Send one message and stream the assistant's turn. Resolves once the `done`
 * event has been handled; rejects (ChatStreamError) on any transport or provider
 * failure so the caller can fall back to the buffered path.
 */
export const streamChat = async (
	message: string,
	conversationId: number | undefined,
	handlers: ChatStreamHandlers,
	signal?: AbortSignal
): Promise<void> => {
	const token = authStore.getState().accessToken;
	const response = await fetch(`${apiBase()}/ai/chat/stream`, {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
			Accept: "text/event-stream",
			...(token ? { Authorization: `Bearer ${token}` } : {}),
		},
		body: JSON.stringify({ message, conversation_id: conversationId }),
		signal,
	});

	if (!response.ok || !response.body) {
		// A non-2xx here is a JSON error envelope (setup failed before streaming);
		// surface its status so the caller can decide to fall back or refresh.
		let detail = `Streaming failed (HTTP ${response.status}).`;

		try {
			const payload = await response.json();
			detail = payload?.errors?.[0]?.message ?? detail;
		} catch {
			// non-JSON body — keep the generic message
		}

		throw new ChatStreamError(detail, response.status);
	}

	const reader = response.body.getReader();
	const decoder = new TextDecoder();
	let buffer = "";
	let done = false;

	const dispatch = (block: string): void => {
		const trimmed = block.trim();

		if (trimmed === "") {
			return;
		}

		const evt = parseSseBlock(block);
		let data: unknown = null;

		if (evt.data !== "") {
			try {
				data = JSON.parse(evt.data);
			} catch {
				return;
			}
		}

		if (evt.event === "meta") {
			const m = data as { conversation_id?: number; title?: string };
			handlers.onMeta?.({
				conversation_id: Number(m?.conversation_id ?? 0),
				title: String(m?.title ?? ""),
			});
		} else if (evt.event === "token") {
			handlers.onToken(String((data as { text?: string })?.text ?? ""));
		} else if (evt.event === "reset") {
			handlers.onReset();
		} else if (evt.event === "tool") {
			const t = data as { name?: string; status?: string };
			handlers.onTool({ name: String(t?.name ?? ""), status: String(t?.status ?? "") });
		} else if (evt.event === "done") {
			done = true;
			handlers.onDone(data as ChatStreamDone);
		} else if (evt.event === "error") {
			throw new ChatStreamError(
				String((data as { message?: string })?.message ?? "The assistant stream failed.")
			);
		}
	};

	// Read the stream, splitting complete "\n\n"-delimited event blocks.
	for (;;) {
		const { done: streamDone, value } = await reader.read();

		if (value) {
			buffer += decoder.decode(value, { stream: true });

			let idx: number;

			while ((idx = buffer.indexOf("\n\n")) >= 0) {
				const block = buffer.slice(0, idx);
				buffer = buffer.slice(idx + 2);
				dispatch(block);
			}
		}

		if (streamDone) {
			break;
		}
	}

	// Flush any trailing block the server didn't terminate with a blank line.
	if (buffer.trim() !== "") {
		dispatch(buffer);
	}

	if (!done) {
		throw new ChatStreamError("The assistant stream ended unexpectedly.");
	}
};
