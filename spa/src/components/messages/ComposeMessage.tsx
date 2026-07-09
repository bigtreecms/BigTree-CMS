import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { keepPreviousData } from "@tanstack/react-query";
import { Search } from "lucide-react";

import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { SlideOver } from "@/components/ui/SlideOver";
import { Field, FieldLabel } from "@/components/ui/Field";
import { RemovableChip } from "@/components/ui/RemovableChip";
import { TextArea } from "@/components/ui/TextArea";
import { TextInput } from "@/components/ui/TextInput";

import { messagesApi, type Message } from "@/api/endpoints/dashboard";
import { usersApi } from "@/api/endpoints/users";
import { queryKeys } from "@/lib/queryKeys";

import { describeApiError } from "@/lib/errorHandling";
import { toast } from "@/lib/toast";

/**
 * Compose / reply slide-over. Multi-user recipient picker is a small
 * typeahead against `usersApi.list({ q })` — picked users render as chips
 * above the input, and the search results omit anyone already chosen.
 *
 * When `replyTo` is set, the dialog seeds subject (`Re: …`) + threads the
 * post via `in_response_to` and pre-populates recipients with the original
 * sender (minus the current user themselves).
 */

interface ComposeMessageProps {
	open: boolean;
	onOpenChange: (open: boolean) => void;
	currentUserId: number;
	/** Pre-populate from this thread when present. */
	replyTo?: Message | null;
}

interface UserChip {
	id: number;
	name: string;
	email: string;
}

