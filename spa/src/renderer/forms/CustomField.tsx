import { useQuery } from "@tanstack/react-query";

import { fieldTypesApi } from "@/api/endpoints/field-types";
import { StubField } from "@/renderer/fields/StubField";
import type { FieldComponentProps } from "@/renderer/fields/types";

import { DeclarativeField } from "./DeclarativeField";
import { HOST_CONTRACT_VERSION } from "./fieldModuleContract";
import { ModuleField } from "./ModuleField";
import { SandboxedField } from "./SandboxedField";

/**
 * Renderer for field types with no built-in component — i.e. custom/extension
 * types. Resolves the type's schema (GET /field-types/{id}/schema, shared cache
 * with the settings designer) and dispatches on its `render` contract:
 *
 *  - declarative → <DeclarativeField /> (Tier 1, composed from primitives)
 *  - module + trusted (core/verified) → <ModuleField /> (Tier 2, loaded in-context)
 *  - module + marketplace → <StubField /> until the iframe sandbox ships (step 5)
 *  - server / unknown → <StubField /> (value preserved)
 *
 * The value always round-trips, so an unresolved or still-loading type never
 * loses data. See spa/.custom-field-types-design.md.
 */
export const CustomField = (props: FieldComponentProps) => {
	const type = props.field.type;

	const schemaQ = useQuery({
		queryKey: ["field-types", "schema", type],
		queryFn: () => fieldTypesApi.getSchema(type),
		enabled: type !== "",
		staleTime: 5 * 60 * 1000,
	});

	if (schemaQ.isLoading) {
		return (
			<div className="rounded-md border border-dashed border-border bg-surface-2 p-3 text-[12px] text-text-3">
				Loading field…
			</div>
		);
	}

	const schema = schemaQ.data;

	if (
		schema?.render === "declarative" &&
		Array.isArray(schema.input_schema) &&
		schema.input_schema.length > 0
	) {
		return <DeclarativeField {...props} inputSchema={schema.input_schema} />;
	}

	if (schema?.render === "module" && schema.asset_url) {
		const trust = schema.trust ?? "marketplace";

		// Trusted modules load in-context; untrusted (marketplace) modules run in
		// the opaque-origin iframe sandbox, which verifies their SRI hash first.
		if (trust === "core" || trust === "verified") {
			return <ModuleField {...props} assetUrl={schema.asset_url} />;
		}

		return (
			<SandboxedField
				key={schema.asset_url}
				{...props}
				assetUrl={schema.asset_url}
				integrity={schema.integrity ?? ""}
				contractVersion={HOST_CONTRACT_VERSION}
			/>
		);
	}

	return <StubField {...props} />;
};
