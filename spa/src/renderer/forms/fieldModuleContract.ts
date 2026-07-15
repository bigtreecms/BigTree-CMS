import type { ModuleFormField } from "@/api/endpoints/modules";

/**
 * Contract between the SPA host and a third-party field-type module (Tier 2 of
 * spa/.custom-field-types-design.md). The module is a compiled ES module that
 * default-exports a `FieldModule`. It is deliberately framework-agnostic and
 * imperative: the host hands it a DOM `element` plus the controlled value and an
 * `onChange` callback, and the module owns what it renders inside. The same
 * shape carries to the iframe sandbox (step 5) — there the live references
 * (`element` mounting, `onChange`) are proxied over postMessage instead.
 *
 * Authors never bundle React; they render into `element` however they like.
 */

/** The contract version the host implements; a module needing a newer major is refused. */
export const HOST_CONTRACT_VERSION = 1;

/** Everything the host hands a module on render / update. */
export interface FieldHost {
	disabled: boolean;
	/** Mount point the module renders into and owns. */
	element: HTMLElement;
	error?: string;
	/** Field config: key, title, settings, required, … */
	field: ModuleFormField;
	/** Push a new value up to the form. */
	onChange: (next: unknown) => void;
	/** Current controlled value. */
	value: unknown;
}

/** Handle the module returns from render() so the host can update / tear down. */
export interface FieldInstance {
	/** Called on unmount; the module should remove what it added to `element`. */
	destroy?: () => void;
	/** Called when value / disabled / error change without a remount. */
	update?: (host: FieldHost) => void;
}

/** The default export of a field-type module bundle. */
export interface FieldModule {
	contractVersion: number;
	render: (host: FieldHost) => FieldInstance | void;
	/** Optional client-side validation; the server still re-validates. */
	validate?: (value: unknown, field: ModuleFormField) => string | null;
}

/**
 * Identity helper so an author gets full typing on their default export:
 * `export default defineField({ contractVersion: 1, render(host) { … } })`.
 */
export const defineField = (mod: FieldModule): FieldModule => mod;

/** Runtime shape-check before the host trusts an imported module. */
export const isFieldModule = (value: unknown): value is FieldModule =>
	!!value &&
	typeof value === "object" &&
	typeof (value as FieldModule).render === "function" &&
	typeof (value as FieldModule).contractVersion === "number";
