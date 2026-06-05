import { useQuery } from "@tanstack/react-query";

import { applySettingDefaults, fieldTypesApi } from "@/api/endpoints/field-types";
import { StubField } from "@/renderer/fields/StubField";
import { settingsOf, type FieldComponentProps } from "@/renderer/fields/types";

import { DeclarativeField } from "./DeclarativeField";
import { HOST_CONTRACT_VERSION } from "./fieldModuleContract";
import { ModuleField } from "./ModuleField";
import { SandboxedField } from "./SandboxedField";

/** Shown for legacy draw.php field types, which the SPA no longer renders. */
const LEGACY_NOTE =
	"This is a legacy field type. It needs to be rebuilt as a JavaScript module (.mjs) " +
	"or a declarative field type to be editable here — its value is preserved.";

/**
 * Renderer for field types with no built-in component — i.e. custom/extension
 * types. Resolves the type's schema (GET /field-types/{id}/schema, shared cache
 * with the settings designer) and dispatches on its `render` contract:
 *
 *  - declarative → <DeclarativeField /> (Tier 1, composed from primitives)
 *  - module + trusted (core/verified) → <ModuleField /> (Tier 2, loaded in-context)
 *  - module + marketplace → <SandboxedField /> (Tier 2, opaque-origin iframe)
 *  - server (legacy draw.php) / unknown → <StubField /> (value preserved)
 *
 * Legacy draw.php types are no longer rendered — they show a stub prompting a
 * port to the .mjs/declarative model. The value always round-trips, so an
 * unresolved or still-loading type never loses data. See
 * spa/.custom-field-types-design.md.
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

	if (schema?.render === "module") {
		// Overlay the schema's setting defaults under the field's configured
		// settings so the module sees a default when a setting wasn't filled in.
		const fieldWithDefaults = {
			...props.field,
			settings: applySettingDefaults(schema.settings_schema, settingsOf(props.field)),
		};
		const moduleProps = { ...props, field: fieldWithDefaults };

		// Local, implicitly-trusted source — run in-context from the stored code.
		if (schema.module_source) {
			return <ModuleField {...moduleProps} source={schema.module_source} />;
		}

		if (schema.asset_url) {
			const trust = schema.trust ?? "marketplace";

			// Extension modules: trusted ones load in-context; untrusted
			// (marketplace) ones run in the opaque-origin iframe sandbox, which
			// verifies their SRI hash first.
			if (trust === "local" || trust === "core" || trust === "verified") {
				return <ModuleField {...moduleProps} assetUrl={schema.asset_url} />;
			}

			return (
				<SandboxedField
					key={schema.asset_url}
					{...moduleProps}
					assetUrl={schema.asset_url}
					integrity={schema.integrity ?? ""}
					contractVersion={HOST_CONTRACT_VERSION}
				/>
			);
		}
	}

	// Legacy draw.php types (render "server"), or a type whose schema didn't
	// resolve: not rendered in the SPA — prompt a port to .mjs/declarative.
	if (schema?.render === "server" || schemaQ.isError) {
		return <StubField {...props} note={LEGACY_NOTE} />;
	}

	return <StubField {...props} />;
};
