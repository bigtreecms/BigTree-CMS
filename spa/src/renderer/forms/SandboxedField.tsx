import { useEffect, useRef, useState } from "react";

import { LoadingText } from "@/components/ui/LoadingText";
import { StubField } from "@/renderer/fields/StubField";
import type { FieldComponentProps } from "@/renderer/fields/types";

import {
	FIELD_SANDBOX_TARGET_ORIGIN,
	FIELD_SANDBOX_URL,
	isSandboxMessage,
	type SandboxOutbound,
} from "./fieldSandboxProtocol";

/**
 * Distributes over a union before omitting, so each member keeps its own keys.
 * A plain Omit<Union, K> collapses to only the keys common to all members.
 */
type DistributiveOmit<T, K extends PropertyKey> = T extends unknown ? Omit<T, K> : never;

interface SandboxedFieldProps extends FieldComponentProps {
	assetUrl: string;
	/** Highest module contract version the host supports. */
	contractVersion: number;
	/** SRI string (e.g. "sha384-…"); the sandbox refuses unsigned modules. */
	integrity: string;
}

/**
 * Renders an untrusted `marketplace` module field type inside a sandboxed
 * iframe (see fieldSandboxProtocol.ts). The third-party code runs at an opaque
 * origin with no access to the admin session/DOM; it talks to the form only
 * through the controlled value/onChange relayed over postMessage. The sandbox
 * verifies the module's SRI hash before executing it. Any failure (bad
 * integrity, load error, version mismatch) falls back to <StubField />, which
 * preserves the value.
 */
export const SandboxedField = ({
	assetUrl,
	integrity,
	contractVersion,
	...props
}: SandboxedFieldProps) => {
	const iframeRef = useRef<HTMLIFrameElement>(null);
	const channelRef = useRef<string>("");

	if (!channelRef.current) {
		channelRef.current = crypto.randomUUID();
	}

	const [status, setStatus] = useState<"loading" | "ready" | "error">("loading");
	const [height, setHeight] = useState(40);

	// Latest props reachable from the message handler without re-subscribing.
	const propsRef = useRef(props);
	propsRef.current = props;

	const post = (msg: DistributiveOmit<SandboxOutbound, "channel">) => {
		iframeRef.current?.contentWindow?.postMessage(
			{ ...msg, channel: channelRef.current },
			FIELD_SANDBOX_TARGET_ORIGIN
		);
	};

	useEffect(() => {
		const channel = channelRef.current;

		const onMessage = (event: MessageEvent) => {
			if (event.source !== iframeRef.current?.contentWindow) {
				return;
			}

			if (!isSandboxMessage(event.data, channel)) {
				return;
			}

			const message = event.data;

			if (message.type === "ready") {
				post({
					type: "init",
					assetUrl,
					integrity,
					contractVersion,
					value: propsRef.current.value,
					field: propsRef.current.field,
					disabled: !!propsRef.current.disabled,
					error: propsRef.current.error,
				});
			} else if (message.type === "mounted") {
				setStatus("ready");
			} else if (message.type === "change") {
				propsRef.current.onChange(message.value);
			} else if (message.type === "resize") {
				setHeight(Math.max(0, Number(message.height) || 0));
			} else if (message.type === "error") {
				console.error("Sandboxed field error:", message.message);
				setStatus("error");
			}
		};

		window.addEventListener("message", onMessage);

		return () => window.removeEventListener("message", onMessage);
	}, [assetUrl, integrity, contractVersion]);

	// Relay value / disabled / error changes into the sandbox.
	useEffect(() => {
		if (status === "ready") {
			post({
				type: "update",
				value: props.value,
				disabled: !!props.disabled,
				error: props.error,
			});
		}
	}, [props.value, props.disabled, props.error, status]);

	if (status === "error") {
		return <StubField {...props} />;
	}

	return (
		<div>
			<iframe
				className="block w-full rounded-md border border-border bg-surface"
				ref={iframeRef}
				sandbox="allow-scripts"
				src={`${FIELD_SANDBOX_URL}#c=${channelRef.current}`}
				style={{ height }}
				title="Custom field"
			/>
			{status === "loading" && (
				<LoadingText className="mt-1 block" label="Loading field…" size="sm" />
			)}
		</div>
	);
};
