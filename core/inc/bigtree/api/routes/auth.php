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

		"POST /auth/forgot-password" => [
			"service" => [AuthService::class, "forgotPassword"],
			"permission" => "public",
			"body" => ["email" => "required|email|max:255"],
			"rate_limit" => ["per_minute" => 5],
		],

		"POST /auth/reset-password" => [
			"service" => [AuthService::class, "resetPassword"],
			"permission" => "public",
			"body" => [
				"token" => "required|string|max:64",
				"password" => "required|string|min:8|max:255",
			],
			"rate_limit" => ["per_minute" => 10],
			"audit" => ["table" => "bigtree_users", "type" => "password_reset", "entry" => "0"],
		],

		"GET /auth/passkey/register/options" => [
			"service" => [AuthService::class, "passkeyRegisterOptions"],
			"permission" => ["level" => 0],
			"rate_limit" => ["per_minute" => 20],
		],

		"POST /auth/passkey/register/verify" => [
			"service" => [AuthService::class, "passkeyRegisterVerify"],
			"permission" => ["level" => 0],
			"body" => [
				"challenge_id" => "required|string|max:64",
				"client_data_json" => "required|string",
				"attestation_object" => "required|string",
				"name" => "string|max:255",
			],
			"rate_limit" => ["per_minute" => 10],
			"audit" => ["table" => "bigtree_user_passkeys", "type" => "registered", "entry" => "%id%"],
		],

		"GET /auth/passkeys" => [
			"service" => [AuthService::class, "listPasskeys"],
			"permission" => ["level" => 0],
		],

		"DELETE /auth/passkeys/{id:int}" => [
			"service" => [AuthService::class, "deletePasskey"],
			"permission" => ["level" => 0],
			"audit" => ["table" => "bigtree_user_passkeys", "type" => "deleted", "entry" => "%id%"],
		],
	];
