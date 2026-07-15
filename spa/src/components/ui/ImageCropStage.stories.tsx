import type { Meta, StoryObj } from "@storybook/react-vite";

import { useImageCrop } from "@/hooks/useImageCrop";
import { ImageCropStage } from "./ImageCropStage";

/**
 * The shared pan-zoom cropper viewport backed by `react-easy-crop`.
 * Always pair with `useImageCrop` — the hook owns all crop/zoom state.
 */
const meta = {
	title: "UI/ImageCropStage",
	component: ImageCropStage,
	tags: ["autodocs"],
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 640 }}>
				<Story />
			</div>
		),
	],
	render: ({ image, aspect, objectFit }) => {
		const controller = useImageCrop();

		return (
			<ImageCropStage
				aspect={aspect}
				controller={controller}
				image={image}
				objectFit={objectFit}
			/>
		);
	},
	args: {
		image: "https://picsum.photos/seed/crop/800/600",
		// controller is supplied by render above
		controller: undefined as never,
	},
} satisfies Meta<typeof ImageCropStage>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Free-form crop with no aspect lock. */
export const FreeForm: Story = {};

/** Fixed 16:9 aspect ratio. */
export const SixteenByNine: Story = {
	args: { aspect: 16 / 9 },
};

/** Square crop with contain fit. */
export const Square: Story = {
	args: { aspect: 1, objectFit: "contain" },
};
