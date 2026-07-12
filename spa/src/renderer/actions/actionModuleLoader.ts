import { createModuleLoader } from "@/renderer/moduleLoader";

import { HOST_CONTRACT_VERSION, isActionModule, type ActionModule } from "./actionModuleContract";

/**
 * Compile author source (TSX/JSX) to a browser-runnable ES module. Sucrase is
 * dynamically imported so it only enters the bundle on the local-authoring /
 * local-action path (extension bundles arrive pre-compiled). The automatic JSX
 * runtime + production:true makes it emit imports from "react/jsx-runtime", which
 * the SDK import map resolves to the SPA's React (see public/sdk/).
 */
const compileSource = async (source: string): Promise<string> => {
	const { transform } = await import("sucrase");

	return transform(source, {
		transforms: ["typescript", "jsx"],
		jsxRuntime: "automatic",
		production: true,
	}).code;
};

const loader = createModuleLoader<ActionModule>({
	hostContractVersion: HOST_CONTRACT_VERSION,
	isModule: isActionModule,
	typeName: "Action",
	compile: compileSource,
});

/**
 * Load an extension module bundle from a URL, caching the `import()` per asset URL
 * so every mount shares one network load. A rejected load is evicted so a later
 * mount can retry rather than being stuck on a stale failure.
 */
export const loadActionModule = (assetUrl: string): Promise<ActionModule> =>
	loader.loadFromUrl(assetUrl);

/**
 * Load a module from inline source (locally authored, implicitly trusted). The
 * source is compiled, turned into a blob and imported in-context — no network, no
 * caching (the source changes as the author edits; the live preview re-imports).
 */
export const loadActionModuleFromSource = (source: string): Promise<ActionModule> =>
	loader.loadFromSource(source);
