import { useEffect, useRef, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import * as Dialog from "@radix-ui/react-dialog";
import { History, Plus, Send, Sparkles, X } from "lucide-react";

import { aiApi, type ChatProposal } from "@/api/endpoints/ai";
import { ChatStreamError, streamChat, type ChatStreamDone } from "@/api/aiStream";
import { queryKeys } from "@/lib/queryKeys";
import { describeApiError, isNotFound } from "@/lib/errorHandling";
import { IconButton } from "@/components/ui/IconButton";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { ChatMessageView, type ChatEntry } from "./ChatMessageView";
import { ConversationList } from "./ConversationList";

/**
 * The AI assistant chat panel — a right-docked slide-over, sibling of QuickSearch
 * in the shell. Streaming (Phase 5): each send shows the user's message and a
 * placeholder, then the assistant's answer streams in token by token over SSE
 * (streamChat), with tool-activity rows appearing live; the terminal `done` event
 * carries the authoritative message + navigable artifacts. If the stream can't
 * start (setup/HTTP error, incl. a 401 needing refresh) it falls back to the
 * buffered aiApi.chat() path.
 *
 * Only mounted when features.ai_chat is enabled (see Shell / TopBar). Conversation
 * state persists across open/close so a thread isn't lost by dismissing the panel.
 */

interface AIChatProps {
	onClose: () => void;
	open: boolean;
}

const PENDING_ID = "pending";

const replacePending = (entries: ChatEntry[], resolved: ChatEntry): ChatEntry[] =>
	entries.map((e) => (e.id === PENDING_ID ? resolved : e));

/** The authoritative done payload → the resolved assistant entry. */
const doneToEntry = (data: ChatStreamDone): ChatEntry => ({
	id: data.message.id,
	role: "assistant",
	content: data.message.content,
	tool_activity: data.message.tool_activity,
	artifacts: data.artifacts,
	proposals: data.message.proposals,
});

export const AIChat = ({ open, onClose }: AIChatProps) => {
	const queryClient = useQueryClient();
	const [view, setView] = useState<"chat" | "history">("chat");
	const [conversationId, setConversationId] = useState<number | null>(null);
	const [entries, setEntries] = useState<ChatEntry[]>([]);
	const [input, setInput] = useState("");
	const [error, setError] = useState<string | null>(null);
	const [loadingConversation, setLoadingConversation] = useState(false);
	const [busyProposalId, setBusyProposalId] = useState<string | null>(null);
	const [streaming, setStreaming] = useState(false);

	const scrollRef = useRef<HTMLDivElement>(null);
	const inputRef = useRef<HTMLTextAreaElement>(null);
	const abortRef = useRef<AbortController | null>(null);

	// Abort any in-flight stream on unmount so a closed panel stops consuming it.
	useEffect(() => () => abortRef.current?.abort(), []);

	// Keep the transcript pinned to the newest turn.
	useEffect(() => {
		scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: "smooth" });
	}, [entries, loadingConversation]);

	// Focus the composer when the panel opens on the chat view.
	useEffect(() => {
		if (open && view === "chat") {
			const t = setTimeout(() => inputRef.current?.focus(), 60);

			return () => clearTimeout(t);
		}
	}, [open, view]);

	// Buffered fallback: used when the stream can't start (setup/HTTP error, incl.
	// a 401 whose refresh-and-retry only the core api client handles).
	const sendMutation = useMutation({
		mutationFn: (message: string) => aiApi.chat(message, conversationId ?? undefined),
		onSuccess: (data) => {
			setConversationId(data.conversation_id);
			setEntries((prev) =>
				replacePending(prev, {
					id: data.message.id,
					role: "assistant",
					content: data.message.content,
					tool_activity: data.message.tool_activity,
					artifacts: data.artifacts,
					proposals: data.message.proposals,
				})
			);
			void queryClient.invalidateQueries({ queryKey: queryKeys.ai.conversations() });
		},
		onError: (err) => {
			setError(describeApiError(err, "The assistant could not respond."));
			setEntries((prev) => prev.filter((e) => e.id !== PENDING_ID));

			// A failed turn no longer discards what it had already staged: the server
			// keeps those cards and persists a message explaining them. Pull that
			// message in so they are visible where they were prepared rather than only
			// in history — appended, never reloaded. The buffered path's error carries
			// no word on what survived, and a wholesale reload of a turn that staged
			// nothing (nothing persisted) would silently drop the message the user
			// just typed and clear the error saying why.
			void queryClient.invalidateQueries({ queryKey: queryKeys.ai.conversations() });

			if (conversationId !== null) {
				void reconcileFailedTurn(conversationId);
			}
		},
	});

	const deleteMutation = useMutation({
		mutationFn: (id: number) => aiApi.deleteConversation(id),
		onSuccess: (_res, id) => {
			void queryClient.invalidateQueries({ queryKey: queryKeys.ai.conversations() });

			if (id === conversationId) {
				resetChat();
			}
		},
	});

	// Replace a proposal in-place after it resolves so the card flips to its new
	// state (approved/rejected) without reloading the whole conversation.
	const applyProposal = (updated: ChatProposal) => {
		setEntries((prev) =>
			prev.map((entry) =>
				entry.proposals
					? {
							...entry,
							proposals: entry.proposals.map((p) =>
								p.proposal_id === updated.proposal_id ? updated : p
							),
						}
					: entry
			)
		);
	};

	/**
	 * Re-read the conversation's proposals and fold their current state into the
	 * rendered cards. Cheaper and less jarring than reloading the whole thread: the
	 * messages haven't changed, only what became of the cards attached to them.
	 */
	const refreshProposals = async () => {
		if (conversationId === null) {
			return;
		}

		try {
			const detail = await aiApi.getConversation(conversationId);
			const byId = new Map(
				detail.messages.flatMap((m) => (m.proposals ?? []).map((p) => [p.proposal_id, p]))
			);

			setEntries((prev) =>
				prev.map((entry) =>
					entry.proposals
						? {
								...entry,
								proposals: entry.proposals.map((p) => byId.get(p.proposal_id) ?? p),
							}
						: entry
				)
			);
		} catch {
			// Best-effort: a refresh failure leaves the cards as they were.
		}
	};

	/**
	 * Fold in the message a *failed* turn left behind. The server keeps whatever
	 * the turn staged and records an assistant message explaining those cards
	 * (AIChatService::persistFailedTurn), so the one thing worth pulling in is that
	 * message — appended to the transcript the user is looking at, which keeps
	 * their own message and the error banner exactly where they are.
	 */
	const reconcileFailedTurn = async (id: number) => {
		try {
			const detail = await aiApi.getConversation(id);
			const last = detail.messages[detail.messages.length - 1];

			if (!last || last.role !== "assistant" || (last.proposals?.length ?? 0) === 0) {
				return;
			}

			setEntries((prev) =>
				prev.some((e) => e.id === last.id)
					? prev
					: [
							...prev,
							{
								id: last.id,
								role: "assistant",
								content: last.content,
								tool_activity: last.tool_activity,
								proposals: last.proposals,
							},
						]
			);
		} catch {
			// Best-effort: the error already tells the user the turn failed, and the
			// cards remain reachable from the history list either way.
		}
	};

	const approveMutation = useMutation({
		mutationFn: (id: string) => aiApi.approveProposal(id),
		onMutate: (id) => {
			setBusyProposalId(id);
			setError(null);
		},
		onSuccess: (data) => {
			applyProposal(data.proposal);
			// Approving one card can invalidate its siblings: they were staged against
			// the same record, and the fingerprint check will now refuse them. Reload
			// the thread so every card shows its real state rather than a stale
			// "awaiting your approval" on something that can no longer be approved.
			void refreshProposals();
			// An approval writes to the CMS, and any screen behind the panel is now
			// showing pre-approval data — a page tree without the page just created, a
			// template list without the template. Which screen that is depends on the
			// tool, so everything outside the assistant's own queries is marked stale
			// rather than enumerating targets a new tool would silently fall outside
			// of. Approvals are rare and user-initiated; only mounted queries refetch.
			void queryClient.invalidateQueries({
				predicate: (query) => query.queryKey[0] !== "ai",
			});
		},
		onError: (err) => setError(describeApiError(err, "The change could not be applied.")),
		onSettled: () => setBusyProposalId(null),
	});

	const rejectMutation = useMutation({
		mutationFn: (id: string) => aiApi.rejectProposal(id),
		onMutate: (id) => {
			setBusyProposalId(id);
			setError(null);
		},
		onSuccess: (data) => applyProposal(data.proposal),
		onError: (err) => setError(describeApiError(err, "The change could not be rejected.")),
		onSettled: () => setBusyProposalId(null),
	});

	const resetChat = () => {
		abortRef.current?.abort();
		abortRef.current = null;
		setStreaming(false);
		setConversationId(null);
		setEntries([]);
		setInput("");
		setError(null);
		setView("chat");
	};

	// Mutate the in-flight assistant placeholder (streaming updates target it).
	const updatePending = (fn: (entry: ChatEntry) => ChatEntry) =>
		setEntries((prev) => prev.map((e) => (e.id === PENDING_ID ? fn(e) : e)));

	const send = async (override?: string) => {
		const text = (override ?? input).trim();

		if (text === "" || sendMutation.isPending || streaming) {
			return;
		}

		setInput("");
		setError(null);
		setEntries((prev) => [
			...prev,
			{ id: `u-${Date.now()}`, role: "user", content: text },
			{ id: PENDING_ID, role: "assistant", content: "", pending: true },
		]);

		const controller = new AbortController();
		abortRef.current = controller;
		setStreaming(true);

		// Whether the stream produced anything yet. If it fails before the first
		// event (setup/HTTP error, CORS/network error), we fall back to the buffered
		// path — which owns the refresh-on-401 retry and surfaces real errors. Once
		// tokens have arrived we never re-send, so a mid-stream drop can't duplicate
		// the turn.
		let received = false;
		const markReceived = () => {
			received = true;
		};

		// The conversation id the server assigns this turn, captured from the early
		// `meta` event (before any tokens). Kept local — not pushed into state — so a
		// clean provider failure that rolls the new conversation back doesn't leave a
		// dangling id on the buffered-fallback path. Promoted into state only to
		// reconcile: a mid-stream drop after content arrived, or a server-side failure
		// that reports it kept the turn (so the row is still there).
		let streamConversationId: number | null = null;

		try {
			await streamChat(
				text,
				conversationId ?? undefined,
				{
					onMeta: (meta) => {
						streamConversationId = meta.conversation_id || null;
					},
					onToken: (chunk) => {
						markReceived();
						updatePending((e) => ({ ...e, content: e.content + chunk }));
					},
					// A tool round: drop the streamed preamble, keep the tool rows.
					onReset: () => {
						markReceived();
						updatePending((e) => ({ ...e, content: "" }));
					},
					onTool: (tool) => {
						markReceived();
						updatePending((e) => ({
							...e,
							tool_activity: [
								...(e.tool_activity ?? []),
								{ name: tool.name, arguments: {}, status: tool.status },
							],
						}));
					},
					onDone: (data) => {
						markReceived();
						setConversationId(data.conversation_id);
						setEntries((prev) => replacePending(prev, doneToEntry(data)));
						void queryClient.invalidateQueries({
							queryKey: queryKeys.ai.conversations(),
						});
					},
				},
				controller.signal
			);
		} catch (err) {
			if (controller.signal.aborted) {
				return;
			}

			// The server ended the turn itself and said what it did with it. Nothing
			// to fall back to (the buffered path would re-run a turn the server has
			// already settled) and nothing to reload — only the cards it kept, if it
			// kept any, are missing from the transcript.
			if (err instanceof ChatStreamError && err.fromServer) {
				setError(err.message);
				setEntries((prev) => prev.filter((e) => e.id !== PENDING_ID));
				void queryClient.invalidateQueries({ queryKey: queryKeys.ai.conversations() });

				const settledId = conversationId ?? streamConversationId;

				if (err.proposalsKept && settledId !== null) {
					setConversationId(settledId);
					void reconcileFailedTurn(settledId);
				}

				return;
			}

			if (!received) {
				sendMutation.mutate(text);
			} else {
				// Content arrived, then the connection dropped — the server likely
				// persisted this turn. Rather than silently discard the pending entry
				// (which for a brand-new thread would strand the turn and fork a second
				// conversation on the next send), reconcile from the server. Always
				// refresh the history list; refetch the thread if we know its id.
				void queryClient.invalidateQueries({ queryKey: queryKeys.ai.conversations() });

				const reconcileId = conversationId ?? streamConversationId;

				if (reconcileId !== null) {
					setConversationId(reconcileId);
					void openConversation(reconcileId);
				} else {
					setError(
						err instanceof Error ? err.message : "The assistant could not respond."
					);
					setEntries((prev) => prev.filter((e) => e.id !== PENDING_ID));
				}
			}
		} finally {
			if (abortRef.current === controller) {
				abortRef.current = null;
			}

			setStreaming(false);
		}
	};

	const openConversation = async (id: number) => {
		abortRef.current?.abort();
		abortRef.current = null;
		setStreaming(false);
		setView("chat");
		setLoadingConversation(true);
		setError(null);

		try {
			const detail = await aiApi.getConversation(id);
			setConversationId(detail.conversation.id);
			setEntries(
				detail.messages.map((m) => ({
					id: m.id,
					role: m.role,
					content: m.content,
					tool_activity: m.tool_activity,
					proposals: m.proposals,
				}))
			);
		} catch (err) {
			// A conversation the server deleted — a provider failure on a brand-new
			// thread rolls the row back — leaves the client holding a dead id that
			// every later send 404s against, permanently. Drop it and start fresh.
			if (isNotFound(err)) {
				setConversationId(null);
				setEntries([]);
				void queryClient.invalidateQueries({ queryKey: queryKeys.ai.conversations() });
				setError("That conversation is no longer available.");
			} else {
				setError(describeApiError(err, "Could not load that conversation."));
			}
		} finally {
			setLoadingConversation(false);
		}
	};

	const onComposerKey = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
		if (e.key === "Enter" && !e.shiftKey) {
			e.preventDefault();
			void send();
		}
	};

	return (
		<Dialog.Root
			open={open}
			onOpenChange={(isOpen) => {
				if (!isOpen) {
					onClose();
				}
			}}
		>
			<Dialog.Portal>
				<Dialog.Overlay className="fixed inset-0 z-60 bg-black/25" />
				<Dialog.Content
					aria-describedby={undefined}
					className="fixed right-0 top-0 z-70 flex h-screen w-[min(440px,100vw)] flex-col border-l border-border bg-surface shadow-lg focus:outline-none"
				>
					{/* Header */}
					<div className="flex items-center gap-2 border-b border-border px-4 py-3">
						<Sparkles className="text-accent" size={16} />
						<Dialog.Title className="flex-1 text-[14px] font-semibold tracking-[-0.01em]">
							{view === "history" ? "Conversations" : "Assistant"}
						</Dialog.Title>

						<IconButton label="New chat" title="New chat" onClick={resetChat}>
							<Plus size={16} />
						</IconButton>
						<IconButton
							ariaExpanded={view === "history"}
							label="Conversation history"
							title="History"
							onClick={() => setView((v) => (v === "history" ? "chat" : "history"))}
						>
							<History size={15} />
						</IconButton>
						<IconButton label="Close" title="Close" onClick={onClose}>
							<X size={16} />
						</IconButton>
					</div>

					{view === "history" ? (
						<div className="flex-1 overflow-auto">
							<ConversationList
								activeId={conversationId}
								onDelete={(id) => deleteMutation.mutate(id)}
								onSelect={(id) => void openConversation(id)}
							/>
						</div>
					) : (
						<>
							<div className="flex-1 space-y-4 overflow-auto p-4 " ref={scrollRef}>
								{loadingConversation && (
									<InlineEmpty align="center" className="py-8" variant="plain">
										Loading conversation…
									</InlineEmpty>
								)}

								{!loadingConversation && entries.length === 0 && (
									<InlineEmpty
										align="center"
										className="py-12"
										pad="xl"
										variant="plain"
									>
										Ask about your pages, modules, entries, tags, or users.
									</InlineEmpty>
								)}

								{entries.map((entry) => (
									<ChatMessageView
										busyProposalId={busyProposalId}
										entry={entry}
										key={entry.id}
										onApproveProposal={(id) => approveMutation.mutate(id)}
										onChooseOption={(choice) => void send(choice)}
										onNavigate={onClose}
										onRejectProposal={(id) => rejectMutation.mutate(id)}
									/>
								))}

								{error && (
									<div className="rounded-lg border border-danger/30 bg-surface-2/50 px-3 py-2 text-[12px] text-danger">
										{error}
									</div>
								)}
							</div>

							{/* Composer */}
							<div className="border-t border-border p-3">
								<div className="flex items-end gap-2 rounded-xl border border-border bg-surface-2/50 px-3 py-2 focus-within:border-border-strong">
									<textarea
										className="max-h-32 flex-1 resize-none bg-transparent text-[13px] text-text placeholder:text-text-3 focus:outline-none"
										placeholder="Ask the assistant…"
										ref={inputRef}
										rows={1}
										spellCheck={false}
										value={input}
										onChange={(e) => setInput(e.target.value)}
										onKeyDown={onComposerKey}
									/>
									<IconButton
										disabled={
											input.trim() === "" ||
											sendMutation.isPending ||
											streaming
										}
										label="Send"
										title="Send (Enter)"
										tone="accent"
										onClick={() => void send()}
									>
										<Send size={15} />
									</IconButton>
								</div>
								<p className="mt-1.5 px-1 text-[10.5px] text-text-3">
									The assistant can read your content and propose changes. Nothing
									happens until you approve — you stay in control.
								</p>
							</div>
						</>
					)}
				</Dialog.Content>
			</Dialog.Portal>
		</Dialog.Root>
	);
};
