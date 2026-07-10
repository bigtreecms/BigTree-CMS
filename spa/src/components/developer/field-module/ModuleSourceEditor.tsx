import { Alert } from "@/components/ui/Alert";
import { SectionLabel } from "@/components/ui/SectionLabel";

import type { SettingDescriptor } from "@/api/endpoints/field-types";

import { CodeSourceEditor } from "../CodeSourceEditor";
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
 * The code surface itself is the shared {@link CodeSourceEditor}.
 */
export const ModuleSourceEditor = ({
	value,
	onChange,
	settingsSchema,
	onSettingsSchemaChange,
	settingsParseError,
	typeId,
	name,
}: ModuleSourceEditorProps) => (
	<div className="space-y-3">
		<CodeSourceEditor
			label="Module code"
			value={value}
			onChange={onChange}
			reference={HOST_API_REFERENCE}
		/>

		<div>
			<SectionLabel className="mb-2">Settings</SectionLabel>
			{settingsParseError && (
				<Alert tone="danger" className="mb-2">
					Couldn&apos;t parse <code>settings.js</code> — the settings shown here may be
					empty or out of date. Rebuild them below and save to overwrite the file.
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
