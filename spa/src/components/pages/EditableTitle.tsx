import { useEffect, useRef, useState } from "react";

/**
 * Inline-editable title using contentEditable. Double-click enters edit mode,
 * Enter commits, Escape cancels. The contentEditable approach (rather than
 * swapping in an <input>) keeps the visual layout stable while editing — no
 * jumpy resize as the field changes from text to input.
 */
interface EditableTitleProps {
	value: string;
	onChange: (next: string) => void;
}

export const EditableTitle = ({ value, onChange }: EditableTitleProps) => {
	const ref = useRef<HTMLSpanElement>(null);
	const [editing, setEditing] = useState(false);

	// Keep the DOM text in sync with the value prop when not editing.
	useEffect(() => {
		if (!editing && ref.current && ref.current.innerText !== value) {
			ref.current.innerText = value;
		}
	}, [value, editing]);

	const startEdit = () => {
		setEditing(true);

		// Defer until the contentEditable attribute is applied, then select all.
		setTimeout(() => {
			const el = ref.current;

			if (!el) {
				return;
			}

			el.focus();
			const range = document.createRange();
			range.selectNodeContents(el);
			const sel = window.getSelection();
			sel?.removeAllRanges();
			sel?.addRange(range);
		}, 0);
	};

	const commit = () => {
		setEditing(false);
		const next = (ref.current?.innerText ?? "").trim();

		if (next && next !== value) {
			onChange(next);
		} else if (ref.current) {
			// Revert if blank or unchanged
			ref.current.innerText = value;
		}
	};

	return (
		<span
			ref={ref}
			contentEditable={editing}
			suppressContentEditableWarning
			onDoubleClick={startEdit}
			onBlur={commit}
			onKeyDown={(e) => {
				if (e.key === "Enter") {
					e.preventDefault();
					ref.current?.blur();
				} else if (e.key === "Escape") {
					if (ref.current) {
						ref.current.innerText = value;
					}

					setEditing(false);
					ref.current?.blur();
				}
			}}
			className={`-mx-1 -my-0.5 cursor-pointer truncate   rounded px-1 py-0.5 font-medium text-text hover:bg-hover ${
				editing ? "cursor-text bg-surface outline outline-2 outline-accent" : ""
			}`}
		>
			{value}
		</span>
	);
};
