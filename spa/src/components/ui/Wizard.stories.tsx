import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { Field } from "./Field";
import { TextInput } from "./TextInput";
import { Wizard } from "./Wizard";

/**
 * Multi-step shell with progress dots + Back/Next/Finish. Step content is
 * controlled; the host gates advancing via `steps[i].canAdvance`. Used by the
 * module designer and the upgrade flow.
 */
const steps = [
	{
		key: "details",
		title: "Details",
		description: "Name your module and give it a route.",
		content: (
			<div className="flex max-w-sm flex-col gap-4">
				<Field label="Module name">
					<TextInput defaultValue="Team Members" />
				</Field>
				<Field label="Route">
					<TextInput defaultValue="team-members" />
				</Field>
			</div>
		),
	},
	{
		key: "fields",
		title: "Fields",
		description: "Add the fields editors will fill in.",
		content: <p className="text-[13px] text-text-2">Field builder goes here.</p>,
	},
	{
		key: "review",
		title: "Review",
		description: "Confirm and create the module.",
		content: <p className="text-[13px] text-text-2">Everything looks good.</p>,
	},
];

const meta = {
	title: "UI/Wizard",
	component: Wizard,
	tags: ["autodocs"],
	args: { steps, current: 0, onStepChange: () => {} },
	render: (args) => {
		const [current, setCurrent] = useState(args.current);

		return (
			<Wizard
				{...args}
				current={current}
				onStepChange={setCurrent}
				onCancel={() => setCurrent(0)}
				onFinish={() => setCurrent(0)}
			/>
		);
	},
} satisfies Meta<typeof Wizard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const FirstStep: Story = {};

export const MiddleStep: Story = {
	args: { current: 1 },
};

/** `canAdvance: false` disables Next until the step validates. */
export const CannotAdvance: Story = {
	args: {
		steps: steps.map((s, i) => (i === 0 ? { ...s, canAdvance: false } : s)),
	},
};
