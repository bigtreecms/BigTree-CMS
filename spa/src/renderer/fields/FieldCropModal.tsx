import { useCallback, useEffect, useState } from "react";
import * as Dialog from "@radix-ui/react-dialog";
import Cropper, { type Area } from "react-easy-crop";

import { imagesApi, type PendingCrop } from "@/api/endpoints/images";
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
	const [crop, setCrop] = useState({ x: 0, y: 0 });
	const [zoom, setZoom] = useState(1);
	const [areaPixels, setAreaPixels] = useState<Area | null>(null);
	const [busy, setBusy] = useState(false);

	const imageSrc = expandImageUrl(file);
	const current: PendingCrop | undefined = crops[index];

	// Reset transform state whenever the dialog opens or we move to a new crop.
	useEffect(() => {
		if (open) {
			setCrop({ x: 0, y: 0 });
			setZoom(1);
			setAreaPixels(null);
		}
	}, [open, index]);

	// Starting over (new image / re-open) resets to the first crop.
	useEffect(() => {
		if (open) {
			setIndex(0);
		}
	}, [open, file]);

	const onCropComplete = useCallback((_: Area, areaPx: Area) => {
		setAreaPixels(areaPx);
	}, []);

	if (!current) {
		return null;
	}

	const aspect = current.width / current.height;
	const requiredFactor = current.retina ? 2 : 1;
	const minWidth = current.width * requiredFactor;
	const minHeight = current.height * requiredFactor;
	const tooSmall = !!areaPixels && (areaPixels.width < minWidth || areaPixels.height < minHeight);
	const validCrop = !!areaPixels && areaPixels.width > 0 && areaPixels.height > 0 && !tooSmall;
	const isLast = index === crops.length - 1;

	const finalizeCurrent = async () => {
		if (!areaPixels || !validCrop || busy) {
			return;
		}
		setBusy(true);

		try {
			await imagesApi.crop({
				file,
				x: Math.round(areaPixels.x),
				y: Math.round(areaPixels.y),
				width: Math.round(areaPixels.width),
				height: Math.round(areaPixels.height),
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
			}
		} catch {
			toast.error("Could not generate the crop. Please try again.");
		} finally {
			setBusy(false);
		}
	};

	return (
		<Dialog.Root
			open={open}
			onOpenChange={(next) => {
				if (!next && !busy) {
					onCancel();
				}
			}}
		>
			<Dialog.Portal>
				<Dialog.Overlay className="fixed inset-0 z-50 bg-black/60 backdrop-blur-[2px]" />
				<Dialog.Content className="fixed left-1/2 top-1/2 z-50 w-[min(900px,95vw)] -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-xl border border-border bg-surface shadow-lg focus:outline-none">
					<div className="border-b border-border bg-surface-2 px-5 py-3">
						<Dialog.Title className="text-[14px] font-semibold tracking-[-0.01em] text-text">
							{crops.length > 1
								? `Crop image ${index + 1} of ${crops.length}`
								: "Crop image"}
						</Dialog.Title>
						<Dialog.Description className="mt-0.5 text-[12px] text-text-3">
							Position the {current.width}×{current.height}
							{current.retina ? " (retina)" : ""} crop. Drag to move, scroll or use
							the slider to zoom.
						</Dialog.Description>
					</div>

					<div className="relative h-[460px] bg-black">
						<Cropper
							image={imageSrc}
							crop={crop}
							zoom={zoom}
							aspect={aspect}
							onCropChange={setCrop}
							onZoomChange={setZoom}
							onCropComplete={onCropComplete}
							objectFit="contain"
						/>
					</div>

					<div className="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-border bg-surface-2 px-5 py-3">
						<label htmlFor="field-crop-zoom" className="text-[11.5px] text-text-3">
							Zoom
						</label>
						<input
							id="field-crop-zoom"
							type="range"
							min={1}
							max={4}
							step={0.05}
							value={zoom}
							onChange={(e) => setZoom(parseFloat(e.target.value))}
							className="w-32 max-w-full accent-accent sm:w-40"
						/>

						{tooSmall && (
							<span className="w-full text-[11.5px] text-warn sm:w-auto">
								Selection is below the required {minWidth}×{minHeight}px.
							</span>
						)}

						<div className="ml-auto flex items-center gap-2">
							<button
								type="button"
								className="rounded-md border border-border px-3 py-1.5 text-[12.5px] hover:bg-hover disabled:opacity-50"
								onClick={onCancel}
								disabled={busy}
							>
								Cancel
							</button>
							<button
								type="button"
								className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-60 hover:bg-accent-hover"
								onClick={finalizeCurrent}
								disabled={!validCrop || busy}
							>
								{busy ? "Cropping…" : isLast ? "Finish" : "Crop & continue"}
							</button>
						</div>
					</div>
				</Dialog.Content>
			</Dialog.Portal>
		</Dialog.Root>
	);
};
