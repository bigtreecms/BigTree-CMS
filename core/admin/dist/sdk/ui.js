// SDK import-map shim for `@bigtree/ui` — the curated primitives kit. Backed by
// the global registerSdk() sets (src/sdk/ui.tsx).
const ui = globalThis.__BIGTREE_SDK__ && globalThis.__BIGTREE_SDK__["@bigtree/ui"];

if (!ui) {
	throw new Error("BigTree SDK not initialized — `@bigtree/ui` is unavailable.");
}

export default ui;
export const {
	Stack,
	Row,
	Button,
	Note,
	Heading,
	TextInput,
	SelectInput,
	CheckboxInput,
	TextareaInput,
	INPUT_CLASS,
} = ui;
