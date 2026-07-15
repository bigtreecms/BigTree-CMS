<?php
	namespace BigTree\Services\AI;

	/**
	 * Discovers and registers extension-provided AI tools, mirroring how extensions
	 * plug routes into the REST manifest (Manifest globs
	 * extensions/{id}/api/routes/*.php). An extension exposes tools by dropping a
	 * provider file at one of:
	 *
	 *   extensions/{id}/api/ai-tools/*.php
	 *   custom/inc/bigtree/api/ai-tools/*.php
	 *
	 * Each provider file returns a list of AIToolInterface instances (or a single
	 * instance). A ProposalStore is available in the file's scope as $proposal_store
	 * so a provider can build two-phase mutating tools — those should implement
	 * Tools\ApprovableTool so AIChatService::approveProposal can dispatch them.
	 *
	 * Every registered tool goes through the same AIToolRegistry as core tools, so
	 * per-user filtering (isAvailable) and the server-side execute() re-check apply
	 * to extension tools exactly as they do to built-ins: an extension can add a
	 * capability, never bypass permission. Core tool names always win — an extension
	 * cannot shadow a built-in — and a provider file that throws is logged and
	 * skipped so one bad extension can't abort a chat turn.
	 */
	class ExtensionTools {
		/**
		 * Register every discovered extension tool into $registry.
		 */
		public static function registerInto(AIToolRegistry $registry, ProposalStore $store): void {
			self::registerFiles($registry, self::providerFiles(), $store);
		}

		/**
		 * Register the tools returned by an explicit list of provider files. Split
		 * out from registerInto() (which supplies the discovered files) so the
		 * include→normalize→register path can be tested against a fixture file.
		 *
		 * @param list<string> $files
		 */
		public static function registerFiles(AIToolRegistry $registry, array $files, ProposalStore $store): void {
			foreach ($files as $file) {
				try {
					$returned = self::includeProvider($file, $store);
				} catch (\Throwable $e) {
					@error_log("[BigTree AI] extension tool provider failed: " . $file . " — " . $e->getMessage());

					continue;
				}

				foreach (self::toolInstances($returned) as $tool) {
					// Core tools win: an extension can't shadow a built-in tool name.
					if (!$registry->has($tool->name())) {
						$registry->register($tool);
					}
				}
			}
		}

		/**
		 * The provider files present on disk, extensions first then custom.
		 *
		 * @return list<string>
		 */
		public static function providerFiles(): array {
			if (!defined("SERVER_ROOT")) {

				return [];
			}

			$files = [];

			foreach (glob(SERVER_ROOT . "extensions/*/api/ai-tools/*.php") ?: [] as $f) {
				$files[] = $f;
			}

			foreach (glob(SERVER_ROOT . "custom/inc/bigtree/api/ai-tools/*.php") ?: [] as $f) {
				$files[] = $f;
			}

			return $files;
		}

		/**
		 * Normalize a provider file's return value to a list of AIToolInterface. A
		 * single tool, a list of tools, or a mix with junk are all accepted; anything
		 * that is not a tool is dropped. Pure (no filesystem) so it is unit-testable.
		 *
		 * @param mixed $returned
		 * @return list<AIToolInterface>
		 */
		public static function toolInstances($returned): array {
			if ($returned instanceof AIToolInterface) {

				return [$returned];
			}

			if (!is_array($returned)) {

				return [];
			}

			$out = [];

			foreach ($returned as $item) {
				if ($item instanceof AIToolInterface) {
					$out[] = $item;
				}
			}

			return $out;
		}

		/**
		 * Include a provider file with $proposal_store in scope and return its value.
		 * Wrapped in a closure so the file cannot see or clobber caller locals.
		 *
		 * @return mixed
		 */
		private static function includeProvider(string $file, ProposalStore $store) {
			$load = static function (string $__provider_file, ProposalStore $proposal_store) {

				return include $__provider_file;
			};

			return $load($file, $store);
		}
	}
