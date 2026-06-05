import type { ModuleFormField } from "@/api/endpoints/modules";

/**
 * postMessage protocol between the SPA (parent) and the sandboxed iframe that
 * runs an untrusted `marketplace` field-type module (Tier 2 / step 5 of
 * spa/.custom-field-types-design.md).
 *
 * The iframe is served as a static document and embedded with
 * `sandbox="allow-scripts"` (no `allow-same-origin`), so it runs at an opaque
 * origin: it cannot read the admin's cookies, storage, CSRF token, or DOM.
 * Because the origin is opaque, `event.origin` is "null" and can't be matched;
 * instead a random `channel` nonce — set by the parent in the iframe URL hash,
 * so only the parent knows it — authenticates every message, alongside a
 * `event.source === iframe.contentWindow` check.
 *
 * Hardening note: hosting the sandbox document on a *separate domain* is
 * recommended defense-in-depth (set VITE_FIELD_SANDBOX_URL). The opaque-origin
 * sandbox attribute is the primary isolation either way.
 */

/** Where the sandbox bootstrap document is served from. */
export const FIELD_SANDBOX_URL: string =
	import.meta.env.VITE_FIELD_SANDBOX_URL || `${import.meta.env.BASE_URL}field-sandbox/index.html`;

/**
 * targetOrigin for postMessage to the iframe. An opaque-origin sandbox can't be
 * named, so "*" is used — safe here because we always post to one specific
 * `iframe.contentWindow`, and the payload (the field's own value) isn't secret.
 * A separate-domain deployment can pin this via VITE_FIELD_SANDBOX_ORIGIN.
 */
export const FIELD_SANDBOX_TARGET_ORIGIN: string = import.meta.env.VITE_FIELD_SANDBOX_ORIGIN || "*";

/** Parent → sandbox. */
export type SandboxOutbound =
	| {
			channel: string;
			type: "init";
			assetUrl: string;
			integrity: string;
			contractVersion: number;
			value: unknown;
			field: ModuleFormField;
			disabled: boolean;
			error?: string;
	  }
	| {
			channel: string;
			type: "update";
			value: unknown;
			disabled: boolean;
			error?: string;
	  };

/** Sandbox → parent. */
export type SandboxInbound =
	| { channel: string; type: "ready" }
	| { channel: string; type: "mounted" }
	| { channel: string; type: "change"; value: unknown }
	| { channel: string; type: "resize"; height: number }
	| { channel: string; type: "error"; message: string };

/** Narrow an untrusted `MessageEvent.data` to a sandbox message on our channel. */
export const isSandboxMessage = (data: unknown, channel: string): data is SandboxInbound =>
	!!data &&
	typeof data === "object" &&
	(data as { channel?: unknown }).channel === channel &&
	typeof (data as { type?: unknown }).type === "string";
