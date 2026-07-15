import { SectionLabel } from "@/components/ui/SectionLabel";
import {
	ResourceDesigner,
	toModuleFormFields,
	type ResourceEntry,
} from "@/components/developer/ResourceDesigner";
import type { ModuleFormField } from "@/api/endpoints/modules";

import { FormHooksEditor } from "./FormHooksEditor";

interface ModuleFieldsSectionProps {
	columnsTable: string;
	fields: ModuleFormField[];
	hooks: Record<string, unknown> | unknown[];
	onFieldsChange: (next: ModuleFormField[]) => void;
	onHooksChange: (next: Record<string, unknown>) => void;
	settingsErrors: Record<number, Record<string, string>>;
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
				columnsTable={columnsTable}
				keyField="column"
				resources={fields as unknown as ResourceEntry[]}
				settingsErrors={settingsErrors}
				useCase="modules"
				onChange={(next) => onFieldsChange(toModuleFormFields(next))}
			/>
		</div>

		<FormHooksEditor value={hooks} onChange={onHooksChange} />
	</>
);
