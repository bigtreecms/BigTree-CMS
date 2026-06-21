import type { Meta, StoryObj } from "@storybook/react-vite";
import { FileKey } from "lucide-react";

import { UploadButton } from "./UploadButton";

/**
 * Styled button wrapping a hidden `<input type="file">`. Used by the Configure
 * screens to collect single credential files. The input resets after each pick
 * so the same file can be re-selected.
 */
const meta = {
	title: "UI/UploadButton",
	component: UploadButton,
	tags: ["autodocs"],
	args: { label: "Upload file", onSelect: () => {} },
} satisfies Meta<typeof UploadButton>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** Constrain the picker with `accept` and a contextual label/icon. */
export const ServiceAccountKey: Story = {
	args: {
		label: "Upload service-account key",
		accept: ".json",
		icon: <FileKey size={13} />,
	},
};

export const Disabled: Story = {
	args: { label: "Uploading…", disabled: true },
};
