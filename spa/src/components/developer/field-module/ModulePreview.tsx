import { useEffect, useMemo, useState } from "react";

import { applySettingDefaults, type SettingDescriptor } from "@/api/endpoints/field-types";
import type { ModuleFormField } from "@/api/endpoints/modules";
import { ModuleField } from "@/renderer/forms/ModuleField";

import { isVisible } from "../field-settings/evaluate";
import { controlRegistry } from "../field-settings/registry";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

interface ModulePreviewProps {
	source: string;
	/** Settings descriptors from the builder — rendered as a config form. */
	settingsSchema: SettingDescriptor[];
	typeId: string;
	name: string;
}

/**
 * Live preview of a local module field type. Debounces the source, imports it
 * in-context (the same way it'll run for real), and renders it with a scratch
 * value so the author can interact with the field as they edit. The settings
 * built alongside render as a configuration form whose values feed into the
 * field — so the whole settings → host.field.settings loop is testable before
 * saving. Compile/runtime errors surface inline.
 */
export const ModulePreview = ({ source, settingsSchema, typeId, name }: ModulePreviewProps) => {
	const [value, setValue] = useState<unknown>(undefined);
	const [error, setError] = useState<string | null>(null);
	const [debounced, setDebounced] = useState(source);
	// Values the author overrides in the preview; defaults come from the schema.
	const [overrides, setOverrides] = useState<Record<string, unknown>>({});

	const descriptors = settingsSchema;

	useEffect(() => {
		const timer = setTimeout(() => setDebounced(source), 400);

		return () => clearTimeout(timer);
	}, [source]);

	// Effective settings the previewed field sees: schema defaults under overrides.
	const settings = useMemo(
		() => applySettingDefaults(settingsSchema, overrides),
		[settingsSchema, overrides]
	);

	const field = useMemo<ModuleFormField>(
		() => ({
			column: "preview",
			type: typeId || "custom-preview",
			title: name || "Preview",
			settings,
		}),
		[typeId, name, settings]
	);

	if (!debounced.trim()) {
		return <InlineEmpty pad="md">Write some code to see a live preview.</InlineEmpty>;
	}

	const onPatch = (patch: Record<string, unknown>) =>
		setOverrides((prev) => ({ ...prev, ...patch }));

	return (
		<div className="space-y-2">
			<div className="rounded-md border border-border bg-surface p-3">
				{/* key forces a clean remount on source/settings change so render() reruns */}
				<ModuleField
					key={`${debounced}::${JSON.stringify(settings)}`}
					field={field}
					value={value}
					onChange={setValue}
					source={debounced}
					onError={setError}
				/>
			</div>

			{error && (
				<div className="rounded-md border border-danger/40 bg-danger/5 px-3 py-2 font-mono text-[11.5px] text-danger">
					{error}
				</div>
			)}

			<div className="text-[11px] text-text-3">
				Current value:{" "}
				<span className="font-mono text-text-2">
					{value === undefined ? "undefined" : JSON.stringify(value)}
				</span>
			</div>

			{descriptors.length > 0 && (
				<div className="space-y-3 rounded-md border border-border bg-surface-2 p-3">
					<div className="text-[11px] font-semibold uppercase tracking-[0.06em] text-text-3">
						Settings
					</div>
					{descriptors.map((descriptor) => {
						const Control = controlRegistry[descriptor.control];

						if (!Control || !isVisible(descriptor, settings, "modules")) {
							return null;
						}

						return (
							<Control
								key={descriptor.id}
								descriptor={descriptor}
								settings={settings}
								onPatch={onPatch}
								useCase="modules"
							/>
						);
					})}
				</div>
			)}
		</div>
	);
};
