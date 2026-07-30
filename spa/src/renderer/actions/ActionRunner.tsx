import { useEffect, useRef, useState } from "react";

import { Alert } from "@/components/ui/Alert";
import { ErrorBoundary } from "@/components/ui/ErrorBoundary";
import { LoadingText } from "@/components/ui/LoadingText";
import { describeApiError } from "@/lib/errorHandling";

import type { ActionHost, ActionModule } from "./actionModuleContract";
import { loadActionModule, loadActionModuleFromSource } from "./actionModuleLoader";

interface ActionRunnerProps {
	/** URL of the module bundle (extension-delivered, schema.asset_url). */
	assetUrl?: string;
	host: ActionHost;
	/**
	 * Called with the load error message, or null on success. Lets the live
	 * preview surface compile errors; when provided, the failure is shown by the
	 * caller and this component renders nothing instead of its own error panel.
	 */
	onError?: (message: string | null) => void;
	/** Inline source (locally authored, schema.module_source) — run in-context. */
	source?: string;
}

/**
 * Runs a custom (module) action in-context: imports the module (from inline
 * `source` or an `assetUrl`) and renders its React component through the action
 * contract (actionModuleContract.ts). Local source and trusted (core/verified)
 * bundles run here; untrusted marketplace bundles go through the iframe sandbox
 * (a later build step). Render-time crashes in the author's component are caught
 * by the wrapping ErrorBoundary — because the module is a component the host
 * renders, not an imperative mount, the boundary actually sees them.
 *
 * When the action route changes, `host` updates immediately but the previous
 * module source can still be on screen for one paint (or until the next source
 * compiles). We refuse to render a module that wasn't loaded for the current
 * source — otherwise the old component re-runs effects against the *new* host
 * (e.g. Settings calling `{ op: "load" }` on Add Form's formEditor → "Form id
 * is required").
 */
export const ActionRunner = ({ host, assetUrl, source, onError }: ActionRunnerProps) => {
	const sourceKey = source != null ? `src:${source}` : assetUrl ? `url:${assetUrl}` : "";
	const [mod, setMod] = useState<ActionModule | null>(null);
	/** Source key the current `mod` was compiled/loaded for. */
	const [loadedKey, setLoadedKey] = useState<string | null>(null);
	const [error, setError] = useState<string | null>(null);

	// Keep the latest onError reachable without re-running the load effect (the
	// live preview passes a fresh closure each render).
	const onErrorRef = useRef(onError);
	onErrorRef.current = onError;

	useEffect(() => {
		let cancelled = false;

		setMod(null);
		setLoadedKey(null);
		setError(null);

		const load =
			source != null
				? loadActionModuleFromSource(source)
				: assetUrl
					? loadActionModule(assetUrl)
					: Promise.reject(new Error("No action source or asset URL provided."));

		load.then((loaded) => {
			if (!cancelled) {
				setMod(loaded);
				setLoadedKey(sourceKey);
				onErrorRef.current?.(null);
			}
		}).catch((err: unknown) => {
			if (!cancelled) {
				const message = describeApiError(err, "Failed to load action.");
				console.error("Action module load failed:", err);
				setMod(null);
				setLoadedKey(null);
				setError(message);
				onErrorRef.current?.(message);
			}
		});

		return () => {
			cancelled = true;
		};
	}, [assetUrl, source, sourceKey]);

	if (error) {
		// When a caller handles errors (live preview), render nothing and let it
		// show the message; otherwise show a self-contained failure panel.
		if (onError) {
			return null;
		}

		return (
			<Alert title="This action couldn't load" tone="danger">
				<div className="text-text-2">
					Its custom code failed to load. It may need to be rebuilt.
				</div>
				<pre className="mt-3 max-h-40 overflow-auto rounded bg-surface-2 p-2 font-mono text-[11.5px] text-text-3">
					{error}
				</pre>
			</Alert>
		);
	}

	// Loading, or still holding a module compiled for a previous action.
	if (!mod || loadedKey !== sourceKey) {
		return <LoadingText boxed label="Loading action…" />;
	}

	const Component = mod.Component;

	return (
		<ErrorBoundary resetKey={sourceKey}>
			{/* key forces a clean mount when the action source changes */}
			<Component key={sourceKey} host={host} />
		</ErrorBoundary>
	);
};
