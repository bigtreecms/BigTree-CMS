import { HOST_CONTRACT_VERSION, isActionModule, type ActionModule } from "./actionModuleContract";

/**
 * Loads custom action modules. Ports fieldModuleLoader.ts: an extension-delivered
 * bundle is `import()`ed by URL and cached (one network load per asset); locally
 * authored source is turned into a blob and imported in-context (no cache — the
 * source changes as the author edits and the live preview re-imports often).
 */
const cache = new Map<string, Promise<ActionModule>>();

/** Validate an imported module's default export against the host contract. */
const validateModule = (mod: { default?: unknown }): ActionModule => {
	const def = mod.default;

	if (!isActionModule(def)) {
		throw new Error("Action module did not default-export a valid action contract.");
	}

	if (Math.floor(def.contractVersion) > HOST_CONTRACT_VERSION) {
		throw new Error(
			`Action module needs contract v${def.contractVersion}; host supports v${HOST_CONTRACT_VERSION}.`
		);
	}

	return def;
};

/**
 * Load an extension module bundle from a URL, caching the `import()` per asset URL
 * so every mount shares one network load. A rejected load is evicted so a later
 * mount can retry rather than being stuck on a stale failure.
 */
export const loadActionModule = (assetUrl: string): Promise<ActionModule> => {
	let pending = cache.get(assetUrl);

	if (!pending) {
		pending = import(/* @vite-ignore */ assetUrl).then(validateModule);
		pending.catch(() => cache.delete(assetUrl));
		cache.set(assetUrl, pending);
	}

	return pending;
};

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

/**
 * Load a module from inline source (locally authored, implicitly trusted). The
 * source is compiled, turned into a blob and imported in-context — no network, no
 * caching (the source changes as the author edits; the live preview re-imports).
 */
export const loadActionModuleFromSource = async (source: string): Promise<ActionModule> => {
	const compiled = await compileSource(source);
	const url = URL.createObjectURL(new Blob([compiled], { type: "text/javascript" }));

	try {
		return validateModule(await import(/* @vite-ignore */ url));
	} finally {
		URL.revokeObjectURL(url);
	}
};
