import type { Meta, StoryObj } from "@storybook/react-vite";
import { Plus, Save, Trash2 } from "lucide-react";

import { Button } from "./Button";

/**
 * `Button` is the single button primitive for the whole admin — header actions,
 * form footers, toolbars, dialogs. This story is the reference shape for the
 * catalog: copy it when adding stories for new primitives.
 */
const meta = {
	title: "UI/Button",
	component: Button,
	tags: ["autodocs"],
	args: {
		children: "Button",
		variant: "secondary",
		size: "md",
	},
	argTypes: {
		variant: {
			control: "inline-radio",
			options: ["primary", "secondary", "danger", "dangerGhost", "link"],
		},
		size: { control: "inline-radio", options: ["sm", "md", "lg"] },
		onClick: { action: "clicked" },
	},
} satisfies Meta<typeof Button>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Playground: Story = {};

/** Every variant at the default `md` size. */
export const Variants: Story = {
	render: (args) => (
		<div className="flex flex-wrap items-center gap-3">
			<Button {...args} variant="primary">
				Primary
			</Button>
			<Button {...args} variant="secondary">
				Secondary
			</Button>
			<Button {...args} variant="danger">
				Danger
			</Button>
			<Button {...args} variant="dangerGhost">
				Danger ghost
			</Button>
			<Button {...args} variant="link">
				Link
			</Button>
		</div>
	),
};

/** The three sizes, shown for the primary variant. */
export const Sizes: Story = {
	render: (args) => (
		<div className="flex flex-wrap items-center gap-3">
			<Button {...args} variant="primary" size="sm">
				Small
			</Button>
			<Button {...args} variant="primary" size="md">
				Medium
			</Button>
			<Button {...args} variant="primary" size="lg">
				Large
			</Button>
		</div>
	),
};

/** Buttons can carry a leading lucide icon (size 13 to match the type). */
export const WithIcon: Story = {
	render: (args) => (
		<div className="flex flex-wrap items-center gap-3">
			<Button {...args} variant="primary" icon={<Save size={13} />}>
				Save
			</Button>
			<Button {...args} variant="secondary" icon={<Plus size={13} />}>
				Add new
			</Button>
			<Button {...args} variant="dangerGhost" icon={<Trash2 size={13} />}>
				Delete
			</Button>
		</div>
	),
};

/** `to` renders a router `<Link>`, `href` renders an `<a>`; both look identical. */
export const AsLink: Story = {
	render: (args) => (
		<div className="flex flex-wrap items-center gap-3">
			<Button {...args} variant="primary" to="/pages">
				Internal link (to)
			</Button>
			<Button {...args} href="https://www.bigtreecms.org" target="_blank">
				External link (href)
			</Button>
		</div>
	),
};

/** A disabled control always renders as a plain `<button>`, never a link. */
export const Disabled: Story = {
	args: { variant: "primary", disabled: true, children: "Disabled" },
};

/** The full matrix — every variant × every size. */
export const Matrix: Story = {
	render: () => {
		const variants = ["primary", "secondary", "danger", "dangerGhost", "link"] as const;
		const sizes = ["sm", "md", "lg"] as const;

		return (
			<div className="flex flex-col gap-3">
				{variants.map((variant) => (
					<div key={variant} className="flex items-center gap-3">
						{sizes.map((size) => (
							<Button key={size} variant={variant} size={size}>
								{variant}/{size}
							</Button>
						))}
					</div>
				))}
			</div>
		);
	},
};
