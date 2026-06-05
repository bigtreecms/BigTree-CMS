import { HOST_CONTRACT_VERSION, isFieldModule, type FieldModule } from "./fieldModuleContract";

/**
 * Caches the `import()` of each field-type module bundle (keyed by asset URL) so
 * every instance of the same type shares one network load. A rejected load is
 * evicted so a later mount can retry rather than being stuck on a stale failure.
 */
const cache = new Map<string, Promise<FieldModule>>();

export const loadFieldModule = (assetUrl: string): Promise<FieldModule> => {
	let pending = cache.get(assetUrl);

	if (!pending) {
		pending = import(/* @vite-ignore */ assetUrl).then((mod: { default?: unknown }) => {
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
		});

		pending.catch(() => cache.delete(assetUrl));
		cache.set(assetUrl, pending);
	}

	return pending;
};
