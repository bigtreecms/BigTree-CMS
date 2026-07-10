import { SectionLabel } from "@/components/ui/SectionLabel";

import { CodeSourceEditor } from "../CodeSourceEditor";
import { ActionModulePreview } from "./ActionModulePreview";
import { ACTION_HOST_API_REFERENCE } from "./starterTemplate";

interface ActionModuleEditorProps {
	value: string;
	onChange: (next: string) => void;
	/** Action name + route — used to populate the live preview's host.context. */
	name: string;
	route: string;
}

/**
 * Authoring surface for a custom (module) action: a code editor for the action's
 * TSX source, a collapsible host-API reference, and a live preview. The source is
 * run in-context (implicitly trusted — you wrote it on your own install); trust /
 * signing only matter when packaging into an extension. Mirror of the field-type
 * ModuleSourceEditor, minus the per-field settings schema; both share the
 * {@link CodeSourceEditor} code surface.
 */
export const ActionModuleEditor = ({ value, onChange, name, route }: ActionModuleEditorProps) => (
	<div className="space-y-3">
		<CodeSourceEditor
			label="Action code"
			value={value}
			onChange={onChange}
			reference={ACTION_HOST_API_REFERENCE}
		/>

		<div>
			<SectionLabel className="mb-2">Live preview</SectionLabel>
			<ActionModulePreview source={value} name={name} route={route} />
		</div>
	</div>
);
