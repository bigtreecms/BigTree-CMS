import { useEffect, useState } from "react";

import { Button } from "@/components/ui/Button";
import { ImageCropStage } from "@/components/ui/ImageCropStage";
import { Modal } from "@/components/ui/Modal";
import { imagesApi, type PendingCrop } from "@/api/endpoints/images";
import { useImageCrop } from "@/hooks/useImageCrop";
import { expandImageUrl } from "@/lib/imageUrl";
import { toast } from "@/lib/toast";

interface FieldCropModalProps {
	open: boolean;
	/** Stored original path the crops are drawn from (raw `{wwwroot}` path). */
	file: string;
	/** Crops the server couldn't auto-generate; walked one at a time. */
	crops: PendingCrop[];
	/** All crops finalized. */
	onComplete: () => void;
	/** User dismissed before finishing (original stays, crops are incomplete). */
	onCancel: () => void;
}

/**
 * Sequential cropper for an image field's manual crops — the SPA equivalent of
 * the legacy admin's crop screen (`auto-modules/forms/crop.php`). For each
 * pending crop the user positions a fixed-aspect selection; on confirm we POST
 * the source-pixel rect to `/images/crop`, which generates the crop plus any
 * nested thumbs / center crops, then advance to the next one.
 */
export const FieldCropModal = ({
	open,
	file,
	crops,
	onComplete,
	onCancel,
}: FieldCropModalProps) => {
	const [index, setIndex] = useState(0);
	const [busy, setBusy] = useState(false);
	const imageCrop = useImageCrop();
	const { reset: resetCrop } = imageCrop;

	const imageSrc = expandImageUrl(file);
	const current: PendingCrop | undefined = crops[index];

	// Opening (or swapping the source image) starts over at the first crop with
	// a fresh transform. Advancing between crops is handled in finalizeCurrent so
	// we don't chain "index changed" → "reset transform" across an extra render.
	useEffect(() => {
		if (open) {
			setIndex(0);
			resetCrop();
		}
	}, [open, file, resetCrop]);

	if (!current) {
		return null;
	}

	const aspect = current.width / current.height;
	const requiredFactor = current.retina ? 2 : 1;
	const minWidth = current.width * requiredFactor;
	const minHeight = current.height * requiredFactor;
	const tooSmall =
		!!imageCrop.areaPixels &&
		(imageCrop.areaPixels.width < minWidth || imageCrop.areaPixels.height < minHeight);
	const validCrop =
		!!imageCrop.areaPixels &&
		imageCrop.areaPixels.width > 0 &&
		imageCrop.areaPixels.height > 0 &&
		!tooSmall;
	const isLast = index === crops.length - 1;

	const finalizeCurrent = async () => {
		if (!imageCrop.areaPixels || !validCrop || busy) {
			return;
		}

		setBusy(true);

		try {
			await imagesApi.crop({
				file,
				x: Math.round(imageCrop.areaPixels.x),
				y: Math.round(imageCrop.areaPixels.y),
				width: Math.round(imageCrop.areaPixels.width),
				height: Math.round(imageCrop.areaPixels.height),
				target_width: current.width,
				target_height: current.height,
				prefix: current.prefix,
				name: current.name,
				directory: current.directory,
				retina: current.retina,
				grayscale: current.grayscale,
				thumbs: current.thumbs,
				center_crops: current.center_crops,
			});

			if (isLast) {
				onComplete();
			} else {
				setIndex((i) => i + 1);
				resetCrop();
			}
		} catch {
			toast.error("Could not generate the crop. Please try again.");
		} finally {
			setBusy(false);
		}
	};

	return (
		<Modal
			open={open}
			onOpenChange={(next) => {
				if (!next && !busy) {
					onCancel();
				}
			}}
			title={crops.length > 1 ? `Crop image ${index + 1} of ${crops.length}` : "Crop image"}
			description={`Position the ${current.width}×${current.height}${current.retina ? " (retina)" : ""} crop. Drag to move, scroll or use the slider to zoom.`}
			size="xl"
			layout="bars"
			scrim="dark"
			footer={
				<div className="flex flex-wrap items-center gap-x-3 gap-y-2">
					<label htmlFor="field-crop-zoom" className="text-[11.5px] text-text-3">
						Zoom
					</label>
					<input
						id="field-crop-zoom"
						type="range"
						min={1}
						max={4}
						step={0.05}
						value={imageCrop.zoom}
						onChange={(e) => imageCrop.setZoom(parseFloat(e.target.value))}
						className="w-32 max-w-full accent-accent sm:w-40"
					/>

					{tooSmall && (
						<span className="w-full text-[11.5px] text-warn sm:w-auto">
							Selection is below the required {minWidth}×{minHeight}px.
						</span>
					)}

					<div className="ml-auto flex items-center gap-2">
						<Button variant="secondary" onClick={onCancel} disabled={busy}>
							Cancel
						</Button>
						<Button
							variant="primary"
							onClick={finalizeCurrent}
							disabled={!validCrop}
							loading={busy}
							loadingLabel="Cropping…"
						>
							{isLast ? "Finish" : "Crop & continue"}
						</Button>
					</div>
				</div>
			}
		>
			<ImageCropStage
				image={imageSrc}
				aspect={aspect}
				objectFit="contain"
				controller={imageCrop}
			/>
		</Modal>
	);
};
