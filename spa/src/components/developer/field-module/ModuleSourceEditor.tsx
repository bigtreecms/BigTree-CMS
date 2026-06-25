import { useState, type KeyboardEvent } from "react";
import { Alert } from "@/components/ui/Alert";
import { DisclosureToggle } from "@/components/ui/DisclosureToggle";
import { SectionLabel } from "@/components/ui/SectionLabel";

import type { SettingDescriptor } from "@/api/endpoints/field-types";

import { ModulePreview } from "./ModulePreview";
import { SettingsSchemaBuilder } from "./SettingsSchemaBuilder";
import { HOST_API_REFERENCE } from "./starterTemplate";

interface ModuleSourceEditorProps {
	value: string;
	onChange: (next: string) => void;
	/** Settings schema, built here and stored in settings.js. */
	settingsSchema: SettingDescriptor[];
	onSettingsSchemaChange: (next: SettingDescriptor[]) => void;
	/** True when settings.js exists on disk but the backend couldn't parse it. */
	settingsParseError?: boolean;
	/** Field type id + name — used to label the live preview. */
	typeId: string;
	name: string;
}

/**
 * The local-module authoring surface: a code editor for the field's source, a
 * settings-schema builder (stored in settings.js, surfaced to the field as
 * host.field.settings), a collapsible host-API reference, and a live preview.
 * The source/settings are run in-context (implicitly trusted — see
 * spa/.custom-field-types-design.md). No URLs, trust levels, or signing here;
 * those only matter when packaging a module into an extension for distribution.
 */
export const ModuleSourceEditor = ({
	value,
	onChange,
	settingsSchema,
	onSettingsSchemaChange,
	settingsParseError,
	typeId,
	name,
}: ModuleSourceEditorProps) => {
	const [showApi, setShowApi] = useState(false);

	// Tab inserts a tab character instead of moving focus, so code stays editable.
	const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
		if (event.key !== "Tab") {
			return;
		}

		event.preventDefault();

		const target = event.currentTarget;
		const { selectionStart, selectionEnd } = target;
		const next = value.slice(0, selectionStart) + "\t" + value.slice(selectionEnd);

		onChange(next);
		requestAnimationFrame(() => {
			target.selectionStart = target.selectionEnd = selectionStart + 1;
		});
	};

	return (
		<div className="space-y-3">
			<div>
				<SectionLabel className="mb-2">Module code</SectionLabel>
				<textarea
					value={value}
					aria-label="Module code"
					onChange={(e) => onChange(e.target.value)}
					onKeyDown={onKeyDown}
					spellCheck={false}
					autoCapitalize="off"
					autoCorrect="off"
					rows={18}
					className="w-full rounded-md border border-border bg-surface-2 p-3 font-mono text-[12.5px] leading-relaxed text-text focus:outline-none focus:ring-1 focus:ring-accent-ring"
				/>
			</div>

			<div className="rounded-md border border-border bg-surface">
				<DisclosureToggle
					open={showApi}
					onToggle={() => setShowApi((v) => !v)}
					size={14}
					className="w-full gap-1.5 px-3 py-2 text-[12px] font-medium text-text-2"
					label="Host API reference"
				/>
				{showApi && (
					<dl className="grid grid-cols-1 gap-x-4 gap-y-1.5 border-t border-border p-3 sm:grid-cols-[auto_1fr]">
						{HOST_API_REFERENCE.map((row) => (
							<div key={row.name} className="contents">
								<dt className="font-mono text-[11.5px] text-accent">{row.name}</dt>
								<dd className="text-[12px] text-text-2">{row.desc}</dd>
							</div>
						))}
					</dl>
				)}
			</div>

			<div>
				<SectionLabel className="mb-2">Settings</SectionLabel>
				{settingsParseError && (
					<Alert tone="danger" className="mb-2">
						Couldn&apos;t parse <code>settings.js</code> — the settings shown here may
						be empty or out of date. Rebuild them below and save to overwrite the file.
					</Alert>
				)}
				<SettingsSchemaBuilder value={settingsSchema} onChange={onSettingsSchemaChange} />
			</div>

			<div>
				<SectionLabel className="mb-2">Live preview</SectionLabel>
				<ModulePreview
					source={value}
					settingsSchema={settingsSchema}
					typeId={typeId}
					name={name}
				/>
			</div>
		</div>
	);
};
