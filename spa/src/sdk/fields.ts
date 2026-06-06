/**
 * `@bigtree/fields` — the field-drawing surface custom action modules import.
 * Exposes the SPA's own FieldRenderer (and the field props/type) so a custom
 * action can compose a form from any built-in or custom field type and have it
 * look and behave exactly like the rest of the admin.
 *
 * Exposed to author modules via the import map (public/sdk/fields.js → the global
 * the app registers at startup). See registerSdk.ts.
 */
export { FieldRenderer } from "@/renderer/forms/FieldRenderer";
export type { FieldComponentProps } from "@/renderer/fields/types";
export type { ModuleFormField } from "@/api/endpoints/modules";
