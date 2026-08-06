import type { Meta, StoryObj } from "@storybook/react-vite";

import { FieldComparison } from "./FieldComparison";

const meta: Meta<typeof FieldComparison> = {
	title: "Pending Changes/FieldComparison",
	component: FieldComparison,
	parameters: { layout: "padded" },
};

export default meta;

type Story = StoryObj<typeof FieldComparison>;

const PUBLISHED_COPY =
	"Our annual conference returns to Boston this September. Early bird tickets are " +
	"available until the end of July, and members save an additional 15% on every tier.";

const DRAFT_COPY =
	"Our annual conference returns to Chicago this September. Early bird tickets are " +
	"available until the end of August, and members save an additional 20% on every tier.";

export const Prose: Story = {
	args: {
		fieldType: "textarea",
		published: PUBLISHED_COPY,
		pending: DRAFT_COPY,
		pendingLabel: "Draft by Sam",
	},
};

export const HtmlField: Story = {
	args: {
		fieldType: "html",
		published:
			'<h2>Getting started</h2><p>Read the <a href="/docs/install">installation guide</a> first.</p>',
		pending:
			'<h2>Before you begin</h2><p>Read the <a href="/docs/requirements">requirements</a> first.</p>',
		pendingLabel: "Your draft",
	},
};

/** Only the link target moved, so the text view has nothing to report. */
export const HtmlMarkupOnlyChange: Story = {
	args: {
		fieldType: "html",
		published: '<p>Read the <a href="/docs/v1">guide</a>.</p>',
		pending: '<p>Read the <a href="/docs/v2">guide</a>.</p>',
		pendingLabel: "Your draft",
	},
};

export const MediaGallery: Story = {
	args: {
		fieldType: "media-gallery",
		published: [
			{ image: "{wwwroot}files/one.jpg", caption: "Opening keynote" },
			{ image: "{wwwroot}files/two.jpg", caption: "Workshop track" },
			{ image: "{wwwroot}files/three.jpg", caption: "Closing panel" },
			{ image: "{wwwroot}files/four.jpg", caption: "Evening reception" },
		],
		pending: [
			{ image: "{wwwroot}files/one.jpg", caption: "Opening keynote" },
			{ image: "{wwwroot}files/two.jpg", caption: "Workshop tracks" },
			{ image: "{wwwroot}files/three.jpg", caption: "Closing panel" },
			{ image: "{wwwroot}files/four.jpg", caption: "Evening reception" },
		],
		pendingLabel: "Draft by Sam",
	},
};

/** An opaque type keeps the original side-by-side columns. */
export const ImageColumns: Story = {
	args: {
		fieldType: "image",
		published: "{wwwroot}files/published.jpg",
		pending: "{wwwroot}files/draft.jpg",
		pendingLabel: "Your draft",
	},
};

/** A never-published draft has no baseline, so it keeps the columns too. */
export const NewDraft: Story = {
	args: {
		fieldType: "textarea",
		isNew: true,
		published: null,
		pending: DRAFT_COPY,
		pendingLabel: "Your draft",
	},
};
