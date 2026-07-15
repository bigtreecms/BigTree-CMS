import { useEffect, useState } from "react";
import { Button } from "@/components/ui/Button";
import { SlideOver } from "@/components/ui/SlideOver";
import { Field } from "@/components/ui/Field";
import { TextInput } from "@/components/ui/TextInput";
import { resourcesApi, type ResourceDetail } from "@/api/endpoints/resources";
import { toast } from "@/lib/toast";
import { useToastMutation } from "@/hooks/useToastMutation";

interface VideoCreatorProps {
	folderId: number;
	invalidateKey: readonly unknown[];
	/** Called with the new resource after creation, so the parent can open its detail panel. */
	onCreated?: (resource: ResourceDetail) => void;
	onOpenChange: (open: boolean) => void;
	open: boolean;
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
			description="Paste a YouTube or Vimeo URL. We'll pull the title, description, and thumbnail."
			footer={
				<div className="flex justify-end gap-2">
					<Button variant="secondary" onClick={() => onOpenChange(false)}>
						Cancel
					</Button>
					<Button
						disabled={!looksValid}
						loading={createMutation.isPending}
						loadingLabel="Adding…"
						variant="primary"
						onClick={submit}
					>
						Add video
					</Button>
				</div>
			}
			open={open}
			title="Add managed video"
			width="sm"
			onOpenChange={onOpenChange}
		>
			<Field label="Video URL">
				<TextInput
					autoFocus
					inputMode="url"
					placeholder="https://youtube.com/watch?v=… or https://vimeo.com/…"
					type="url"
					value={url}
					onChange={(e) => setUrl(e.target.value)}
					onKeyDown={(e) => {
						if (e.key === "Enter") {
							e.preventDefault();
							submit();
						}
					}}
				/>
				<p className="mt-1.5 text-[11.5px] text-text-3">
					Supported services: YouTube, Vimeo.
				</p>
			</Field>
		</SlideOver>
	);
};
