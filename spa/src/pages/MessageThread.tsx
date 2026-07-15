import { useEffect, useState } from "react";
import { Link, Navigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Reply } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Card } from "@/components/ui/Card";
import { DescriptionList } from "@/components/ui/DescriptionList";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Loading } from "@/components/ui/Loading";
import { Button } from "@/components/ui/Button";

import { ComposeMessage } from "@/components/messages/ComposeMessage";
import { messagesApi } from "@/api/endpoints/dashboard";
import { useAuthStore } from "@/auth/store";
import { sanitizeHtml } from "@/lib/html";
import { queryKeys } from "@/lib/queryKeys";

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
		queryKey: queryKeys.messages.detail(id),
		queryFn: () => messagesApi.get(id),
		enabled: valid,
	});

	const markReadMutation = useMutation({
		mutationFn: () => messagesApi.markRead(id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: queryKeys.messages.unreadCount() });
			queryClient.invalidateQueries({ queryKey: queryKeys.messages.lists() });
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
		return <Navigate replace to="/messages" />;
	}

	if (messageQ.isLoading || !messageQ.data) {
		return (
			<PageContainer width="narrow">
				<Loading variant="card" />
			</PageContainer>
		);
	}

	if (messageQ.error) {
		return (
			<PageContainer width="narrow">
				<ErrorPanel error={messageQ.error} />
			</PageContainer>
		);
	}

	const message = messageQ.data;
	// Server strips most HTML before storing; the surviving tags (<p>, <b>,
	// <em>, <i>, <a>, <strong>) are safe to render directly. We're not the
	// boundary for trust here — that's the server's job.
	const html = message.message;

	return (
		<PageContainer width="narrow">
			<Breadcrumb
				items={[
					{ label: "Messages", to: "/messages" },
					{ label: message.subject || "(no subject)" },
				]}
			/>

			<PageHead
				actions={
					<>
						<Button icon={<ChevronLeft size={13} />} to="/messages">
							Inbox
						</Button>
						<Button
							icon={<Reply size={13} />}
							variant="primary"
							onClick={() => setComposeOpen(true)}
						>
							Reply
						</Button>
					</>
				}
				sub={`From ${message.sender_name ?? `#${message.sender}`} · ${message.date}`}
				title={message.subject || "(no subject)"}
			/>

			<DescriptionList
				boxed
				className="mb-4"
				items={[
					{
						label: "From",
						value: message.sender_name ?? `User #${message.sender}`,
					},
					{
						label: "To",
						value: message.recipient_names
							.map((r) => r.name ?? `User #${r.id}`)
							.join(", "),
					},
					{ label: "Date", value: message.date },
					...(message.response_to > 0
						? [
								{
									label: "In reply to",
									value: (
										<Link
											className="text-accent hover:underline"
											to={`/messages/${message.response_to}`}
										>
											Message #{message.response_to}
										</Link>
									),
								},
							]
						: []),
				]}
			/>

			<Card
				as="article"
				className="text-[13.5px] leading-relaxed text-text"
				/* Sanitised server-side; only inline tags survive. */
				dangerouslySetInnerHTML={{ __html: sanitizeHtml(html) }}
				padding="sm"
			/>

			<ComposeMessage
				currentUserId={currentUserId}
				open={composeOpen}
				replyTo={message}
				onOpenChange={setComposeOpen}
			/>
		</PageContainer>
	);
};
