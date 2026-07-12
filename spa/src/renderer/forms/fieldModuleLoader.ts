import { createModuleLoader } from "@/renderer/moduleLoader";

import { HOST_CONTRACT_VERSION, isFieldModule, type FieldModule } from "./fieldModuleContract";

const loader = createModuleLoader<FieldModule>({
	hostContractVersion: HOST_CONTRACT_VERSION,
	isModule: isFieldModule,
	typeName: "Field",
});

/**
 * Load an extension module bundle from a URL, caching the `import()` per asset
 * URL so every instance shares one network load. A rejected load is evicted so a
 * later mount can retry rather than being stuck on a stale failure.
 */
export const loadFieldModule = (assetUrl: string): Promise<FieldModule> =>
	loader.loadFromUrl(assetUrl);

/**
 * Load a module from inline source (locally authored, implicitly trusted). The
 * source is turned into a blob and imported in-context — no network, no caching
 * (the source changes as the author edits, and live preview re-imports often).
 */
export const loadFieldModuleFromSource = (source: string): Promise<FieldModule> =>
	loader.loadFromSource(source);
