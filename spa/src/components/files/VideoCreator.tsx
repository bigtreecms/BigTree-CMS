import { useEffect, useState } from "react";
import { Button } from "@/components/ui/Button";
import { SlideOver } from "@/components/ui/SlideOver";
import { Field } from "@/components/ui/Field";
import { TextInput } from "@/components/ui/TextInput";
import { resourcesApi, type ResourceDetail } from "@/api/endpoints/resources";
import { toast } from "@/lib/toast";
import { useToastMutation } from "@/hooks/useToastMutation";

interface VideoCreatorProps {
	open: boolean;
	onOpenChange: (open: boolean) => void;
	folderId: number;
	invalidateKey: readonly unknown[];
	/** Called with the new resource after creation, so the parent can open its detail panel. */
	onCreated?: (resource: ResourceDetail) => void;
}

const URL_HOST_HINT = /(youtu\.be|youtube\.com|vimeo\.com)/i;

/**
 * Slide-over for creating a "managed video" resource by URL — the same flow
 * the PHP admin exposes under /files/add/video/. The server fetches oembed
 * metadata and stores the thumbnail locally, returning the new resource. We
 * pass that back via onCreated so callers can drop the user straight into
 * the file-detail panel.
 */
export const VideoCreator = ({
	open,
	onOpenChange,
	folderId,
	invalidateKey,
	onCreated,
}: VideoCreatorProps) => {
	const [url, setUrl] = useState("");

	useEffect(() => {
		if (open) {
			setUrl("");
		}
	}, [open]);

	const createMutation = useToastMutation({
		mutationFn: () =>
			resourcesApi.createVideo({ url: url.trim(), folder: folderId || undefined }),
		invalidate: [[...invalidateKey]],
		errorMessage: "Could not add video",
		onSuccess: (resource) => {
			toast.success("Video added", { description: resource.name });
			onOpenChange(false);
			onCreated?.(resource);
		},
	});

	const trimmed = url.trim();
	const looksValid = trimmed.length > 0 && URL_HOST_HINT.test(trimmed);

	const submit = () => {
		if (!looksValid || createMutation.isPending) {
			return;
		}

		createMutation.mutate();
	};

	return (
		<SlideOver
			open={open}
			onOpenChange={onOpenChange}
			title="Add managed video"
			description="Paste a YouTube or Vimeo URL. We'll pull the title, description, and thumbnail."
			width="sm"
			footer={
				<div className="flex justify-end gap-2">
					<button
						type="button"
						className="rounded-md border border-border px-3 py-1.5 text-[12.5px] hover:bg-hover"
						onClick={() => onOpenChange(false)}
					>
						Cancel
					</button>
					<Button
						variant="primary"
						disabled={!looksValid}
						loading={createMutation.isPending}
						loadingLabel="Adding…"
						onClick={submit}
					>
						Add video
					</Button>
				</div>
			}
		>
			<Field label="Video URL">
				<TextInput
					autoFocus
					type="url"
					inputMode="url"
					value={url}
					onChange={(e) => setUrl(e.target.value)}
					onKeyDown={(e) => {
						if (e.key === "Enter") {
							e.preventDefault();
							submit();
						}
					}}
					placeholder="https://youtube.com/watch?v=… or https://vimeo.com/…"
				/>
				<p className="mt-1.5 text-[11.5px] text-text-3">
					Supported services: YouTube, Vimeo.
				</p>
			</Field>
		</SlideOver>
	);
};
