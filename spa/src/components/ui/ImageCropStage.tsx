import Cropper from "react-easy-crop";

import type { ImageCropController } from "@/hooks/useImageCrop";

interface ImageCropStageProps {
	image: string;
	aspect?: number;
	objectFit?: "contain" | "cover" | "horizontal-cover" | "vertical-cover";
	controller: ImageCropController;
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
				image={image}
				crop={crop}
				zoom={zoom}
				aspect={aspect}
				onCropChange={setCrop}
				onZoomChange={setZoom}
				onCropComplete={onCropComplete}
				objectFit={objectFit}
			/>
		</div>
	);
};
