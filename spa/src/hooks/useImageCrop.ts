import { useCallback, useState } from "react";

import type { Area } from "react-easy-crop";

export interface ImageCropController {
	areaPixels: Area | null;
	crop: { x: number; y: number };
	onCropComplete: (_: Area, areaPx: Area) => void;
	reset: () => void;
	setCrop: (crop: { x: number; y: number }) => void;
	setZoom: (zoom: number) => void;
	zoom: number;
}

/**
 * Owns the crop/zoom/areaPixels state machine for react-easy-crop.
 * Pass the returned controller to `ImageCropStage`; read `areaPixels` in
 * the submit handler to get the selected pixel rect.
 */
export const useImageCrop = (): ImageCropController => {
	const [crop, setCrop] = useState({ x: 0, y: 0 });
	const [zoom, setZoom] = useState(1);
	const [areaPixels, setAreaPixels] = useState<Area | null>(null);

	const onCropComplete = useCallback((_: Area, areaPx: Area) => {
		setAreaPixels(areaPx);
	}, []);

	const reset = useCallback(() => {
		setCrop({ x: 0, y: 0 });
		setZoom(1);
		setAreaPixels(null);
	}, []);

	return { crop, zoom, areaPixels, setCrop, setZoom, onCropComplete, reset };
};
