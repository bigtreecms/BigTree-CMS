import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ExternalLink, Plus, Save, Trash } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Field } from "@/components/ui/Field";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";

import { configureApi, type MediaPreset } from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

const inputClass =
	"w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent-ring";

/**
 * Media presets — reusable image-field configurations.
 *
 * The legacy admin's preset editor is a Big modal that handles crops, thumbs,
 * center-crops, and per-crop output sizes. For this first SPA pass we expose
 * the common case (name + min dimensions + preview prefix) and round-trip the
 * advanced fields untouched so an existing preset stays intact when edited
 * here. For full crop/thumb editing the legacy modal still opens via the
 * deep-link at the top of the screen.
 */
export const ConfigureMediaPresets = () => {
	const queryClient = useQueryClient();
	const detailQ = useQuery({
		queryKey: ["configure", "media-presets"],
		queryFn: () => configureApi.mediaPresets.get(),
	});

	const [presets, setPresets] = useState<MediaPreset[]>([]);
	const [confirmDelete, setConfirmDelete] = useState<string | null>(null);
	const [generalError, setGeneralError] = useState<string | null>(null);

	useEffect(() => {
		if (detailQ.data) {
			setPresets(detailQ.data.presets.map((p) => ({ ...p })));
		}
	}, [detailQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: MediaPreset[]) => configureApi.mediaPresets.update({ presets: next }),
		onSuccess: (fresh) => {
			queryClient.setQueryData(["configure", "media-presets"], fresh);
			setPresets(fresh.presets.map((p) => ({ ...p })));
			toast.success("Media presets saved");
			setGeneralError(null);
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message ? err.message : "Could not save presets";
			setGeneralError(msg);
			toast.error(msg);
		},
	});

	const addPreset = () => {
		setPresets((prev) => [
			...prev,
			{
				id: `tmp-${Date.now()}`,
				name: "New preset",
				min_width: "",
				min_height: "",
				preview_prefix: "",
			} as MediaPreset,
		]);
	};

	const update = (id: string, patch: Partial<MediaPreset>) => {
		setPresets((prev) => prev.map((p) => (p.id === id ? { ...p, ...patch } : p)));
	};

	const remove = (id: string) => {
		const next = presets.filter((p) => p.id !== id);
		setPresets(next);
		saveMutation.mutate(next);
		setConfirmDelete(null);
	};

	return (
		<ConfigureLayout
			title="Media presets"
			sub="Reusable image-field configurations — minimum dimensions, crops, thumbnails. Save the dropdown of choices an editor sees on every image field."
			actions={
				<>
					<a
						href="/admin/developer/media/"
						target="_blank"
						rel="noreferrer"
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface-2 px-3 py-1.5 text-[12.5px] font-medium text-text hover:bg-hover"
					>
						Advanced editor
						<ExternalLink size={12} />
					</a>
					<button
						type="button"
						onClick={addPreset}
						className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
					>
						<Plus size={13} />
						Add preset
					</button>
				</>
			}
		>
			{detailQ.isLoading && <p className="text-[12.5px] text-text-3">Loading…</p>}

			{detailQ.error && <ErrorPanel error={detailQ.error} />}

			{generalError && <ErrorPanel error={new Error(generalError)} />}

			{detailQ.data && presets.length === 0 && (
				<p className="rounded-xl border border-dashed border-border bg-surface p-6 text-center text-[12.5px] text-text-3">
					No presets yet. Add one above to give editors a reusable choice on image fields.
				</p>
			)}

			<div className="space-y-3">
				{presets.map((p) => (
					<div key={p.id} className="rounded-xl border border-border bg-surface p-4">
						<div className="grid grid-cols-1 gap-3 md:grid-cols-4">
							<Field label="Name">
								<input
									className={inputClass}
									value={(p.name as string) ?? ""}
									onChange={(e) => update(p.id, { name: e.target.value })}
								/>
							</Field>

							<Field label="Min width (px)">
								<input
									className={inputClass}
									type="number"
									min={0}
									value={(p.min_width as string) ?? ""}
									onChange={(e) =>
										update(p.id, { min_width: e.target.value } as Partial<MediaPreset>)
									}
								/>
							</Field>

							<Field label="Min height (px)">
								<input
									className={inputClass}
									type="number"
									min={0}
									value={(p.min_height as string) ?? ""}
									onChange={(e) =>
										update(p.id, { min_height: e.target.value } as Partial<MediaPreset>)
									}
								/>
							</Field>

							<Field label="Preview prefix">
								<input
									className={inputClass}
									value={(p.preview_prefix as string) ?? ""}
									onChange={(e) =>
										update(p.id, { preview_prefix: e.target.value } as Partial<MediaPreset>)
									}
								/>
							</Field>
						</div>

						<div className="mt-3 flex items-center justify-between gap-3">
							<p className="text-[11.5px] text-text-3">
								Crops, thumbnails, and center-crops are round-tripped untouched. Use the
								"Advanced editor" button above for the full crop builder.
							</p>

							<button
								type="button"
								onClick={() => setConfirmDelete(p.id)}
								className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface-2 p-1.5 text-text-3 hover:bg-hover hover:text-danger"
								title="Delete preset"
								aria-label="Delete preset"
							>
								<Trash size={13} />
							</button>
						</div>
					</div>
				))}
			</div>

			{presets.length > 0 && (
				<div className="sticky bottom-4 mt-4 flex justify-end">
					<button
						type="button"
						onClick={() => saveMutation.mutate(presets)}
						disabled={saveMutation.isPending}
						className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover disabled:opacity-60"
					>
						<Save size={13} />
						{saveMutation.isPending ? "Saving…" : "Save all"}
					</button>
				</div>
			)}

			{confirmDelete && (
				<ConfirmDialog
					open
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDelete(null);
						}
					}}
					title="Delete this preset?"
					description="Image fields referencing it will fall back to their inline settings."
					confirmLabel="Delete"
					variant="danger"
					onConfirm={() => remove(confirmDelete)}
				/>
			)}
		</ConfigureLayout>
	);
};
