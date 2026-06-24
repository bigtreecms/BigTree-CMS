import type { Meta, StoryObj } from "@storybook/react-vite";

import { RemovableChip } from "./RemovableChip";

/**
 * A removable tag pill — for selected tokens the user can dismiss (tag inputs,
 * message recipients). Distinct from `Chip`, which is a toggleable filter.
 */
const meta = {
	title: "UI/RemovableChip",
	component: RemovableChip,
	tags: ["autodocs"],
	args: { label: "Marketing", onRemove: () => {} },
} satisfies Meta<typeof RemovableChip>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Disabled: Story = {
	args: { disabled: true },
};

/** Several together, as a tag input or recipient list renders them. */
export const Group: Story = {
	render: () => (
		<div className="flex flex-wrap gap-1.5">
			{["Design", "Engineering", "Operations"].map((label) => (
				<RemovableChip key={label} label={label} onRemove={() => {}} />
			))}
		</div>
	),
};
