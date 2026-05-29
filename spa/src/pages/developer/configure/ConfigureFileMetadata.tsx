import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Save } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { ResourceDesigner, type ResourceEntry } from "@/components/developer/ResourceDesigner";

import {
	configureApi,
	type FileMetadataConfig,
	type FileMetadataField,
} from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

const BUCKETS: Array<{ id: keyof FileMetadataConfig; label: string; hint: string }> = [
	{ id: "file", label: "Generic files", hint: "Asked on every non-image, non-video upload." },
	{ id: "image", label: "Images", hint: "Asked on every image upload." },
	{ id: "video", label: "Videos", hint: "Asked on every video upload." },
];

/** Adapt a stored file-metadata field into the designer's generic entry shape. */
const toResource = (field: FileMetadataField): ResourceEntry => ({
	id: field.id,
	title: field.title,
	subtitle: field.subtitle,
	type: field.type,
	settings: field.settings,
});

/** Normalize a designer entry back into the file-metadata storage shape. */
const toField = (entry: ResourceEntry): FileMetadataField => ({
	id: String(entry.id ?? ""),
	title: entry.title ?? "",
	subtitle: entry.subtitle ?? "",
	type: entry.type || "text",
	settings:
		entry.settings && !Array.isArray(entry.settings)
			? (entry.settings as Record<string, unknown>)
			: {},
});

export const ConfigureFileMetadata = () => {
	const queryClient = useQueryClient();
	const detailQ = useQuery({
		queryKey: ["configure", "file-metadata"],
		queryFn: () => configureApi.fileMetadata.get(),
	});

	const [draft, setDraft] = useState<FileMetadataConfig>({ file: [], image: [], video: [] });
	const [generalError, setGeneralError] = useState<string | null>(null);

	useEffect(() => {
		if (detailQ.data) {
			setDraft({
				file: detailQ.data.file.map((r) => ({ ...r })),
				image: detailQ.data.image.map((r) => ({ ...r })),
				video: detailQ.data.video.map((r) => ({ ...r })),
			});
		}
	}, [detailQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: FileMetadataConfig) => configureApi.fileMetadata.update(next),
		onSuccess: (fresh) => {
			queryClient.setQueryData(["configure", "file-metadata"], fresh);
			toast.success("File metadata saved");
			setGeneralError(null);
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message
					? err.message
					: "Could not save file metadata";
			setGeneralError(msg);
			toast.error(msg);
		},
	});

	const setBucket = (bucket: keyof FileMetadataConfig, entries: ResourceEntry[]) => {
		setDraft((prev) => ({ ...prev, [bucket]: entries.map(toField) }));
	};

	return (
		<ConfigureLayout
			title="File metadata"
			sub="Custom fields collected on file / image / video uploads, surfaced on the Files screen and in the upload field type."
			actions={
				<button
					type="button"
					onClick={() => saveMutation.mutate(draft)}
					disabled={saveMutation.isPending}
					className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover disabled:opacity-60"
				>
					<Save size={13} />
					{saveMutation.isPending ? "Saving…" : "Save"}
				</button>
			}
		>
			{detailQ.isLoading && <p className="text-[12.5px] text-text-3">Loading…</p>}

			{detailQ.error && <ErrorPanel error={detailQ.error} />}

			{generalError && <ErrorPanel error={new Error(generalError)} />}

			<div className="space-y-4">
				{BUCKETS.map((b) => (
					<div key={b.id} className="rounded-xl border border-border bg-surface p-4">
						<div className="mb-3">
							<div className="text-[13px] font-semibold text-text">{b.label}</div>
							<div className="text-[11.5px] text-text-3">{b.hint}</div>
						</div>

						<ResourceDesigner
							resources={draft[b.id].map(toResource)}
							onChange={(entries) => setBucket(b.id, entries)}
							keyField="id"
							useCase="settings"
						/>
					</div>
				))}
			</div>
		</ConfigureLayout>
	);
};
