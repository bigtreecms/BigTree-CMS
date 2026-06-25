import type { Meta, StoryObj } from "@storybook/react-vite";

import { Field, FieldLabel } from "./Field";
import { TextInput } from "./TextInput";

/**
 * Label + optional hint/error wrapper shared by the form-control primitives and
 * any one-off field needing the same chrome. Renders a `<label>`, so the control
 * passed as `children` is associated automatically. Use it directly when you
 * need a `ref` or native attribute on the control that the `*Field` wrappers
 * don't forward.
 */
const meta = {
	title: "UI/Form/Field",
	component: Field,
	tags: ["autodocs"],
	args: {
		label: "Email address",
		children: <TextInput placeholder="you@example.com" />,
	},
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 360 }}>
				<Story />
			</div>
		),
	],
} satisfies Meta<typeof Field>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithHint: Story = {
	args: { hint: "We'll only use this for password resets." },
};

export const Required: Story = {
	args: { required: true },
};

export const WithError: Story = {
	args: { error: "Enter a valid email address." },
};

/** A short hint can sit inline on the label line (the resource-designer treatment). */
export const InlineHint: Story = {
	args: { label: "Slug", inlineHint: "lowercase, no spaces" },
};

/**
 * `size="sm"` is the denser label used by the resource designer (`ControlShell`
 * composes this). Pair with an `inlineHint` and a below `hint`.
 */
export const Small: Story = {
	args: {
		label: "Column",
		size: "sm",
		inlineHint: "optional",
		hint: "Leave blank to use the field key.",
	},
};

/**
 * `as="div"` renders the same label/hint/error chrome around a custom control
 * (Combobox / icon picker / schema row) that must not be nested in a `<label>`.
 * The label becomes a `FieldLabel as="span"` instead of wrapping the control.
 */
export const CustomControl: StoryObj = {
	render: () => (
		<Field
			as="div"
			label="Icon"
			required
			hint="Choose an icon for this field."
			error="An icon is required."
		>
			<div className="rounded-md border border-border bg-surface-2 p-2 text-[12px] text-text-3">
				custom control
			</div>
		</Field>
	),
};

/**
 * `FieldLabel` is the same label typography on its own — for a label above a
 * custom control where `Field`'s wrapping `<label>` is wrong. Default
 * `as="span"`; pass `tone="muted"` for the de-emphasized filter-label tier.
 */
export const StandaloneLabel: StoryObj = {
	render: () => (
		<div className="space-y-3">
			<div>
				<FieldLabel required>Icon</FieldLabel>
				<div className="rounded-md border border-border bg-surface-2 p-2 text-[12px] text-text-3">
					custom control
				</div>
			</div>
			<div>
				<FieldLabel size="sm" tone="muted">
					Table
				</FieldLabel>
				<div className="rounded-md border border-border bg-surface-2 p-2 text-[12px] text-text-3">
					filter control
				</div>
			</div>
		</div>
	),
};
