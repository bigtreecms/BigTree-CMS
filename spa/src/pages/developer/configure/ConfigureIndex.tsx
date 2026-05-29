import { Link } from "react-router-dom";
import {
	Cloud,
	CreditCard,
	FileImage,
	FileText,
	LineChart,
	Mail,
	MapPin,
	Share2,
} from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

interface ConfigureCard {
	to: string;
	icon: React.ReactNode;
	title: string;
	description: string;
}

const CARDS: ConfigureCard[] = [
	{
		to: "/developer/configure/email",
		icon: <Mail size={16} />,
		title: "Email",
		description: "SMTP, Mandrill, Mailgun, Postmark, SendGrid — pick the delivery service BigTree uses.",
	},
	{
		to: "/developer/configure/geocoding",
		icon: <MapPin size={16} />,
		title: "Geocoding",
		description: "Google / Bing / MapQuest API key for address → lat-lng lookups.",
	},
	{
		to: "/developer/configure/cloud-storage",
		icon: <Cloud size={16} />,
		title: "Cloud storage",
		description: "Amazon S3 / Rackspace / Google Cloud credentials + default storage backend.",
	},
	{
		to: "/developer/configure/payment-gateway",
		icon: <CreditCard size={16} />,
		title: "Payment gateway",
		description: "Authorize.Net / PayPal / LinkPoint credentials for module forms that take payments.",
	},
	{
		to: "/developer/configure/analytics",
		icon: <LineChart size={16} />,
		title: "Analytics",
		description: "Google Analytics 4 service-account hookup for the dashboard traffic widget.",
	},
	{
		to: "/developer/configure/services",
		icon: <Share2 size={16} />,
		title: "Services",
		description: "Twitter, Instagram, YouTube, Flickr, Salesforce, Disqus, Facebook integrations.",
	},
	{
		to: "/developer/configure/media-presets",
		icon: <FileImage size={16} />,
		title: "Media presets",
		description: "Image crop/thumbnail presets reusable across image fields.",
	},
	{
		to: "/developer/configure/file-metadata",
		icon: <FileText size={16} />,
		title: "File metadata",
		description: "Custom metadata fields collected on uploads (file / image / video).",
	},
];

export const ConfigureIndex = () => (
	<div className="mx-auto max-w-screen-2xl px-6 py-4">
		<Breadcrumb items={[{ label: "Developer", to: "/developer" }, { label: "Configure" }]} />

		<PageHead
			title="Configure"
			sub="Third-party integrations and storage backends BigTree talks to on your behalf."
		/>

		<DeveloperSectionNav />

		<div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
			{CARDS.map((c) => (
				<Link
					key={c.to}
					to={c.to}
					className="group flex items-start gap-3 rounded-xl border border-border bg-surface p-4 transition-colors hover:border-accent-ring hover:bg-surface-2"
				>
					<span className="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-accent-soft text-accent">
						{c.icon}
					</span>
					<div className="min-w-0">
						<div className="text-[13.5px] font-semibold text-text group-hover:text-accent">
							{c.title}
						</div>
						<div className="mt-0.5 text-[12px] text-text-3">{c.description}</div>
					</div>
				</Link>
			))}
		</div>
	</div>
);
