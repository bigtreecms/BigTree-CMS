import { HOST_CONTRACT_VERSION, isFieldModule, type FieldModule } from "./fieldModuleContract";

/**
 * Caches the `import()` of each field-type module bundle (keyed by asset URL) so
 * every instance of the same type shares one network load. A rejected load is
 * evicted so a later mount can retry rather than being stuck on a stale failure.
 */
const cache = new Map<string, Promise<FieldModule>>();

/** Validate an imported module's default export against the host contract. */
const validateModule = (mod: { default?: unknown }): FieldModule => {
	const def = mod.default;

	if (!isFieldModule(def)) {
		throw new Error("Field module did not default-export a valid field contract.");
	}

	if (Math.floor(def.contractVersion) > HOST_CONTRACT_VERSION) {
		throw new Error(
			`Field module needs contract v${def.contractVersion}; host supports v${HOST_CONTRACT_VERSION}.`
		);
	}

	return def;
};

/**
 * Load an extension module bundle from a URL, caching the `import()` per asset
 * URL so every instance shares one network load. A rejected load is evicted so a
 * later mount can retry rather than being stuck on a stale failure.
 */
export const loadFieldModule = (assetUrl: string): Promise<FieldModule> => {
	let pending = cache.get(assetUrl);

	if (!pending) {
		pending = import(/* @vite-ignore */ assetUrl).then(validateModule);
		pending.catch(() => cache.delete(assetUrl));
		cache.set(assetUrl, pending);
	}

	return pending;
};

/**
 * Load a module from inline source (locally authored, implicitly trusted). The
 * source is turned into a blob and imported in-context — no network, no caching
 * (the source changes as the author edits, and live preview re-imports often).
 */
export const loadFieldModuleFromSource = async (source: string): Promise<FieldModule> => {
	const url = URL.createObjectURL(new Blob([source], { type: "text/javascript" }));

	try {
		return validateModule(await import(/* @vite-ignore */ url));
	} finally {
		URL.revokeObjectURL(url);
	}
};
