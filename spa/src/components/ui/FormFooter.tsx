import { Save } from "lucide-react";
import type { ReactNode } from "react";

import { Button } from "./Button";

interface FormFooterProps {
	/** Cancel button label. Defaults to "Cancel". */
	cancelLabel?: ReactNode;
	/** Cancel destination — renders a `<Link>`. Use this or `onCancel`. */
	cancelTo?: string;
	/** Extra guard that disables submit (e.g. a validity flag). */
	disabled?: boolean;
	/** Optional action rendered far-left of the pair (e.g. a Delete button). */
	extra?: ReactNode;
	/** Busy state for the submit button (e.g. `mutation.isPending`). */
	loading?: boolean;
	/** Label shown on the submit button while `loading` (e.g. "Saving…"). */
	loadingLabel?: ReactNode;
	/** Cancel handler — renders a plain button. Use this or `cancelTo`. */
	onCancel?: () => void;
	/** Submit button icon. Defaults to a Save icon. */
	submitIcon?: ReactNode;
	/** Primary submit button label (e.g. "Save feed"). */
	submitLabel: ReactNode;
}

/**
 * The standard edit-page form footer: a ghost Cancel on the left and a primary
 * submit on the right, composing the `Button` `loading` prop for the
 * pending/disabled dance. Drop into a `FormShell` `footer` slot. Pages with a
 * non-standard footer can pass `extra` (a far-left Delete, say) or fall back to
 * a raw fragment.
 */
export const FormFooter = ({
	cancelTo,
	onCancel,
	cancelLabel = "Cancel",
	submitLabel,
	submitIcon = <Save size={13} />,
	loading,
	loadingLabel,
	disabled,
	extra,
}: FormFooterProps) => (
	<>
		{extra && <span className="mr-auto">{extra}</span>}
		<Button to={cancelTo} onClick={onCancel}>
			{cancelLabel}
		</Button>
		<Button
			disabled={disabled}
			icon={submitIcon}
			loading={loading}
			loadingLabel={loadingLabel}
			type="submit"
			variant="primary"
		>
			{submitLabel}
		</Button>
	</>
);
