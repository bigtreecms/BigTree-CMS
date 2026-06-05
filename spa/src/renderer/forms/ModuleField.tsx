import { useEffect, useRef, useState } from "react";

import { StubField } from "@/renderer/fields/StubField";
import type { FieldComponentProps } from "@/renderer/fields/types";

import type { FieldHost, FieldInstance, FieldModule } from "./fieldModuleContract";
import { loadFieldModule } from "./fieldModuleLoader";

interface ModuleFieldProps extends FieldComponentProps {
	/** URL of the module bundle (schema.asset_url). */
	assetUrl: string;
}

/**
 * Renders a `module` field type in-context: dynamically imports the module
 * bundle and mounts it through the imperative field contract
 * (`fieldModuleContract.ts`). Intended for trusted (`core` / `verified`) types
 * only — marketplace modules must go through the iframe sandbox (step 5);
 * CustomField enforces that, this component assumes it.
 *
 * Module render/update calls run inside try/catch (they happen in effects, not
 * React render, so an ErrorBoundary wouldn't catch them); any failure falls back
 * to <StubField />, which preserves the value.
 */
export const ModuleField = ({ assetUrl, ...props }: ModuleFieldProps) => {
	const containerRef = useRef<HTMLDivElement>(null);
	const instanceRef = useRef<FieldInstance | null>(null);
	const moduleRef = useRef<FieldModule | null>(null);
	const [status, setStatus] = useState<"loading" | "ready" | "error">("loading");

	// Keep the latest props reachable from the imperative host without
	// re-running the mount effect (which would tear the module down and back up).
	const propsRef = useRef(props);
	propsRef.current = props;

	// Stable onChange so the module never holds a stale closure.
	const onChangeRef = useRef((next: unknown) => propsRef.current.onChange(next));

	const buildHost = (): FieldHost => ({
		element: containerRef.current as HTMLElement,
		value: propsRef.current.value,
		field: propsRef.current.field,
		disabled: !!propsRef.current.disabled,
		error: propsRef.current.error,
		onChange: onChangeRef.current,
	});

	// Mount / unmount the module when the asset URL changes.
	useEffect(() => {
		let cancelled = false;

		setStatus("loading");

		loadFieldModule(assetUrl)
			.then((mod) => {
				if (cancelled || !containerRef.current) {
					return;
				}

				moduleRef.current = mod;
				instanceRef.current = mod.render(buildHost()) || null;
				setStatus("ready");
			})
			.catch((err) => {
				if (!cancelled) {
					console.error("Field module load failed:", err);
					setStatus("error");
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
	}, [assetUrl]);

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
		}
	}, [props.value, props.disabled, props.error, status]);

	if (status === "error") {
		return <StubField {...props} />;
	}

	return (
		<div>
			<div ref={containerRef} />
			{status === "loading" && (
				<div className="rounded-md border border-dashed border-border bg-surface-2 p-3 text-[12px] text-text-3">
					Loading field…
				</div>
			)}
		</div>
	);
};
