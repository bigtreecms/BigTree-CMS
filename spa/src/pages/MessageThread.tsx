import { useEffect, useState } from "react";
import { Link, Navigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Reply } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";

import { ComposeMessage } from "@/components/messages/ComposeMessage";
import { messagesApi } from "@/api/endpoints/dashboard";
import { useAuthStore } from "@/auth/store";
import { sanitizeHtml } from "@/lib/html";

/**
 * Single-message view. Marks the message read on mount, refreshes the unread
 * count, and exposes a Reply button that opens the Compose slide-over with
 * the thread pre-filled.
 *
 * Threading note: the API stores `response_to` per message, but doesn't yet
 * expose a thread roll-up endpoint — we render the single message and rely on
 * subject prefix ("Re: …") for visual continuity, matching the legacy admin.
 */
export const MessageThread = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const id = Number(idParam);
	const valid = Number.isFinite(id) && id > 0;
	const queryClient = useQueryClient();
	const currentUserId = useAuthStore((s) => s.user?.id ?? 0);

	const [composeOpen, setComposeOpen] = useState(false);

	const messageQ = useQuery({
		queryKey: ["messages", "detail", id],
		queryFn: () => messagesApi.get(id),
		enabled: valid,
	});

	const markReadMutation = useMutation({
		mutationFn: () => messagesApi.markRead(id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["messages", "unread-count"] });
			queryClient.invalidateQueries({ queryKey: ["messages", "list"] });
		},
	});

	// Fire the read-flip once per load, only if the current user is a
	// recipient AND hasn't been recorded as read yet.
	useEffect(() => {
		if (!messageQ.data || !currentUserId) {
			return;
		}

		const isRecipient = messageQ.data.recipients.includes(currentUserId);
		const alreadyRead = messageQ.data.read_by.includes(currentUserId);

		if (isRecipient && !alreadyRead) {
			markReadMutation.mutate();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- only fire on initial load
	}, [messageQ.data?.id]);

	if (!valid) {
		return <Navigate to="/messages" replace />;
	}

	if (messageQ.isLoading || !messageQ.data) {
		return (
			<div className="mx-auto max-w-3xl px-6 py-4">
				<EmptyState>Loading…</EmptyState>
			</div>
		);
	}

	if (messageQ.error) {
		return (
			<div className="mx-auto max-w-3xl px-6 py-4">
				<ErrorPanel error={messageQ.error} />
			</div>
		);
	}

	const message = messageQ.data;
	// Server strips most HTML before storing; the surviving tags (<p>, <b>,
	// <em>, <i>, <a>, <strong>) are safe to render directly. We're not the
	// boundary for trust here — that's the server's job.
	const html = message.message;

	return (
		<div className="mx-auto max-w-3xl px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Messages", to: "/messages" },
					{ label: message.subject || "(no subject)" },
				]}
			/>

			<PageHead
				title={message.subject || "(no subject)"}
				sub={`From ${message.sender_name ?? `#${message.sender}`} · ${message.date}`}
				actions={
					<>
						<Button icon={<ChevronLeft size={13} />} to="/messages">
							Inbox
						</Button>
						<Button
							variant="primary"
							icon={<Reply size={13} />}
							onClick={() => setComposeOpen(true)}
						>
							Reply
						</Button>
					</>
				}
			/>

			<dl className="mb-4 grid grid-cols-[120px_minmax(0,1fr)] gap-x-3 gap-y-1.5 rounded-lg border border-border bg-surface-2 p-3 text-[12.5px]">
				<dt className="text-text-3">From</dt>
				<dd className="text-text-2">{message.sender_name ?? `User #${message.sender}`}</dd>
				<dt className="text-text-3">To</dt>
				<dd className="text-text-2">
					{message.recipient_names.map((r) => r.name ?? `User #${r.id}`).join(", ")}
				</dd>
				<dt className="text-text-3">Date</dt>
				<dd className="text-text-2">{message.date}</dd>
				{message.response_to > 0 && (
					<>
						<dt className="text-text-3">In reply to</dt>
						<dd>
							<Link
								to={`/messages/${message.response_to}`}
								className="text-accent hover:underline"
							>
								Message #{message.response_to}
							</Link>
						</dd>
					</>
				)}
			</dl>

			<article
				className="rounded-xl border border-border bg-surface p-4 text-[13.5px] leading-relaxed text-text"
				/* Sanitised server-side; only inline tags survive. */
				dangerouslySetInnerHTML={{ __html: sanitizeHtml(html) }}
			/>

			<ComposeMessage
				open={composeOpen}
				onOpenChange={setComposeOpen}
				currentUserId={currentUserId}
				replyTo={message}
			/>
		</div>
	);
};
