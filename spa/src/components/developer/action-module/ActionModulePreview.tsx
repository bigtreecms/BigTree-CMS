import { useMemo, useState } from "react";

import { useDebouncedValue } from "@/hooks/useDebouncedValue";
import type { ModuleAction } from "@/api/endpoints/modules";
import { toast } from "@/lib/toast";
import { ActionRunner } from "@/renderer/actions/ActionRunner";
import type { ActionHost } from "@/renderer/actions/actionModuleContract";
import { Alert } from "@/components/ui/Alert";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

interface ActionModulePreviewProps {
	source: string;
	/** Action name + route, used to populate the previewed host.context. */
	name: string;
	route: string;
}

interface RecordedCall {
	fn: string;
	payload: unknown;
}

/**
 * Live preview of a custom action module. Debounces the source, compiles +
 * imports it in-context (exactly how it runs for real), and renders it with a
 * mocked host so the author can interact with their UI before saving. invoke /
 * invokeAction are stubbed — they don't hit the server (the handler may not exist
 * yet) but record the payload that *would* be submitted, so the author can verify
 * it. Compile/runtime errors surface inline.
 */
export const ActionModulePreview = ({ source, name, route }: ActionModulePreviewProps) => {
	const debounced = useDebouncedValue(source, 400);
	const [error, setError] = useState<string | null>(null);
	const [lastCall, setLastCall] = useState<RecordedCall | null>(null);

	const host = useMemo<ActionHost>(() => {
		const action = {
			id: "preview",
			name: name || "Preview",
			route: route || "preview",
			class: "",
			in_nav: false,
			level: 0,
			position: 0,
			form: null,
			view: null,
			report: null,
			render: "module",
		} as ModuleAction;

		const record = (fn: string, payload: unknown) => {
			setLastCall({ fn, payload });

			return Promise.resolve({ preview: true, payload });
		};

		return {
			context: { moduleId: "preview", action, params: {}, userLevel: 2 },
			invoke: (payload?: unknown) => record("invoke", payload),
			invokeAction: (targetRoute: string, payload?: unknown) =>
				record(`invokeAction(${targetRoute})`, payload),
			navigate: (to: string) => toast.info(`navigate → ${to}`),
			toast: (message: string, kind = "info") => toast[kind](message),
		};
	}, [name, route]);

	if (!debounced.trim()) {
		return <InlineEmpty pad="md">Write some code to see a live preview.</InlineEmpty>;
	}

	return (
		<div className="space-y-2">
			<div className="rounded-md border border-border bg-surface p-3">
				{/* key forces a clean remount on source change so the module re-imports */}
				<ActionRunner key={debounced} host={host} source={debounced} onError={setError} />
			</div>

			{error && (
				<Alert tone="danger" mono>
					{error}
				</Alert>
			)}

			{lastCall && (
				<div className="text-[11px] text-text-3">
					Last submit — <span className="font-mono text-text-2">{lastCall.fn}</span>:{" "}
					<span className="font-mono text-text-2">
						{JSON.stringify(lastCall.payload)}
					</span>
				</div>
			)}
		</div>
	);
};
