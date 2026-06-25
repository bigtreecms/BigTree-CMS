import type { Meta, StoryObj } from "@storybook/react-vite";

import { PageHead } from "./PageHead";
import { PageContainer } from "./PageContainer";

/**
 * Centered, fixed-width page content column (`shell/PageContainer`). Every
 * screen wraps its body in this so the `mx-auto px-6 py-4` gutter and the four
 * content widths (`wide`/`medium`/`narrow`/`xwide`) are a single layout
 * contract instead of a copy-pasted Tailwind string per page. The grey bar
 * marks the column edges so the width/gutter read.
 */
const meta = {
	title: "Shell/PageContainer",
	component: PageContainer,
	tags: ["autodocs"],
	decorators: [
		(Story) => (
			<div className="bg-surface-2">
				<Story />
			</div>
		),
	],
	args: {
		children: (
			<>
				<PageHead title="Page title" sub="A subtitle describing the screen" />
				<div className="rounded-xl border border-border bg-surface p-4 text-[13px] text-text-3">
					Page content sits inside the column.
				</div>
			</>
		),
	},
} satisfies Meta<typeof PageContainer>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Wide list/table screens (`max-w-screen-2xl`). */
export const Wide: Story = { args: { width: "wide" } };

/** Medium edit/detail screens (`max-w-5xl`). */
export const Medium: Story = { args: { width: "medium" } };

/** Narrow single-column forms (`max-w-3xl`). */
export const Narrow: Story = { args: { width: "narrow" } };

/** Extra-wide screens (`max-w-7xl`). */
export const XWide: Story = { args: { width: "xwide" } };
