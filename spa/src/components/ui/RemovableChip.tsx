import { X } from "lucide-react";

interface RemovableChipProps {
	/** Visible text; also used for the remove button's accessible name (`Remove {label}`). */
	label: string;
	onRemove: () => void;
	disabled?: boolean;
	/** Layout-only classes for the wrapper (e.g. `shrink-0`). */
	className?: string;
}

/**
 * A removable tag pill — `accent-soft` background with a trailing `×`. Use for
 * selected tokens that the user can dismiss (tag inputs, message recipients).
 * Distinct from {@link Chip}, which is a toggleable filter button — this is a
 * non-interactive label with one remove affordance, so it's a `<span>` (no
 * nested buttons).
 */
export const RemovableChip = ({ label, onRemove, disabled, className }: RemovableChipProps) => (
	<span
		className={`inline-flex items-center gap-1 rounded-md bg-accent-soft px-1.5 py-0.5 text-[12px] text-accent${
			className ? ` ${className}` : ""
		}`}
	>
		{label}
		<button
			type="button"
			className="rounded p-0.5 text-accent hover:bg-accent/15 disabled:opacity-40"
			onClick={onRemove}
			disabled={disabled}
			aria-label={`Remove ${label}`}
		>
			<X size={11} />
		</button>
	</span>
);
