import type { Meta, StoryObj } from "@storybook/react-vite";

import { AuthCard } from "./AuthCard";
import { Button } from "./Button";
import { Field } from "./Field";
import { TextInput } from "./TextInput";

/**
 * Centered card layout for the unauthenticated screens (forgot / reset
 * password). Mirrors the login framing so the flows feel like one surface. It
 * fills the viewport (`min-h-screen`, its own `bg-bg`).
 */
const meta = {
	title: "UI/AuthCard",
	component: AuthCard,
	tags: ["autodocs"],
	parameters: { layout: "fullscreen" },
	args: {
		title: "Reset your password",
		subtitle: "Enter a new password for your account.",
		children: null,
	},
	render: (args) => (
		<AuthCard {...args}>
			<form className="flex flex-col gap-4">
				<Field label="New password">
					<TextInput placeholder="••••••••" type="password" />
				</Field>
				<Field label="Confirm password">
					<TextInput placeholder="••••••••" type="password" />
				</Field>
				<Button className="w-full justify-center" size="lg" type="submit" variant="primary">
					Set new password
				</Button>
			</form>
		</AuthCard>
	),
} satisfies Meta<typeof AuthCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const ForgotPassword: Story = {
	args: {
		title: "Forgot your password?",
		subtitle: "We'll email you a reset link.",
	},
	render: (args) => (
		<AuthCard {...args}>
			<form className="flex flex-col gap-4">
				<Field label="Email address">
					<TextInput placeholder="you@example.com" type="email" />
				</Field>
				<Button className="w-full justify-center" size="lg" type="submit" variant="primary">
					Send reset link
				</Button>
			</form>
		</AuthCard>
	),
};

/** Wider card used during multi-step auth flows (e.g. forced 2FA enrollment). */
export const Wide: Story = {
	args: {
		title: "Set up two-factor authentication",
		subtitle: "Your organization requires a second factor to sign in.",
		wide: true,
	},
	render: (args) => (
		<AuthCard {...args}>
			<p className="text-[12.5px] text-text-2">
				Enrollment form body goes here — QR code, secret, and verify step.
			</p>
		</AuthCard>
	),
};
