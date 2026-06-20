import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Save } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { FormShell } from "@/components/ui/FormShell";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import {
	configureApi,
	type GeocodingConfig,
	type GeocodingServiceId,
} from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

const inputClass =
	"w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent-ring";

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
		queryKey: ["configure", "geocoding"],
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
			queryClient.setQueryData(["configure", "geocoding"], fresh);
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
			{detailQ.isLoading && <p className="text-[12.5px] text-text-3">Loading…</p>}

			{detailQ.error && <ErrorPanel error={detailQ.error} />}

			{draft && (
				<FormShell
					onSubmit={onSubmit}
					footer={
						<Button
							variant="primary"
							type="submit"
							icon={<Save size={13} />}
							disabled={saveMutation.isPending}
						>
							{saveMutation.isPending ? "Saving…" : "Save"}
						</Button>
					}
				>
					{generalError && <ErrorPanel error={new Error(generalError)} />}

					<Field label="Service">
						<select
							value={draft.service}
							onChange={(e) =>
								setDraft({
									...draft,
									service: e.target.value as GeocodingServiceId,
								})
							}
							className={inputClass}
						>
							{SERVICES.map((s) => (
								<option key={s.id} value={s.id}>
									{s.label}
								</option>
							))}
						</select>
						<p className="mt-1 text-[11.5px] text-text-3">{active.help}</p>
					</Field>

					<div className="mt-4 space-y-3">
						{draft.service === "google" && (
							<Field label="Google Maps API key">
								<input
									className={inputClass}
									value={draft.google_key}
									onChange={(e) =>
										setDraft({ ...draft, google_key: e.target.value })
									}
								/>
							</Field>
						)}

						{draft.service === "bing" && (
							<Field label="Bing Maps API key">
								<input
									className={inputClass}
									value={draft.bing_key}
									onChange={(e) =>
										setDraft({ ...draft, bing_key: e.target.value })
									}
								/>
							</Field>
						)}

						{draft.service === "mapquest" && (
							<Field label="MapQuest API key">
								<input
									className={inputClass}
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
