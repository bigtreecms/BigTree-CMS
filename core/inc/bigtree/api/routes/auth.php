<?php
	use BigTree\Services\AuthService;

	return [
		"POST /auth/login" => [
			"service" => [AuthService::class, "login"],
			"permission" => "public",
			"body" => [
				"email" => "required|email|max:255",
				"password" => "required|string|max:255",
				"remember" => "bool",
			],
			"rate_limit" => ["per_minute" => 10],
		],

		"POST /auth/2fa" => [
			"service" => [AuthService::class, "twoFactor"],
			"permission" => "public",
			"body" => [
				"mfa_token" => "required|string|max:64",
				"code" => "required|string|max:16",
			],
			"rate_limit" => ["per_minute" => 10],
		],

		"POST /auth/refresh" => [
			"service" => [AuthService::class, "refresh"],
			"permission" => "public",
			"rate_limit" => ["per_minute" => 20],
		],

		"POST /auth/logout" => [
			"service" => [AuthService::class, "logout"],
			"permission" => "public",
		],

		"POST /auth/logout-all" => [
			"service" => [AuthService::class, "logoutAll"],
			"permission" => ["level" => 0],
		],

		"GET /auth/me" => [
			"service" => [AuthService::class, "me"],
			"permission" => ["level" => 0],
		],

		"GET /auth/passkey/options" => [
			"service" => [AuthService::class, "passkeyOptions"],
			"permission" => "public",
			"rate_limit" => ["per_minute" => 20],
		],

		"POST /auth/passkey/verify" => [
			"service" => [AuthService::class, "passkeyVerify"],
			"permission" => "public",
			"body" => [
				"challenge_id" => "required|string|max:64",
				"credential_id" => "required|string|max:1024",
				"client_data_json" => "required|string",
				"authenticator_data" => "required|string",
				"signature" => "required|string",
			],
			"rate_limit" => ["per_minute" => 10],
		],
	];
