interface SwitchProps {
	on: boolean;
	onChange: (next: boolean) => void;
	/** Accessible name — these are icon-free, so a label is required. */
	label: string;
	disabled?: boolean;
	/** Layout-only classes appended to the button (e.g. margins). */
	className?: string;
}

/**
 * On/off toggle switch — the single source of truth for the sliding-knob
 * `aria-pressed` toggle (vs. the inline-flow {@link Checkbox}, which is the
 * default for form booleans). Reach for `Switch` when the on/off state is a
 * prominent, immediate setting with its own description (e.g. the Add-User
 * "Send invitation email" toggle). Token-driven so light/dark both work.
 */
export const Switch = ({ on, onChange, label, disabled, className }: SwitchProps) => (
	<button
		type="button"
		className={`inline-flex h-5 w-9 items-center rounded-full border border-border bg-surface p-0.5 transition-colors data-[on=true]:bg-accent disabled:cursor-not-allowed disabled:opacity-50${
			className ? ` ${className}` : ""
		}`}
		data-on={on}
		onClick={() => onChange(!on)}
		aria-label={label}
		aria-pressed={on}
		disabled={disabled}
	>
		<span
			className="inline-block size-3.5 rounded-full bg-white shadow transition-transform data-[on=true]:translate-x-4"
			data-on={on}
		/>
	</button>
);
