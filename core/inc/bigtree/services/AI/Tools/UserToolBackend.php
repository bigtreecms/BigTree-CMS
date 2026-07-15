<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the user-management AI tools call, implemented by UserService.
	 *
	 * Deliberately narrow: administrators (level ≥ 1) can create basic editor accounts
	 * and edit a user's profile fields (name, email, company, timezone, daily digest).
	 * The assistant NEVER sets or changes a user's level or permissions (an explicit
	 * non-tool — privilege changes stay a human action) and never handles passwords. A
	 * new account is always created at level 0 and needs a password set out of band.
	 * A user whose level exceeds the acting administrator's cannot be edited.
	 */
	interface UserToolBackend {
		/**
		 * Validate creating a basic editor account without writing: administrator
		 * level, a valid unique email. Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateUserCreate(array $args, $user): array;

		/**
		 * Apply an approved account creation from a stored payload. Re-checks admin
		 * level and creates the user at level 0 with no password.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiCreateUser(array $payload, $user): array;

		/**
		 * Validate editing a user's profile fields without writing: admin level, target
		 * existence, and that the target's level does not exceed the actor's. Never
		 * touches level or permissions. Same return shape.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateUserUpdate(array $args, $user): array;

		/**
		 * Apply an approved profile edit from a stored payload. Re-checks admin level
		 * and the target-level guard.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiUpdateUser(array $payload, $user): array;
	}
