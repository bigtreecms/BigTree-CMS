import type { Meta, StoryObj } from "@storybook/react-vite";

import { FieldGrid } from "./FieldGrid";
import { Field } from "./Field";
import { TextInput } from "./TextInput";

/**
 * The responsive two-column field grid: one column on small screens, two from
 * `md` up. The single source of truth for the paired-input form layout used
 * across the module designer and developer edit pages.
 */
const meta = {
	title: "UI/Layout/FieldGrid",
	component: FieldGrid,
	tags: ["autodocs"],
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 720 }}>
				<Story />
			</div>
		),
	],
} satisfies Meta<typeof FieldGrid>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Four fields pairing into two columns at `md` and up. */
export const Default: Story = {
	args: {
		children: (
			<>
				<Field label="Title">
					<TextInput defaultValue="Quarterly report" />
				</Field>
				<Field label="Slug">
					<TextInput defaultValue="quarterly-report" />
				</Field>
				<Field label="Limit" hint="Defaults to 15.">
					<TextInput defaultValue="15" />
				</Field>
				<Field label="Order by">
					<TextInput defaultValue="created_at" />
				</Field>
			</>
		),
	},
};
