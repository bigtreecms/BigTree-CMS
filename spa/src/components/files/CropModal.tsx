import { useEffect, useState } from "react";
import { useToastMutation } from "@/hooks/useToastMutation";

import { Button } from "@/components/ui/Button";
import { ImageCropStage } from "@/components/ui/ImageCropStage";
import { Modal } from "@/components/ui/Modal";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { TextInput } from "@/components/ui/TextInput";
import { resourcesApi, type ResourceDetail } from "@/api/endpoints/resources";
import { useImageCrop } from "@/hooks/useImageCrop";

interface CropModalProps {
	onOpenChange: (open: boolean) => void;
	open: boolean;
	resource: ResourceDetail;
}

/**
 * Image-cropping dialog wired to POST /resources/{id}/crop. Wraps
 * react-easy-crop to give the user pan + zoom + (optional) aspect lock,
 * then POSTs the resulting pixel rect plus the chosen output dimensions
 * to the API. New crops are appended server-side and the resource detail
 * query is invalidated so they show up in the SlideOver's Crops list.
 */
export const CropModal = ({ open, onOpenChange, resource }: CropModalProps) => {
	const imageCrop = useImageCrop();
	const { reset: resetCrop } = imageCrop;

	const [aspect, setAspect] = useState<number | undefined>(undefined);
	const [targetWidth, setTargetWidth] = useState<string>("");
	const [targetHeight, setTargetHeight] = useState<string>("");
	const [prefix, setPrefix] = useState("crop-");

	// Reset state every time the dialog opens so each crop session starts fresh.
	useEffect(() => {
		if (open) {
			resetCrop();
			setAspect(undefined);
			setTargetWidth("");
			setTargetHeight("");
			setPrefix("crop-");
		}
	}, [open, resetCrop]);

	const cropMutation = useToastMutation({
		mutationFn: () => {
			if (!imageCrop.areaPixels) {
				throw new Error("no crop area");
			}

			return resourcesApi.crop(resource.id, {
				x: Math.round(imageCrop.areaPixels.x),
				y: Math.round(imageCrop.areaPixels.y),
				width: Math.round(imageCrop.areaPixels.width),
				height: Math.round(imageCrop.areaPixels.height),
				target_width: targetWidth ? parseInt(targetWidth, 10) : undefined,
				target_height: targetHeight ? parseInt(targetHeight, 10) : undefined,
				prefix: prefix.trim() || undefined,
			});
		},
		invalidate: [["resources", "detail", resource.id]],
		successMessage: "Crop saved",
		errorMessage: "Could not crop image",
		onSuccess: () => {
			onOpenChange(false);
		},
	});

	const validCrop =
		!!imageCrop.areaPixels && imageCrop.areaPixels.width > 0 && imageCrop.areaPixels.height > 0;

	return (
		<Modal
			showClose
			description="Drag inside the image to position the crop. Scroll or use the slider to zoom."
			footer={
				<div className="flex justify-end gap-2">
					<Button variant="secondary" onClick={() => onOpenChange(false)}>
						Cancel
					</Button>
					<Button
						disabled={!validCrop}
						loading={cropMutation.isPending}
						loadingLabel="Saving…"
						variant="primary"
						onClick={() => cropMutation.mutate()}
					>
						Save crop
					</Button>
				</div>
			}
			layout="bars"
			open={open}
			scrim="dark"
			size="xl"
			title={`Crop "${resource.name}"`}
			onOpenChange={onOpenChange}
		>
			<div className="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_260px]">
				<ImageCropStage aspect={aspect} controller={imageCrop} image={resource.file} />

				<aside className="space-y-4 border-t border-border bg-surface-2 p-4 md:border-l md:border-t-0">
					<div aria-label="Aspect ratio" role="group">
						<SectionLabel className="mb-1 block" size="sm">
							Aspect ratio
						</SectionLabel>
						<div className="flex flex-wrap gap-1">
							{(
								[
									["Free", undefined],
									["1:1", 1],
									["4:3", 4 / 3],
									["3:2", 3 / 2],
									["16:9", 16 / 9],
								] as const
							).map(([label, value]) => (
								<button
									className={`rounded-md border px-2 py-1 text-[11.5px] ${
										aspect === value
											? "border-accent bg-accent-soft text-accent"
											: "border-border bg-surface text-text-2 hover:bg-hover"
									}`}
									key={label}
									type="button"
									onClick={() => setAspect(value)}
								>
									{label}
								</button>
							))}
						</div>
					</div>

					<div>
						<SectionLabel
							as="label"
							className="mb-1 block"
							htmlFor="crop-zoom"
							size="sm"
						>
							Zoom
						</SectionLabel>
						<input
							className="w-full accent-accent"
							id="crop-zoom"
							max={4}
							min={1}
							step={0.05}
							type="range"
							value={imageCrop.zoom}
							onChange={(e) => imageCrop.setZoom(parseFloat(e.target.value))}
						/>
					</div>

					<div className="grid grid-cols-2 gap-2">
						<label className="block">
							<SectionLabel className="mb-1 block" size="sm">
								Width
							</SectionLabel>
							<TextInput
								compact
								className="w-full"
								inputMode="numeric"
								min={1}
								placeholder={
									imageCrop.areaPixels
										? String(Math.round(imageCrop.areaPixels.width))
										: ""
								}
								type="number"
								value={targetWidth}
								onChange={(e) => setTargetWidth(e.target.value)}
							/>
						</label>
						<label className="block">
							<SectionLabel className="mb-1 block" size="sm">
								Height
							</SectionLabel>
							<TextInput
								compact
								className="w-full"
								inputMode="numeric"
								min={1}
								placeholder={
									imageCrop.areaPixels
										? String(Math.round(imageCrop.areaPixels.height))
										: ""
								}
								type="number"
								value={targetHeight}
								onChange={(e) => setTargetHeight(e.target.value)}
							/>
						</label>
					</div>
					<p className="text-[11px] text-text-3">
						Leave blank to keep the source-pixel dimensions of the crop.
					</p>

					<label className="block">
						<SectionLabel className="mb-1 block" size="sm">
							Filename prefix
						</SectionLabel>
						<TextInput
							compact
							className="w-full"
							maxLength={64}
							value={prefix}
							onChange={(e) => setPrefix(e.target.value)}
						/>
					</label>
				</aside>
			</div>
		</Modal>
	);
};
