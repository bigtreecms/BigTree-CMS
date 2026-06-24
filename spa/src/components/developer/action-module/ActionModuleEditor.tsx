import { useState, type KeyboardEvent } from "react";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { ChevronDown, ChevronRight } from "lucide-react";

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
 * ModuleSourceEditor, minus the per-field settings schema.
 */
export const ActionModuleEditor = ({ value, onChange, name, route }: ActionModuleEditorProps) => {
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
				<SectionLabel className="mb-2">Action code</SectionLabel>
				<textarea
					value={value}
					aria-label="Action code"
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
				<button
					type="button"
					onClick={() => setShowApi((v) => !v)}
					className="flex w-full items-center gap-1.5 px-3 py-2 text-[12px] font-medium text-text-2"
				>
					{showApi ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
					Host API reference
				</button>
				{showApi && (
					<dl className="grid grid-cols-1 gap-x-4 gap-y-1.5 border-t border-border p-3 sm:grid-cols-[auto_1fr]">
						{ACTION_HOST_API_REFERENCE.map((row) => (
							<div key={row.name} className="contents">
								<dt className="font-mono text-[11.5px] text-accent">{row.name}</dt>
								<dd className="text-[12px] text-text-2">{row.desc}</dd>
							</div>
						))}
					</dl>
				)}
			</div>

			<div>
				<SectionLabel className="mb-2">Live preview</SectionLabel>
				<ActionModulePreview source={value} name={name} route={route} />
			</div>
		</div>
	);
};
