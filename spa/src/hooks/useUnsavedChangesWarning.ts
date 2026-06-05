import { useEffect } from "react";
import { useBlocker, type Blocker } from "react-router-dom";

/**
 * Warn the user before they abandon unsaved edits.
 *
 * When `isDirty` is true this guards two distinct exits:
 *
 *   1) In-app navigation (clicking a link, hitting Back, programmatic
 *      `navigate()`) is intercepted via the data-router `useBlocker`. The
 *      returned blocker is "blocked" while a navigation is pending — the
 *      caller renders a confirm dialog and resolves it with `proceed()` /
 *      `reset()`.
 *   2) Full-page exits (refresh, tab close, typing a new URL) are guarded with
 *      a native `beforeunload` listener, which shows the browser's own generic
 *      prompt. We can't customise that text — browsers ignore it.
 *
 * Requires a data router (`createBrowserRouter`); `useBlocker` is a no-op
 * otherwise. Only one blocker may be active per router at a time, so do not
 * mount two of these guards simultaneously.
 *
 * The blocker callback only fires on a real path change, so in-page query/hash
 * updates don't trip the warning.
 */
export const useUnsavedChangesWarning = (isDirty: boolean): Blocker => {
	const blocker = useBlocker(
		({ currentLocation, nextLocation }) =>
			isDirty && currentLocation.pathname !== nextLocation.pathname
	);

	useEffect(() => {
		if (!isDirty) {
			return;
		}

		const handleBeforeUnload = (event: BeforeUnloadEvent) => {
			event.preventDefault();
			// Legacy Chrome/Firefox require a truthy returnValue to show the prompt.
			event.returnValue = "";
		};

		window.addEventListener("beforeunload", handleBeforeUnload);

		return () => window.removeEventListener("beforeunload", handleBeforeUnload);
	}, [isDirty]);

	// If the form stops being dirty while a navigation is parked (e.g. the guard
	// is lifted mid-prompt), let the pending navigation through rather than
	// leaving it wedged in the "blocked" state.
	useEffect(() => {
		if (!isDirty && blocker.state === "blocked") {
			blocker.proceed();
		}
	}, [isDirty, blocker]);

	return blocker;
};
