/**
 * `@bigtree/fields` — the field-drawing surface custom action modules import.
 * Exposes the SPA's own FieldRenderer (and helpers) so a custom action can
 * compose a form from any built-in or custom field type and have it look and
 * behave exactly like the rest of the admin.
 *
 * Exposed via the import map (public/sdk/fields.js → registerSdk() globals).
 *
 * @example
 * ```tsx
 * import { FieldRenderer } from "@bigtree/fields";
 *
 * <FieldRenderer
 *   field={{ column: "title", title: "Title", type: "text", settings: { validation: "required" } }}
 *   value={title}
 *   onChange={setTitle}
 * />
 * ```
 */
export { FieldRenderer } from "@/renderer/forms/FieldRenderer";
export type { FieldComponentProps } from "@/renderer/fields/types";
export { settingsOf } from "@/renderer/fields/types";
export type { ModuleFormField } from "@/api/endpoints/modules";
