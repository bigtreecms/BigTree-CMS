import { useQuery } from "@tanstack/react-query";
import { Plus, Trash } from "lucide-react";

import { systemApi } from "@/api/endpoints/system";
import { queryKeys } from "@/lib/queryKeys";

import { Checkbox } from "../../ui/Checkbox";
import { Select } from "../../ui/Select";
import { SectionLabel } from "../../ui/SectionLabel";

import { ControlShell } from "./ControlShell";
import type { ControlProps } from "./types";
import { IconButton } from "@/components/ui/IconButton";
import { TextInput } from "@/components/ui/TextInput";
import { useListEditor } from "@/hooks/useListEditor";

/**
 * Image processing options shared by the image / video / media-gallery field
 * types (legacy core/admin/field-types/_image-options.php). Manages several
 * top-level settings keys directly: preset, min_width, min_height,
 * preview_prefix, retina, and the crops / thumbs / center_crops arrays.
 *
 * Crops can contain nested thumbs and center_crops. Greyscale is stored as
 * "on" / "" to match the legacy hidden inputs. When a preset is selected the
 * manual options are hidden — the preset alone is persisted.
 */

interface DimRow {
	center_crops?: DimRow[];
	grayscale?: string;
	height?: string;
	prefix?: string;
	thumbs?: DimRow[];
	width?: string;
}

const asRows = (value: unknown): DimRow[] => (Array.isArray(value) ? (value as DimRow[]) : []);

interface DimFieldsProps {
	onChange: (patch: Partial<DimRow>) => void;
	onRemove: () => void;
	row: DimRow;
}

const DimFields = ({ row, onChange, onRemove }: DimFieldsProps) => (
	<div className="flex items-center gap-2">
		<TextInput
			compact
			aria-label="Prefix"
			className="min-w-0 flex-1"
			placeholder="Prefix"
			value={row.prefix ?? ""}
			onChange={(e) => onChange({ prefix: e.target.value })}
		/>
		<TextInput
			compact
			aria-label="Width"
			className="min-w-0 flex-1"
			inputMode="numeric"
			placeholder="Width"
			value={row.width ?? ""}
			onChange={(e) => onChange({ width: e.target.value.replace(/[^0-9]/g, "") })}
		/>
		<TextInput
			compact
			aria-label="Height"
			className="min-w-0 flex-1"
			inputMode="numeric"
			placeholder="Height"
			value={row.height ?? ""}
			onChange={(e) => onChange({ height: e.target.value.replace(/[^0-9]/g, "") })}
		/>
		<Checkbox
			checked={Boolean(row.grayscale)}
			className="whitespace-nowrap"
			label="Grey"
			size="sm"
			onChange={(checked) => onChange({ grayscale: checked ? "on" : "" })}
		/>
		<IconButton label="Remove" tone="danger" onClick={onRemove}>
			<Trash size={13} />
		</IconButton>
	</div>
);

interface DimListProps {
	label: string;
	onChange: (next: DimRow[]) => void;
	rows: DimRow[];
}

const DimList = ({ label, rows, onChange }: DimListProps) => {
	const { update, remove, add } = useListEditor<DimRow>(rows, onChange);

	return (
		<div className="space-y-1.5">
			<div className="flex items-center justify-between">
				<span className="text-[11.5px] font-medium text-text-2">{label}</span>
				<button
					className="inline-flex items-center gap-1 rounded border border-border bg-surface px-2 py-0.5 text-[11.5px] hover:bg-hover"
					type="button"
					onClick={() => add({})}
				>
					<Plus size={11} />
					Add
				</button>
			</div>
			{rows.map((row, index) => (
				<DimFields
					key={index}
					row={row}
					onChange={(patch) => update(index, patch)}
					onRemove={() => remove(index)}
				/>
			))}
		</div>
	);
};

