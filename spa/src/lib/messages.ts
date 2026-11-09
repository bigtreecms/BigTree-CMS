import type { Message } from "@/api/endpoints/dashboard";

/** A person picked as a message recipient. */
export interface RecipientChip {
	email: string;
	id: number;
	name: string;
}

/**
 * Who a reply goes to: the original sender, and with `all` everyone else the
 * message was sent to — minus the current user, without repeats.
 */
export const replyRecipients = (
	message: Pick<Message, "recipient_names" | "sender" | "sender_name">,
	currentUserId: number,
	all: boolean
): RecipientChip[] => {
	const people = [
		{ id: message.sender, name: message.sender_name },
		...(all ? message.recipient_names : []),
	];
	const seen = new Set<number>([currentUserId]);
	const out: RecipientChip[] = [];

	for (const person of people) {
		if (seen.has(person.id)) {
			continue;
		}

		seen.add(person.id);
		out.push({ id: person.id, name: person.name ?? `User #${person.id}`, email: "" });
	}

	return out;
};
