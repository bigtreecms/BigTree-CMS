interface EmbedHoneypotProps {
	onChange: (value: string) => void;
	value: string;
}

/**
 * A field people never see but form-filling bots do. Anything typed here
 * rejects the submission server-side (the legacy `_bigtree_email` trap). It's
 * moved off-screen rather than `display: none` because some bots skip hidden
 * inputs, and it's kept out of the tab order and the accessibility tree.
 */
export const EmbedHoneypot = ({ value, onChange }: EmbedHoneypotProps) => (
	<div aria-hidden="true" className="absolute left-[-10000px] top-auto size-px overflow-hidden">
		<label htmlFor="bigtree-embed-email">Leave this field empty</label>
		<input
			autoComplete="off"
			id="bigtree-embed-email"
			name="email"
			tabIndex={-1}
			type="text"
			value={value}
			onChange={(e) => onChange(e.target.value)}
		/>
	</div>
);
