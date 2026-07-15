import Cropper from "react-easy-crop";

import type { ImageCropController } from "@/hooks/useImageCrop";

interface ImageCropStageProps {
	aspect?: number;
	controller: ImageCropController;
	image: string;
	objectFit?: "contain" | "cover" | "horizontal-cover" | "vertical-cover";
}

/**
 * The pan-zoom cropper viewport — `react-easy-crop` in a fixed 460px black stage.
 * Pair with `useImageCrop` for state; render your own zoom slider and submit logic.
 */
export const ImageCropStage = ({ image, aspect, objectFit, controller }: ImageCropStageProps) => {
	const { crop, zoom, setCrop, setZoom, onCropComplete } = controller;

	return (
		<div className="relative h-[460px] bg-black">
			<Cropper
				aspect={aspect}
				crop={crop}
				image={image}
				objectFit={objectFit}
				zoom={zoom}
				onCropChange={setCrop}
				onCropComplete={onCropComplete}
				onZoomChange={setZoom}
			/>
		</div>
	);
};
