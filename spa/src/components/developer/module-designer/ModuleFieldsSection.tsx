import { SectionLabel } from "@/components/ui/SectionLabel";
import {
	ResourceDesigner,
	toModuleFormFields,
	type ResourceEntry,
} from "@/components/developer/ResourceDesigner";
import type { ModuleFormField } from "@/api/endpoints/modules";

import { FormHooksEditor } from "./FormHooksEditor";

interface ModuleFieldsSectionProps {
	fields: ModuleFormField[];
	onFieldsChange: (next: ModuleFormField[]) => void;
	settingsErrors: Record<number, Record<string, string>>;
	columnsTable: string;
	hooks: Record<string, unknown> | unknown[];
	onHooksChange: (next: Record<string, unknown>) => void;
}

/**
 * The Fields designer + form hooks editor shared by the module form tabs
 * (`ModuleFormsTab`, `ModuleEmbedFormsTab`). Both drive a `ResourceDesigner`
 * over the module's columns with per-field settings errors, then a
 * `FormHooksEditor` for the edit/pre/post/publish hooks.
 */
export const ModuleFieldsSection = ({
	fields,
	onFieldsChange,
	settingsErrors,
	columnsTable,
	hooks,
	onHooksChange,
}: ModuleFieldsSectionProps) => (
	<>
		<div>
			<SectionLabel className="mb-2">Fields</SectionLabel>
			<ResourceDesigner
				resources={fields as unknown as ResourceEntry[]}
				onChange={(next) => onFieldsChange(toModuleFormFields(next))}
				keyField="column"
				useCase="modules"
				columnsTable={columnsTable}
				settingsErrors={settingsErrors}
			/>
		</div>

		<FormHooksEditor value={hooks} onChange={onHooksChange} />
	</>
);
