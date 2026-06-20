import { useCallback, useEffect, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import * as Dialog from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import Cropper, { type Area } from "react-easy-crop";

import { Button } from "@/components/ui/Button";
import { resourcesApi, type ResourceDetail } from "@/api/endpoints/resources";
import { toast } from "@/lib/toast";

interface CropModalProps {
	open: boolean;
	onOpenChange: (open: boolean) => void;
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
	const queryClient = useQueryClient();

	const [crop, setCrop] = useState({ x: 0, y: 0 });
	const [zoom, setZoom] = useState(1);
	const [aspect, setAspect] = useState<number | undefined>(undefined);
	const [areaPixels, setAreaPixels] = useState<Area | null>(null);
	const [targetWidth, setTargetWidth] = useState<string>("");
	const [targetHeight, setTargetHeight] = useState<string>("");
	const [prefix, setPrefix] = useState("crop-");

	// Reset state every time the dialog opens so each crop session starts fresh.
	useEffect(() => {
		if (open) {
			setCrop({ x: 0, y: 0 });
			setZoom(1);
			setAspect(undefined);
			setAreaPixels(null);
			setTargetWidth("");
			setTargetHeight("");
			setPrefix("crop-");
		}
	}, [open]);

	const onCropComplete = useCallback((_: Area, areaPx: Area) => {
		setAreaPixels(areaPx);
	}, []);

	const cropMutation = useMutation({
		mutationFn: () => {
			if (!areaPixels) {
				throw new Error("no crop area");
			}

			return resourcesApi.crop(resource.id, {
				x: Math.round(areaPixels.x),
				y: Math.round(areaPixels.y),
				width: Math.round(areaPixels.width),
				height: Math.round(areaPixels.height),
				target_width: targetWidth ? parseInt(targetWidth, 10) : undefined,
				target_height: targetHeight ? parseInt(targetHeight, 10) : undefined,
				prefix: prefix.trim() || undefined,
			});
		},
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["resources", "detail", resource.id],
			});
			toast.success("Crop saved");
			onOpenChange(false);
		},
		onError: () => {
			toast.error("Could not crop image");
		},
	});

	const validCrop = !!areaPixels && areaPixels.width > 0 && areaPixels.height > 0;

	return (
		<Dialog.Root open={open} onOpenChange={onOpenChange}>
			<Dialog.Portal>
				<Dialog.Overlay className="fixed inset-0 z-50 bg-black/60 backdrop-blur-[2px]" />
				<Dialog.Content className="fixed left-1/2 top-1/2 z-50 w-[min(960px,95vw)] -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-xl border border-border bg-surface shadow-lg focus:outline-none">
					<div className="flex items-center justify-between border-b border-border bg-surface-2 px-5 py-3">
						<div>
							<Dialog.Title className="text-[14px] font-semibold tracking-[-0.01em] text-text">
								Crop “{resource.name}”
							</Dialog.Title>
							<Dialog.Description className="mt-0.5 text-[12px] text-text-3">
								Drag inside the image to position the crop. Scroll or use the slider
								to zoom.
							</Dialog.Description>
						</div>

						<Dialog.Close
							className="rounded-md p-1 text-text-3 hover:bg-hover hover:text-text"
							aria-label="Close"
						>
							<X size={16} />
						</Dialog.Close>
					</div>

					<div className="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_260px]">
						<div className="relative h-[460px] bg-black">
							<Cropper
								image={resource.file}
								crop={crop}
								zoom={zoom}
								aspect={aspect}
								onCropChange={setCrop}
								onZoomChange={setZoom}
								onCropComplete={onCropComplete}
							/>
						</div>

						<aside className="space-y-4 border-t border-border bg-surface-2 px-4 py-4 md:border-l md:border-t-0">
							<div>
								<label className="mb-1 block text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
									Aspect ratio
								</label>
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
											key={label}
											type="button"
											onClick={() => setAspect(value)}
											className={`rounded-md border px-2 py-1 text-[11.5px] ${
												aspect === value
													? "border-accent bg-accent-soft text-accent"
													: "border-border bg-surface text-text-2 hover:bg-hover"
											}`}
										>
											{label}
										</button>
									))}
								</div>
							</div>

							<div>
								<label
									htmlFor="crop-zoom"
									className="mb-1 block text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3"
								>
									Zoom
								</label>
								<input
									id="crop-zoom"
									type="range"
									min={1}
									max={4}
									step={0.05}
									value={zoom}
									onChange={(e) => setZoom(parseFloat(e.target.value))}
									className="w-full accent-accent"
								/>
							</div>

							<div className="grid grid-cols-2 gap-2">
								<label className="block">
									<span className="mb-1 block text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
										Width
									</span>
									<input
										type="number"
										min={1}
										inputMode="numeric"
										className="w-full rounded-md border border-border bg-surface px-2 py-1 text-[12.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
										value={targetWidth}
										onChange={(e) => setTargetWidth(e.target.value)}
										placeholder={
											areaPixels ? String(Math.round(areaPixels.width)) : ""
										}
									/>
								</label>
								<label className="block">
									<span className="mb-1 block text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
										Height
									</span>
									<input
										type="number"
										min={1}
										inputMode="numeric"
										className="w-full rounded-md border border-border bg-surface px-2 py-1 text-[12.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
										value={targetHeight}
										onChange={(e) => setTargetHeight(e.target.value)}
										placeholder={
											areaPixels ? String(Math.round(areaPixels.height)) : ""
										}
									/>
								</label>
							</div>
							<p className="text-[11px] text-text-3">
								Leave blank to keep the source-pixel dimensions of the crop.
							</p>

							<label className="block">
								<span className="mb-1 block text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
									Filename prefix
								</span>
								<input
									className="w-full rounded-md border border-border bg-surface px-2 py-1 text-[12.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
									value={prefix}
									onChange={(e) => setPrefix(e.target.value)}
									maxLength={64}
								/>
							</label>
						</aside>
					</div>

					<div className="flex justify-end gap-2 border-t border-border bg-surface-2 px-5 py-3">
						<button
							type="button"
							className="rounded-md border border-border px-3 py-1.5 text-[12.5px] hover:bg-hover"
							onClick={() => onOpenChange(false)}
						>
							Cancel
						</button>
						<Button
							variant="primary"
							disabled={!validCrop || cropMutation.isPending}
							onClick={() => cropMutation.mutate()}
						>
							{cropMutation.isPending ? "Saving…" : "Save crop"}
						</Button>
					</div>
				</Dialog.Content>
			</Dialog.Portal>
		</Dialog.Root>
	);
};
