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
			<Button {...args} size="sm" variant="primary">
				Small
			</Button>
			<Button {...args} size="md" variant="primary">
				Medium
			</Button>
			<Button {...args} size="lg" variant="primary">
				Large
			</Button>
		</div>
	),
};

/** Buttons can carry a leading lucide icon (size 13 to match the type). */
export const WithIcon: Story = {
	render: (args) => (
		<div className="flex flex-wrap items-center gap-3">
			<Button {...args} icon={<Save size={13} />} variant="primary">
				Save
			</Button>
			<Button {...args} icon={<Plus size={13} />} variant="secondary">
				Add new
			</Button>
			<Button {...args} icon={<Trash2 size={13} />} variant="dangerGhost">
				Delete
			</Button>
		</div>
	),
};

/** `to` renders a router `<Link>`, `href` renders an `<a>`; both look identical. */
export const AsLink: Story = {
	render: (args) => (
		<div className="flex flex-wrap items-center gap-3">
			<Button {...args} to="/pages" variant="primary">
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

/**
 * `loading` disables the button, swaps the icon for a spinner, and shows
 * `loadingLabel` in place of the children — the standard mutation-pending state.
 */
export const Loading: Story = {
	render: (args) => (
		<div className="flex flex-wrap items-center gap-3">
			<Button
				{...args}
				loading
				icon={<Save size={13} />}
				loadingLabel="Saving…"
				variant="primary"
			>
				Save
			</Button>
			<Button {...args} loading variant="secondary">
				No label swap
			</Button>
		</div>
	),
};

/** The full matrix — every variant × every size. */
export const Matrix: Story = {
	render: () => {
		const variants = ["primary", "secondary", "danger", "dangerGhost", "link"] as const;
		const sizes = ["sm", "md", "lg"] as const;

		return (
			<div className="flex flex-col gap-3">
				{variants.map((variant) => (
					<div className="flex items-center gap-3" key={variant}>
						{sizes.map((size) => (
							<Button key={size} size={size} variant={variant}>
								{variant}/{size}
							</Button>
						))}
					</div>
				))}
			</div>
		);
	},
};
