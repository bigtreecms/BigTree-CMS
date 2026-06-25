import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Save } from "lucide-react";

import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { LoadingText } from "@/components/ui/LoadingText";
import { ResourceDesigner, type ResourceEntry } from "@/components/developer/ResourceDesigner";
import { useResourceSettingsValidation } from "@/components/developer/field-settings/useResourceSettingsValidation";

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
	const [settingsErrors, setSettingsErrors] = useState<
		Record<keyof FileMetadataConfig, Record<number, Record<string, string>>>
	>({ file: {}, image: {}, video: {} });

	// Settings errors nest bucket → resource index → field; flatten so the
	// scroll-to-first-error hook can detect any error this submit.
	const flatSettingsErrors = useMemo(
		() =>
			Object.values(settingsErrors).reduce<Record<string, string>>(
				(acc, byIndex) => Object.assign(acc, ...Object.values(byIndex)),
				{}
			),
		[settingsErrors]
	);

	useScrollToFirstError(flatSettingsErrors);

	// One memoized resource array + validator per bucket (hooks must be called
	// unconditionally, so they can't live inside the BUCKETS map below).
	const fileResources = useMemo(() => draft.file.map(toResource), [draft.file]);
	const imageResources = useMemo(() => draft.image.map(toResource), [draft.image]);
	const videoResources = useMemo(() => draft.video.map(toResource), [draft.video]);

	const resourcesByBucket: Record<keyof FileMetadataConfig, ResourceEntry[]> = {
		file: fileResources,
		image: imageResources,
		video: videoResources,
	};

	const validators: Record<
		keyof FileMetadataConfig,
		{ validate: () => Record<number, Record<string, string>> }
	> = {
		file: useResourceSettingsValidation(fileResources, "settings"),
		image: useResourceSettingsValidation(imageResources, "settings"),
		video: useResourceSettingsValidation(videoResources, "settings"),
	};

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

	const handleSave = () => {
		const next = {
			file: validators.file.validate(),
			image: validators.image.validate(),
			video: validators.video.validate(),
		};

		if (BUCKETS.some((b) => Object.keys(next[b.id]).length > 0)) {
			setSettingsErrors(next);
			setGeneralError("Please fill in the required fields.");

			return;
		}

		setSettingsErrors({ file: {}, image: {}, video: {} });
		setGeneralError(null);
		saveMutation.mutate(draft);
	};

	return (
		<ConfigureLayout
			title="File metadata"
			sub="Custom fields collected on file / image / video uploads, surfaced on the Files screen and in the upload field type."
			actions={
				<Button
					variant="primary"
					icon={<Save size={13} />}
					onClick={handleSave}
					disabled={saveMutation.isPending}
				>
					{saveMutation.isPending ? "Saving…" : "Save"}
				</Button>
			}
		>
			{detailQ.isLoading && <LoadingText />}

			{detailQ.error && <ErrorPanel error={detailQ.error} />}

			{generalError && <ErrorPanel error={new Error(generalError)} />}

			<div className="space-y-4">
				{BUCKETS.map((b) => (
					<Card key={b.id} className="p-4">
						<div className="mb-3">
							<div className="text-[13px] font-semibold text-text">{b.label}</div>
							<div className="text-[11.5px] text-text-3">{b.hint}</div>
						</div>

						<ResourceDesigner
							resources={resourcesByBucket[b.id]}
							onChange={(entries) => setBucket(b.id, entries)}
							keyField="id"
							useCase="settings"
							settingsErrors={settingsErrors[b.id]}
						/>
					</Card>
				))}
			</div>
		</ConfigureLayout>
	);
};
