import { useLocation } from "react-router-dom";

/**
 * Resolves where to send the user after a save/delete on a detail page.
 *
 * Mirrors the legacy admin (and `PageEdit`): never leave the editor sitting
 * on the screen with just a growl. Return to whatever screen linked here
 * (captured in router state as `from`), or fall back to the list view for
 * the related entity.
 */
export const useReturnTo = (listPath: string): string => {
	const location = useLocation();
	const from = (location.state as { from?: string } | null)?.from;

	return from || listPath;
};
