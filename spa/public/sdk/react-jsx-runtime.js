// SDK import-map shim for the automatic JSX runtime (Sucrase compiles author JSX
// to imports from "react/jsx-runtime"). Backed by the global registerSdk() sets.
const rt = globalThis.__BIGTREE_SDK__ && globalThis.__BIGTREE_SDK__["react/jsx-runtime"];

if (!rt) {
	throw new Error("BigTree SDK not initialized — `react/jsx-runtime` is unavailable.");
}

export const Fragment = rt.Fragment;
export const jsx = rt.jsx;
export const jsxs = rt.jsxs;
