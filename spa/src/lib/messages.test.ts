import { describe, expect, it } from "vitest";

import { replyRecipients } from "@/lib/messages";

const message = {
	sender: 2,
	sender_name: "Sam",
	recipient_names: [
		{ id: 1, name: "Me" },
		{ id: 3, name: "Alex" },
		{ id: 2, name: "Sam" },
		{ id: 4, name: null },
	],
};

describe("replyRecipients", () => {
	it("replies to the sender by name", () => {
		expect(replyRecipients(message, 1, false)).toEqual([{ id: 2, name: "Sam", email: "" }]);
	});

	it("adds every other recipient for reply all, minus me and repeats", () => {
		expect(replyRecipients(message, 1, true).map((r) => r.name)).toEqual([
			"Sam",
			"Alex",
			"User #4",
		]);
	});

	it("leaves the sender out when I sent it", () => {
		expect(replyRecipients(message, 2, true).map((r) => r.id)).toEqual([1, 3, 4]);
	});
});
