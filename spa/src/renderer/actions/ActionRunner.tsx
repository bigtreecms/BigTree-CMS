import { useEffect, useRef, useState } from "react";

import { Alert } from "@/components/ui/Alert";
import { ErrorBoundary } from "@/components/ui/ErrorBoundary";

import type { ActionHost, ActionModule } from "./actionModuleContract";
import { loadActionModule, loadActionModuleFromSource } from "./actionModuleLoader";

interface ActionRunnerProps {
	host: ActionHost;
	/** URL of the module bundle (extension-delivered, schema.asset_url). */
	assetUrl?: string;
	/** Inline source (locally authored, schema.module_source) — run in-context. */
	source?: string;
	/**
	 * Called with the load error message, or null on success. Lets the live
	 * preview surface compile errors; when provided, the failure is shown by the
	 * caller and this component renders nothing instead of its own error panel.
	 */
	onError?: (message: string | null) => void;
}

/**
 * Runs a custom (module) action in-context: imports the module (from inline
 * `source` or an `assetUrl`) and renders its React component through the action
 * contract (actionModuleContract.ts). Local source and trusted (core/verified)
 * bundles run here; untrusted marketplace bundles go through the iframe sandbox
 * (a later build step). Render-time crashes in the author's component are caught
 * by the wrapping ErrorBoundary — because the module is a component the host
 * renders, not an imperative mount, the boundary actually sees them.
 */
export const ActionRunner = ({ host, assetUrl, source, onError }: ActionRunnerProps) => {
	const [mod, setMod] = useState<ActionModule | null>(null);
	const [error, setError] = useState<string | null>(null);

	// Keep the latest onError reachable without re-running the load effect (the
	// live preview passes a fresh closure each render).
	const onErrorRef = useRef(onError);
	onErrorRef.current = onError;

	useEffect(() => {
		let cancelled = false;

		setMod(null);
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
				onErrorRef.current?.(null);
			}
		}).catch((err: unknown) => {
			if (!cancelled) {
				const message = err instanceof Error ? err.message : "Failed to load action.";
				console.error("Action module load failed:", err);
				setMod(null);
				setError(message);
				onErrorRef.current?.(message);
			}
		});

		return () => {
			cancelled = true;
		};
	}, [assetUrl, source]);

	if (error) {
		// When a caller handles errors (live preview), render nothing and let it
		// show the message; otherwise show a self-contained failure panel.
		if (onError) {
			return null;
		}

		return (
			<Alert tone="danger" title="This action couldn't load">
				<div className="text-text-2">
					Its custom code failed to load. It may need to be rebuilt.
				</div>
				<pre className="mt-3 max-h-40 overflow-auto rounded bg-surface-2 p-2 font-mono text-[11.5px] text-text-3">
					{error}
				</pre>
			</Alert>
		);
	}

	if (!mod) {
		return (
			<div className="rounded-md border border-dashed border-border bg-surface-2 p-3 text-[12px] text-text-3">
				Loading action…
			</div>
		);
	}

	const Component = mod.Component;

	return (
		<ErrorBoundary resetKey={source ?? assetUrl}>
			<Component host={host} />
		</ErrorBoundary>
	);
};