export const ComposeMessage = ({
	open,
	onOpenChange,
	currentUserId,
	replyTo,
}: ComposeMessageProps) => {
	const queryClient = useQueryClient();

	const [subject, setSubject] = useState("");
	const [body, setBody] = useState("");
	const [recipients, setRecipients] = useState<UserChip[]>([]);
	const [search, setSearch] = useState("");
	const [debounced, setDebounced] = useState("");
	const [generalError, setGeneralError] = useState<string | null>(null);

	// Seed from replyTo whenever the dialog opens for a new message.
	useEffect(() => {
		if (!open) {
			return;
		}

		if (replyTo) {
			setSubject(
				replyTo.subject.startsWith("Re:") ? replyTo.subject : `Re: ${replyTo.subject}`
			);
			setBody("");
			// Reply target = original sender, unless we *are* the sender.
			if (replyTo.sender !== currentUserId) {
				setRecipients([{ id: replyTo.sender, name: `User #${replyTo.sender}`, email: "" }]);
			} else {
				setRecipients([]);
			}
		} else {
			setSubject("");
			setBody("");
			setRecipients([]);
		}

		setSearch("");
		setDebounced("");
		setGeneralError(null);
	}, [open, replyTo, currentUserId]);

	useEffect(() => {
		const handle = setTimeout(() => setDebounced(search.trim()), 200);

		return () => clearTimeout(handle);
	}, [search]);

	const searchEnabled = open && debounced.length >= 2;

	const usersQuery = useQuery({
		queryKey: queryKeys.users.list({ q: debounced }),
		queryFn: () => usersApi.list({ q: debounced, per_page: 12 }),
		enabled: searchEnabled,
		placeholderData: keepPreviousData,
	});

	const pickedIds = useMemo(() => new Set(recipients.map((r) => r.id)), [recipients]);

	const sendMutation = useMutation({
		mutationFn: () =>
			messagesApi.create({
				subject: subject.trim(),
				message: body.trim(),
				recipients: recipients.map((r) => r.id),
				in_response_to: replyTo?.id,
			}),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: queryKeys.messages.root() });
			toast.success("Message sent");
			onOpenChange(false);
		},
		onError: (err) => {
			setGeneralError(describeApiError(err, "Could not send"));
		},
	});

	const canSend = subject.trim().length > 0 && body.trim().length > 0 && recipients.length > 0;

	const addRecipient = (user: { id: number; name: string; email: string }) => {
		if (pickedIds.has(user.id) || user.id === currentUserId) {
			return;
		}

		setRecipients((prev) => [...prev, { id: user.id, name: user.name, email: user.email }]);
		setSearch("");
		setDebounced("");
	};

	const removeRecipient = (id: number) => {
		setRecipients((prev) => prev.filter((r) => r.id !== id));
	};

	return (
		<SlideOver
			open={open}
			onOpenChange={onOpenChange}
			title={replyTo ? "Reply to message" : "New message"}
			description={
				replyTo
					? "Your reply is threaded to the original message."
					: "Pick recipients, write your subject and body, then send."
			}
			width="md"
			footer={
				<div className="flex items-center justify-end gap-2">
					<Button
						variant="secondary"
						onClick={() => onOpenChange(false)}
						disabled={sendMutation.isPending}
					>
						Cancel
					</Button>
					<Button
						variant="primary"
						onClick={() => sendMutation.mutate()}
						disabled={!canSend}
						loading={sendMutation.isPending}
						loadingLabel="Sending…"
					>
						Send message
					</Button>
				</div>
			}
		>
			<div className="space-y-4">
				{generalError && <Alert tone="danger">{generalError}</Alert>}

				<div>
					<FieldLabel as="label" htmlFor="compose-recipient-search">
						Recipients
					</FieldLabel>

					{recipients.length > 0 && (
						<div className="mb-2 flex flex-wrap gap-1.5">
							{recipients.map((r) => (
								<RemovableChip
									key={r.id}
									label={r.name || `User #${r.id}`}
									onRemove={() => removeRecipient(r.id)}
								/>
							))}
						</div>
					)}

					<div className="relative">
						<Search
							size={13}
							className="absolute left-3 top-1/2 -translate-y-1/2 text-text-3"
						/>
						<input
							id="compose-recipient-search"
							className="w-full rounded-md border border-border bg-surface py-1.5 pl-9 pr-3 text-[13px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
							placeholder="Search users by name or email…"
							value={search}
							onChange={(e) => setSearch(e.target.value)}
						/>
					</div>

					{searchEnabled && (
						<div className="mt-1 overflow-hidden rounded-md border border-border">
							{usersQuery.isFetching && !usersQuery.data ? (
								<div className="px-3 py-2 text-[12px] text-text-3">Searching…</div>
							) : (usersQuery.data?.items ?? []).filter(
									(u) => !pickedIds.has(u.id) && u.id !== currentUserId
							  ).length === 0 ? (
								<div className="px-3 py-2 text-[12px] text-text-3">
									No matching users.
								</div>
							) : (
								<ul>
									{(usersQuery.data?.items ?? [])
										.filter(
											(u) => !pickedIds.has(u.id) && u.id !== currentUserId
										)
										.map((u) => (
											<li key={u.id}>
												<button
													type="button"
													className="flex w-full flex-col items-start gap-0.5 px-3 py-1.5 text-left hover:bg-hover"
													onClick={() =>
														addRecipient({
															id: u.id,
															name: u.name,
															email: u.email,
														})
													}
												>
													<span className="text-[12.5px] text-text-2">
														{u.name || u.email}
													</span>
													{u.name && (
														<span className="text-[11px] text-text-3">
															{u.email}
														</span>
													)}
												</button>
											</li>
										))}
								</ul>
							)}
						</div>
					)}
				</div>

				<Field label="Subject">
					<TextInput
						value={subject}
						onChange={(e) => setSubject(e.target.value)}
						maxLength={255}
					/>
				</Field>

				<Field label="Message">
					<TextArea
						rows={10}
						className="leading-relaxed"
						value={body}
						onChange={(e) => setBody(e.target.value)}
						placeholder="Plain text. A small set of inline HTML (a, b, em, p) is preserved by the server."
					/>
				</Field>
			</div>
		</SlideOver>
	);
};
