import { useEffect, useRef, useState } from "react";

import { LoadingText } from "@/components/ui/LoadingText";
import { StubField } from "@/renderer/fields/StubField";
import type { FieldComponentProps } from "@/renderer/fields/types";

import type { FieldHost, FieldInstance, FieldModule } from "./fieldModuleContract";
import { loadFieldModule, loadFieldModuleFromSource } from "./fieldModuleLoader";

interface ModuleFieldProps extends FieldComponentProps {
	/** URL of the module bundle (extension-delivered, schema.asset_url). */
	assetUrl?: string;
	/** Inline source (locally authored, schema.module_source) — run in-context. */
	source?: string;
	/**
	 * Called with the load/render error message, or null on success. Lets the
	 * live preview surface compile errors; when provided, the failure is shown by
	 * the caller and this component renders nothing instead of a stub.
	 */
	onError?: (message: string | null) => void;
}

/**
 * Renders a `module` field type in-context: imports the module (from inline
 * `source` or an `assetUrl`) and mounts it through the imperative field contract
 * (`fieldModuleContract.ts`). Local source and trusted (`core`/`verified`)
 * bundles run here; untrusted marketplace bundles go through the iframe sandbox
 * (CustomField enforces that — this component assumes a trusted module).
 *
 * Module render/update calls run inside try/catch (they happen in effects, not
 * React render, so an ErrorBoundary wouldn't catch them); any failure falls back
 * to <StubField /> (preserving the value), or to `onError` when a caller wants
 * to handle it.
 */
export const ModuleField = ({ assetUrl, source, onError, ...props }: ModuleFieldProps) => {
	const containerRef = useRef<HTMLDivElement>(null);
	const instanceRef = useRef<FieldInstance | null>(null);
	const moduleRef = useRef<FieldModule | null>(null);
	const [status, setStatus] = useState<"loading" | "ready" | "error">("loading");

	// Keep the latest props reachable from the imperative host without
	// re-running the mount effect (which would tear the module down and back up).
	const propsRef = useRef(props);
	propsRef.current = props;

	// Stable refs so the module/host never hold a stale closure.
	const onChangeRef = useRef((next: unknown) => propsRef.current.onChange(next));
	const onErrorRef = useRef(onError);
	onErrorRef.current = onError;

	const buildHost = (): FieldHost => ({
		element: containerRef.current as HTMLElement,
		value: propsRef.current.value,
		field: propsRef.current.field,
		disabled: !!propsRef.current.disabled,
		error: propsRef.current.error,
		onChange: onChangeRef.current,
	});

	// Mount / unmount the module when its source or asset URL changes.
	useEffect(() => {
		let cancelled = false;

		setStatus("loading");

		const load =
			source != null
				? loadFieldModuleFromSource(source)
				: assetUrl
					? loadFieldModule(assetUrl)
					: Promise.reject(new Error("No module source or asset URL provided."));

		load.then((mod) => {
			if (cancelled || !containerRef.current) {
				return;
			}

			moduleRef.current = mod;
			instanceRef.current = mod.render(buildHost()) || null;
			setStatus("ready");
			onErrorRef.current?.(null);
		}).catch((err: unknown) => {
			if (!cancelled) {
				const message = err instanceof Error ? err.message : "Failed to load module.";
				console.error("Field module load failed:", err);
				setStatus("error");
				onErrorRef.current?.(message);
			}
		});

		return () => {
			cancelled = true;

			try {
				instanceRef.current?.destroy?.();
			} catch (err) {
				console.error("Field module destroy failed:", err);
			}

			instanceRef.current = null;
			moduleRef.current = null;
		};
	}, [assetUrl, source]);

	// Push value / disabled / error changes into the mounted instance.
	useEffect(() => {
		if (status !== "ready") {
			return;
		}

		try {
			instanceRef.current?.update?.(buildHost());
		} catch (err) {
			console.error("Field module update failed:", err);
			setStatus("error");
			onErrorRef.current?.(err instanceof Error ? err.message : "Module update failed.");
		}
	}, [props.value, props.disabled, props.error, status]);

	if (status === "error") {
		// When a caller handles errors (live preview), render nothing and let it
		// show the message; otherwise fall back to the value-preserving stub.
		return onError ? null : <StubField {...props} />;
	}

	return (
		<div>
			<div ref={containerRef} />
			{status === "loading" && <LoadingText boxed label="Loading field…" />}
		</div>
	);
};
