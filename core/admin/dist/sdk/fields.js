// SDK import-map shim for `@bigtree/fields` — the field-drawing surface. Backed
// by the global registerSdk() sets (src/sdk/fields.ts).
const fields = globalThis.__BIGTREE_SDK__ && globalThis.__BIGTREE_SDK__["@bigtree/fields"];

if (!fields) {
	throw new Error("BigTree SDK not initialized — `@bigtree/fields` is unavailable.");
}

export default fields;
export const { FieldRenderer } = fields;
