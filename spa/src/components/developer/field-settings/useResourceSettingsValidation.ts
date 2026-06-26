import { useMemo } from "react";
import { useQueries } from "@tanstack/react-query";

import {
	fieldTypesApi,
	type FieldTypeSchema,
	type FieldUseCase,
} from "@/api/endpoints/field-types";

import type { ResourceEntry } from "@/components/developer/ResourceDesigner";

import { validateFieldSettings } from "./validate";
import { queryKeys } from "@/lib/queryKeys";

const toObject = (value: unknown): Record<string, unknown> =>
	value && typeof value === "object" && !Array.isArray(value)
		? (value as Record<string, unknown>)
		: {};

/**
 * Resolve the settings schema for every distinct field type used in a resource
 * array, then expose a `validate()` that returns required-setting errors keyed
 * by entry index (`{ [index]: { [settingId]: message } }`).
 *
 * Schemas are fetched through the same `["field-types","schema",type]` query
 * keys `FieldSettingsEditor` uses, so panels the user already opened hit the
 * cache. A schema that hasn't resolved yet contributes no errors (the server
 * still validates on save), so a slow fetch can never produce a false block.
 */
export const useResourceSettingsValidation = (
	resources: ResourceEntry[] | undefined,
	useCase: FieldUseCase
) => {
	const types = useMemo(() => {
		const set = new Set<string>();

		for (const entry of resources ?? []) {
			set.add(entry.type || "text");
		}

		return [...set];
	}, [resources]);

	const queries = useQueries({
		queries: types.map((type) => ({
			queryKey: queryKeys.fieldTypes.schema(type),
			queryFn: () => fieldTypesApi.getSchema(type),
			staleTime: 5 * 60 * 1000,
		})),
	});

	const schemaByType = useMemo(() => {
		const map: Record<string, FieldTypeSchema | undefined> = {};

		types.forEach((type, i) => {
			map[type] = queries[i]?.data;
		});

		return map;
	}, [types, queries]);

	const validate = (): Record<number, Record<string, string>> => {
		const out: Record<number, Record<string, string>> = {};

		(resources ?? []).forEach((entry, index) => {
			const schema = schemaByType[entry.type || "text"];
			const errors = validateFieldSettings(
				schema?.settings_schema,
				toObject(entry.settings),
				useCase
			);

			if (Object.keys(errors).length > 0) {
				out[index] = errors;
			}
		});

		return out;
	};

	return { validate };
};