export const ImageOptionsControl = ({ settings, onPatch }: ControlProps) => {
	const presetsQ = useQuery({
		queryKey: queryKeys.mediaPresets.root(),
		queryFn: () => systemApi.mediaPresets(),
	});

	const preset = String(settings.preset ?? "");
	const presets = presetsQ.data?.presets ?? [];
	const crops = asRows(settings.crops);

	const cropEditor = useListEditor<DimRow>(crops, (next) => onPatch({ crops: next }));
	const updateCrop = cropEditor.update;

	return (
		<div className="space-y-3 rounded-md border border-border bg-surface-2 p-3">
			<ControlShell label="Existing Preset">
				<Select
					dense
					disabled={presetsQ.isLoading}
					value={preset}
					onChange={(e) => onPatch({ preset: e.target.value })}
				>
					<option value="" />
					{presets.map((p) => (
						<option key={p.id} value={String(p.id)}>
							{p.name}
						</option>
					))}
				</Select>
			</ControlShell>

			{preset !== "" ? (
				<p className="text-[11.5px] text-text-3">
					Using a media preset. Clear the preset above to configure crops and thumbnails
					manually.
				</p>
			) : (
				<>
					<div className="grid grid-cols-1 gap-3 md:grid-cols-3">
						<ControlShell hint="(px)" label="Minimum Width">
							<TextInput
								compact
								className="min-w-0 flex-1"
								inputMode="numeric"
								value={String(settings.min_width ?? "")}
								onChange={(e) =>
									onPatch({ min_width: e.target.value.replace(/[^0-9]/g, "") })
								}
							/>
						</ControlShell>
						<ControlShell hint="(px)" label="Minimum Height">
							<TextInput
								compact
								className="min-w-0 flex-1"
								inputMode="numeric"
								value={String(settings.min_height ?? "")}
								onChange={(e) =>
									onPatch({ min_height: e.target.value.replace(/[^0-9]/g, "") })
								}
							/>
						</ControlShell>
						<ControlShell hint="(forms)" label="Preview Prefix">
							<TextInput
								compact
								className="min-w-0 flex-1"
								value={String(settings.preview_prefix ?? "")}
								onChange={(e) => onPatch({ preview_prefix: e.target.value })}
							/>
						</ControlShell>
					</div>

					<Checkbox
						checked={Boolean(settings.retina)}
						label="Create Hi-Resolution Retina Images When Available"
						onChange={(next) => onPatch({ retina: next ? "on" : "" })}
					/>

					<div className="space-y-2 border-t border-border pt-2">
						<div className="flex items-center justify-between">
							<SectionLabel size="sm">Crops</SectionLabel>
							<button
								className="inline-flex items-center gap-1 rounded border border-border bg-surface px-2 py-0.5 text-[11.5px] hover:bg-hover"
								type="button"
								onClick={() => cropEditor.add({})}
							>
								<Plus size={11} />
								Add crop
							</button>
						</div>
						{crops.map((crop, index) => (
							<div
								className="space-y-2 rounded border border-border bg-surface p-2"
								key={index}
							>
								<DimFields
									row={crop}
									onChange={(patch) => updateCrop(index, patch)}
									onRemove={() => cropEditor.remove(index)}
								/>
								<div className="ml-4 space-y-2 border-l border-border pl-3">
									<DimList
										label="Thumbnails of crop"
										rows={asRows(crop.thumbs)}
										onChange={(next) => updateCrop(index, { thumbs: next })}
									/>
									<DimList
										label="Center sub-crops"
										rows={asRows(crop.center_crops)}
										onChange={(next) =>
											updateCrop(index, { center_crops: next })
										}
									/>
								</div>
							</div>
						))}
					</div>

					<div className="border-t border-border pt-2">
						<DimList
							label="Thumbnails"
							rows={asRows(settings.thumbs)}
							onChange={(next) => onPatch({ thumbs: next })}
						/>
					</div>

					<div className="border-t border-border pt-2">
						<DimList
							label="Center Crops"
							rows={asRows(settings.center_crops)}
							onChange={(next) => onPatch({ center_crops: next })}
						/>
					</div>
				</>
			)}
		</div>
	);
};
