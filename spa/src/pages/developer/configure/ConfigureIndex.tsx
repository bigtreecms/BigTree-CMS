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
import { PageContainer } from "@/components/shell/PageContainer";
import { TileLinkGrid, type TileLink } from "@/components/ui/TileLinkGrid";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

const CARDS: TileLink[] = [
	{
		to: "/developer/configure/email",
		icon: <Mail size={16} />,
		title: "Email",
		description:
			"SMTP, Mandrill, Mailgun, Postmark, SendGrid — pick the delivery service BigTree uses.",
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
		description:
			"Authorize.Net / PayPal / LinkPoint credentials for module forms that take payments.",
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
		description:
			"Twitter, Instagram, YouTube, Flickr, Salesforce, Disqus, Facebook integrations.",
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
	<PageContainer width="wide">
		<Breadcrumb items={[{ label: "Developer", to: "/developer" }, { label: "Configure" }]} />

		<PageHead
			title="Configure"
			sub="Third-party integrations and storage backends BigTree talks to on your behalf."
		/>

		<DeveloperSectionNav />

		<TileLinkGrid items={CARDS} />
	</PageContainer>
);
