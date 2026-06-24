import type { Meta, StoryObj } from "@storybook/react-vite";
import { AlertTriangle } from "lucide-react";

import { Alert } from "./Alert";

/**
 * The lighter inline notice banner — save-failure summaries, field errors, heads-up
 * notices. Single source of truth for the bordered `rounded-md` message box. Tone
 * drives the token colors; keep the surrounding margin in `className`.
 */
const meta = {
	title: "UI/Alert",
	component: Alert,
	tags: ["autodocs"],
	args: { children: "Couldn't save — please fix the errors above.", tone: "danger" },
	argTypes: {
		tone: { control: "inline-radio", options: ["danger", "warn", "success", "info"] },
	},
} satisfies Meta<typeof Alert>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Playground: Story = {};

/** The four tones, each mapped to its design token. */
export const Tones: Story = {
	render: () => (
		<div className="flex w-96 flex-col gap-3">
			<Alert tone="danger">Couldn&apos;t save — please fix the errors above.</Alert>
			<Alert tone="warn">This page has unpublished changes.</Alert>
			<Alert tone="success">Settings saved.</Alert>
			<Alert tone="info">
				This is a legacy field type rendered through the server bridge.
			</Alert>
		</div>
	),
};

/** A bold `title` line over a body — the multi-line error/warning summary. */
export const WithTitle: Story = {
	render: () => (
		<div className="w-96">
			<Alert
				tone="danger"
				icon={<AlertTriangle size={13} />}
				title="Errors — fix these before installing"
			>
				<ul className="list-disc space-y-1 pl-5 text-[12px]">
					<li>Missing required field “name”</li>
					<li>Duplicate route</li>
				</ul>
			</Alert>
		</div>
	),
};

/** `mono` renders the body in a monospace face — the code / build-error variant. */
export const Mono: Story = {
	args: { mono: true, children: "ParseError: unexpected token on line 12" },
};
