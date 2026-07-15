import { Save } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Button } from "@/components/ui/Button";
import { TextInput } from "@/components/ui/TextInput";
import { Field } from "@/components/ui/Field";
import { SelectField } from "@/components/ui/SelectField";
import { FormShell } from "@/components/ui/FormShell";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import {
	configureApi,
	type GeocodingConfig,
	type GeocodingServiceId,
} from "@/api/endpoints/configure";

import { queryKeys } from "@/lib/queryKeys";
import { useConfigDraft } from "@/hooks/useConfigDraft";

const SERVICES: Array<{ id: GeocodingServiceId; label: string; help: React.ReactNode }> = [
	{
		id: "",
		label: "Disabled",
		help: "Geocoding fields will fail silently until a service is picked.",
	},
	{
		id: "google",
		label: "Google Maps",
		help: (
			<>
				Requires a Google Maps API key. See{" "}
				<a
					className="text-accent underline"
					href="https://developers.google.com/maps/documentation/geocoding/start"
					rel="noreferrer"
					target="_blank"
				>
					Getting Started
				</a>
				.
			</>
		),
	},
	{
		id: "bing",
		label: "Bing Maps",
		help: "Requires a Bing Maps API key (Basic tier is free).",
	},
	{
		id: "mapquest",
		label: "MapQuest",
		help: "Requires a MapQuest Developer key.",
	},
];

export const ConfigureGeocoding = () => {
	const { detailQ, draft, setDraft, generalError, saveMutation } = useConfigDraft({
		queryKey: queryKeys.configure.geocoding(),
		queryFn: () => configureApi.geocoding.get(),
		seed: (data: GeocodingConfig) => ({ ...data }),
		save: (next: GeocodingConfig) => configureApi.geocoding.update(next),
		successMessage: "Geocoding service updated",
		errorMessage: "Could not save geocoding config",
	});

	const onSubmit = (e: React.FormEvent) => {
		e.preventDefault();

		if (!draft) {
			return;
		}

		saveMutation.mutate(draft);
	};

	const active = SERVICES.find((s) => s.id === draft?.service) ?? SERVICES[0]!;

	return (
		<ConfigureLayout
			query={detailQ}
			sub="Powers address → lat/lng lookups for Geocoding fields and the Google Maps Static API thumbnails."
			title="Geocoding"
		>
			{draft && (
				<FormShell
					footer={
						<Button
							icon={<Save size={13} />}
							loading={saveMutation.isPending}
							loadingLabel="Saving…"
							type="submit"
							variant="primary"
						>
							Save
						</Button>
					}
					onSubmit={onSubmit}
				>
					{generalError && <ErrorPanel message={generalError} />}

					<SelectField
						hint={active.help}
						label="Service"
						options={SERVICES.map((s) => ({ value: s.id, label: s.label }))}
						value={draft.service}
						onChange={(v) => setDraft({ ...draft, service: v as GeocodingServiceId })}
					/>

					<div className="mt-4 space-y-3">
						{draft.service === "google" && (
							<Field label="Google Maps API key">
								<TextInput
									value={draft.google_key}
									onChange={(e) =>
										setDraft({ ...draft, google_key: e.target.value })
									}
								/>
							</Field>
						)}

						{draft.service === "bing" && (
							<Field label="Bing Maps API key">
								<TextInput
									value={draft.bing_key}
									onChange={(e) =>
										setDraft({ ...draft, bing_key: e.target.value })
									}
								/>
							</Field>
						)}

						{draft.service === "mapquest" && (
							<Field label="MapQuest API key">
								<TextInput
									value={draft.mapquest_key}
									onChange={(e) =>
										setDraft({ ...draft, mapquest_key: e.target.value })
									}
								/>
							</Field>
						)}
					</div>
				</FormShell>
			)}
		</ConfigureLayout>
	);
};
