import { useJsonDraft } from "../../../hooks/useJsonDraft";
import { TextArea } from "../../ui/TextArea";

interface JsonFallbackControlProps {
	onChange: (next: Record<string, unknown>) => void;
	value: Record<string, unknown> | unknown[] | undefined;
}

/**
 * Raw-JSON settings editor. Used for custom / extension field types that don't
 * ship a settings schema, and as the always-available "Edit as JSON" escape
 * hatch for schema-driven types.
 */
export const JsonFallbackControl = ({ value, onChange }: JsonFallbackControlProps) => {
	const { draft, error, setDraft, commit } = useJsonDraft(
		value,
		onChange,
		"Field settings must be a JSON object."
	);

	return (
		<div>
			<TextArea
				mono
				aria-label="JSON settings"
				className="leading-relaxed"
				rows={6}
				spellCheck={false}
				value={draft}
				onBlur={commit}
				onChange={(e) => setDraft(e.target.value)}
			/>
			{error && (
				<span data-field-error className="mt-1 block text-[11.5px] text-danger">
					{error}
				</span>
			)}
		</div>
	);
};
