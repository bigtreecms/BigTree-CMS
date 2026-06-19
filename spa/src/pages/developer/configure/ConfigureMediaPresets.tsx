import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronDown, ChevronRight, Plus, Save, Trash } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { HeaderBtn } from "@/components/ui/HeaderBtn";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { MediaPresetEditor } from "@/components/developer/MediaPresetEditor";

import { configureApi, type MediaPreset } from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

const inputClass =
	"w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent-ring";

/**
 * Media presets — reusable image-field configurations.
 *
 * Edits the full preset recipe natively (min dimensions, preview prefix, retina,
 * crops with nested thumbnails / center sub-crops, top-level thumbnails, and
 * center crops) via the MediaPresetEditor. The advanced fields used to be
 * editable only on the legacy admin's crop modal.
 */
export const ConfigureMediaPresets = () => {
	const queryClient = useQueryClient();
	const detailQ = useQuery({
		queryKey: ["configure", "media-presets"],
		queryFn: () => configureApi.mediaPresets.get(),
	});

	const [presets, setPresets] = useState<MediaPreset[]>([]);
	const [expanded, setExpanded] = useState<string | null>(null);
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
		const id = `tmp-${Date.now()}`;
		setPresets((prev) => [
			...prev,
			{
				id,
				name: "New preset",
				min_width: "",
				min_height: "",
				preview_prefix: "",
			} as MediaPreset,
		]);
		setExpanded(id);
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
				<HeaderBtn primary icon={<Plus size={13} />} onClick={addPreset}>
					Add preset
				</HeaderBtn>
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
				{presets.map((p) => {
					const isOpen = expanded === p.id;

					return (
						<div key={p.id} className="rounded-xl border border-border bg-surface">
							<div className="flex items-center gap-2 p-3">
								<button
									type="button"
									onClick={() => setExpanded(isOpen ? null : p.id)}
									className="rounded p-0.5 text-text-3 hover:bg-hover hover:text-text"
									aria-label={isOpen ? "Collapse" : "Expand"}
									aria-expanded={isOpen}
								>
									{isOpen ? (
										<ChevronDown size={15} />
									) : (
										<ChevronRight size={15} />
									)}
								</button>

								<input
									className={inputClass}
									value={(p.name as string) ?? ""}
									placeholder="Preset name"
									onChange={(e) => update(p.id, { name: e.target.value })}
								/>

								<button
									type="button"
									onClick={() => setConfirmDelete(p.id)}
									className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface-2 p-2 text-text-3 hover:bg-hover hover:text-danger"
									title="Delete preset"
									aria-label="Delete preset"
								>
									<Trash size={13} />
								</button>
							</div>

							{isOpen && (
								<div className="border-t border-border p-4">
									<MediaPresetEditor
										preset={p}
										onChange={(patch) => update(p.id, patch)}
									/>
								</div>
							)}
						</div>
					);
				})}
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
