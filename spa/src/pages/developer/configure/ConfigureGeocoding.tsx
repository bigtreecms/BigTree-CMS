import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Save } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Button } from "@/components/ui/Button";
import { TextInput } from "@/components/ui/TextInput";
import { Field } from "@/components/ui/Field";
import { SelectField } from "@/components/ui/SelectField";
import { FormShell } from "@/components/ui/FormShell";
import { LoadingText } from "@/components/ui/LoadingText";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import {
	configureApi,
	type GeocodingConfig,
	type GeocodingServiceId,
} from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";

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
					target="_blank"
					rel="noreferrer"
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
	const queryClient = useQueryClient();
	const detailQ = useQuery({
		queryKey: queryKeys.configure.geocoding(),
		queryFn: () => configureApi.geocoding.get(),
	});

	const [draft, setDraft] = useState<GeocodingConfig | null>(null);
	const [generalError, setGeneralError] = useState<string | null>(null);

	useEffect(() => {
		if (detailQ.data) {
			setDraft({ ...detailQ.data });
		}
	}, [detailQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: GeocodingConfig) => configureApi.geocoding.update(next),
		onSuccess: (fresh) => {
			queryClient.setQueryData(queryKeys.configure.geocoding(), fresh);
			toast.success("Geocoding service updated");
			setGeneralError(null);
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message
					? err.message
					: "Could not save geocoding config";
			setGeneralError(msg);
			toast.error(msg);
		},
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
			title="Geocoding"
			sub="Powers address → lat/lng lookups for Geocoding fields and the Google Maps Static API thumbnails."
		>
			{detailQ.isLoading && <LoadingText />}

			{detailQ.error && <ErrorPanel error={detailQ.error} />}

			{draft && (
				<FormShell
					onSubmit={onSubmit}
					footer={
						<Button
							variant="primary"
							type="submit"
							icon={<Save size={13} />}
							loading={saveMutation.isPending}
							loadingLabel="Saving…"
						>
							Save
						</Button>
					}
				>
					{generalError && <ErrorPanel error={new Error(generalError)} />}

					<SelectField
						label="Service"
						value={draft.service}
						onChange={(v) => setDraft({ ...draft, service: v as GeocodingServiceId })}
						options={SERVICES.map((s) => ({ value: s.id, label: s.label }))}
						hint={active.help}
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
