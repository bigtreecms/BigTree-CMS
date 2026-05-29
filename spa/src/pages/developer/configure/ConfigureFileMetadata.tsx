import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ExternalLink, Plus, Save, Trash } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Field } from "@/components/ui/Field";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import {
	configureApi,
	type FileMetadataConfig,
	type FileMetadataField,
} from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

const inputClass =
	"w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent-ring";

const BUCKETS: Array<{ id: keyof FileMetadataConfig; label: string; hint: string }> = [
	{ id: "file", label: "Generic files", hint: "Asked on every non-image, non-video upload." },
	{ id: "image", label: "Images", hint: "Asked on every image upload." },
	{ id: "video", label: "Videos", hint: "Asked on every video upload." },
];

// Keep this list in sync with the field types the legacy admin allows for
// metadata. Custom field types still load via the form-renderer at edit time;
// this dropdown just picks the type up front.
const FIELD_TYPES = ["text", "textarea", "html", "checkbox", "select", "radio", "date", "number"];

const emptyRow = (): FileMetadataField => ({
	id: "",
	title: "",
	subtitle: "",
	type: "text",
	settings: {},
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
				err instanceof ApiError && err.message ? err.message : "Could not save file metadata";
			setGeneralError(msg);
			toast.error(msg);
		},
	});

	const addRow = (bucket: keyof FileMetadataConfig) => {
		setDraft((prev) => ({ ...prev, [bucket]: [...prev[bucket], emptyRow()] }));
	};

	const updateRow = (
		bucket: keyof FileMetadataConfig,
		index: number,
		patch: Partial<FileMetadataField>
	) => {
		setDraft((prev) => {
			const rows = [...prev[bucket]];
			const existing = rows[index];

			if (!existing) {
				return prev;
			}

			rows[index] = { ...existing, ...patch };

			return { ...prev, [bucket]: rows };
		});
	};

	const removeRow = (bucket: keyof FileMetadataConfig, index: number) => {
		setDraft((prev) => ({
			...prev,
			[bucket]: prev[bucket].filter((_, i) => i !== index),
		}));
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
						<div className="mb-2 flex items-center justify-between">
							<div>
								<div className="text-[13px] font-semibold text-text">{b.label}</div>
								<div className="text-[11.5px] text-text-3">{b.hint}</div>
							</div>
							<button
								type="button"
								onClick={() => addRow(b.id)}
								className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface-2 px-2.5 py-1 text-[12px] font-medium text-text hover:bg-hover"
							>
								<Plus size={12} />
								Field
							</button>
						</div>

						{draft[b.id].length === 0 ? (
							<p className="rounded-md border border-dashed border-border bg-surface-2 p-3 text-[12px] text-text-3">
								No {b.label.toLowerCase()} fields yet.
							</p>
						) : (
							<div className="space-y-2">
								{draft[b.id].map((row, i) => (
									<div
										key={i}
										className="grid grid-cols-12 gap-2 rounded-md border border-border bg-surface-2 p-2"
									>
										<div className="col-span-3">
											<Field label="ID">
												<input
													className={inputClass}
													value={row.id}
													onChange={(e) => updateRow(b.id, i, { id: e.target.value })}
												/>
											</Field>
										</div>
										<div className="col-span-3">
											<Field label="Title">
												<input
													className={inputClass}
													value={row.title}
													onChange={(e) => updateRow(b.id, i, { title: e.target.value })}
												/>
											</Field>
										</div>
										<div className="col-span-3">
											<Field label="Subtitle">
												<input
													className={inputClass}
													value={row.subtitle}
													onChange={(e) => updateRow(b.id, i, { subtitle: e.target.value })}
												/>
											</Field>
										</div>
										<div className="col-span-2">
											<Field label="Type">
												<select
													className={inputClass}
													value={row.type}
													onChange={(e) => updateRow(b.id, i, { type: e.target.value })}
												>
													{FIELD_TYPES.map((t) => (
														<option key={t} value={t}>
															{t}
														</option>
													))}
												</select>
											</Field>
										</div>
										<div className="col-span-1 flex items-end justify-end">
											<button
												type="button"
												onClick={() => removeRow(b.id, i)}
												className="rounded-md border border-border bg-surface p-1.5 text-text-3 hover:bg-hover hover:text-danger"
												title="Remove field"
												aria-label="Remove field"
											>
												<Trash size={13} />
											</button>
										</div>
									</div>
								))}
							</div>
						)}
					</div>
				))}

				<p className="text-[11.5px] text-text-3">
					Need per-field settings (select options, validation rules)? The advanced editor still
					opens on the{" "}
					<a
						className="inline-flex items-center gap-1 text-accent underline"
						href="/admin/developer/files/"
						target="_blank"
						rel="noreferrer"
					>
						legacy admin <ExternalLink size={11} />
					</a>
					.
				</p>
			</div>
		</ConfigureLayout>
	);
};
