<?php
	/*
		Class: BigTreeAI
			Generic AI chat/completions client for BigTree features. Supports
			xAI, OpenAI, and Anthropic with a normalized chat + tool-call API.
	*/

	class BigTreeAI {

		const SERVICES = ["xai", "openai", "anthropic"];

		/** @var array<string, list<array{id:string,label:string}>> */
		const MODELS = [
			"xai" => [
				["id" => "grok-4-latest", "label" => "Grok 4"],
				["id" => "grok-3", "label" => "Grok 3"],
				["id" => "grok-3-mini", "label" => "Grok 3 Mini"],
			],
			"openai" => [
				["id" => "gpt-4.1", "label" => "GPT-4.1"],
				["id" => "gpt-4.1-mini", "label" => "GPT-4.1 Mini"],
				["id" => "gpt-4o", "label" => "GPT-4o"],
			],
			"anthropic" => [
				["id" => "claude-sonnet-5", "label" => "Claude Sonnet 5"],
				["id" => "claude-opus-4-8", "label" => "Claude Opus 4.8"],
				["id" => "claude-haiku-4-5", "label" => "Claude Haiku 4.5"],
			],
		];

		const ENDPOINTS = [
			"xai" => "https://api.x.ai/v1/chat/completions",
			"openai" => "https://api.openai.com/v1/chat/completions",
			"anthropic" => "https://api.anthropic.com/v1/messages",
		];

		/**
		 * Embeddings always use OpenAI's API (xAI has no public embedding models;
		 * Anthropic has none). Chat may still be xAI/Anthropic via embedding_api_key.
		 */
		const EMBEDDING_ENDPOINT = "https://api.openai.com/v1/embeddings";

		/** Fixed VECTOR(n) dimension for bigtree_ai_embeddings (v1). */
		const EMBEDDING_DIMENSIONS = 1536;

		/**
		 * OpenAI embedding models (1536-d for the fixed VECTOR column).
		 *
		 * @var list<array{id:string,label:string}>
		 */
		const EMBEDDING_MODELS = [
			["id" => "text-embedding-3-small", "label" => "text-embedding-3-small (1536)"],
		];

		public $Error = false;
		public $Service = "";
		public $Model = "";
		public $EmbeddingModel = "";
		/** @var array<string,mixed> */
		public $Settings = [];

		public function __construct() {
			$settings = BigTreeCMS::getSetting("bigtree-internal-ai-service");

			if (!is_array($settings)) {
				$settings = [
					"service" => "",
					"api_key" => "",
					"model" => "",
					"embedding_model" => "",
					"embedding_api_key" => "",
					"features" => ["search" => false, "embeddings" => false],
				];
			}

			$this->Settings = $settings;
			$this->Service = (string)($settings["service"] ?? "");
			$this->Model = (string)($settings["model"] ?? "");
			$this->EmbeddingModel = (string)($settings["embedding_model"] ?? "");
		}

		/**
		 * Whether a provider, API key, and model are all present.
		 */
		public function isConfigured(): bool {
			return in_array($this->Service, self::SERVICES, true)
				&& trim((string)($this->Settings["api_key"] ?? "")) !== ""
				&& $this->Model !== "";
		}

		/**
		 * API key used for OpenAI embeddings.
		 * Dedicated embedding_api_key wins; otherwise reuse the chat key only
		 * when the chat provider is OpenAI.
		 */
		public function embeddingApiKey(): string {
			$dedicated = trim((string)($this->Settings["embedding_api_key"] ?? ""));

			if ($dedicated !== "") {
				return $dedicated;
			}

			if ($this->Service === "openai") {
				return trim((string)($this->Settings["api_key"] ?? ""));
			}

			return "";
		}

		/**
		 * Resolved embedding model id (default first allowlisted model).
		 */
		public function resolvedEmbeddingModel(): string {
			if ($this->EmbeddingModel !== "" && self::isValidEmbeddingModel($this->EmbeddingModel)) {
				return $this->EmbeddingModel;
			}

			return self::EMBEDDING_MODELS[0]["id"] ?? "";
		}

		/**
		 * Whether OpenAI embeddings can be requested (model + key present).
		 * Chat may be any provider; embeddings always hit OpenAI.
		 */
		public function isEmbeddingsConfigured(): bool {
			$model = $this->resolvedEmbeddingModel();

			return $model !== ""
				&& self::isValidEmbeddingModel($model)
				&& $this->embeddingApiKey() !== "";
		}

		/**
		 * Whether a named feature flag is enabled and the service is configured.
		 *
		 * @param string $feature Feature key under settings.features (e.g. "search")
		 */
		public function isFeatureEnabled(string $feature): bool {
			if ($feature === "embeddings") {
				if (!$this->isEmbeddingsConfigured()) {
					return false;
				}
			} elseif (!$this->isConfigured()) {
				return false;
			}

			$features = is_array($this->Settings["features"] ?? null)
				? $this->Settings["features"]
				: [];

			return !empty($features[$feature]);
		}

		/**
		 * @return list<array{id:string,label:string}>
		 */
		public static function embeddingModels(): array {
			return self::EMBEDDING_MODELS;
		}

		public static function isValidEmbeddingModel(string $model): bool {
			foreach (self::EMBEDDING_MODELS as $entry) {
				if ($entry["id"] === $model) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Create embeddings via OpenAI (text-embedding-3-small @ 1536-d).
		 *
		 * @param string|list<string> $input
		 * @return list<list<float>>|false
		 */
		public function embed($input) {
			$this->Error = false;

			if (!$this->isEmbeddingsConfigured()) {
				$this->Error = "Embeddings are not configured. Add an OpenAI API key for embeddings under Developer → Configure → AI.";

				return false;
			}

			$texts = is_array($input) ? array_values($input) : [(string)$input];
			$texts = array_map(function ($t) {
				$t = (string)$t;

				if (mb_strlen($t) > 30000) {
					return mb_substr($t, 0, 30000);
				}

				return $t;
			}, $texts);

			if (!$texts) {
				$this->Error = "No text to embed.";

				return false;
			}

			$model = $this->resolvedEmbeddingModel();
			$body = [
				"model" => $model,
				"input" => count($texts) === 1 ? $texts[0] : $texts,
			];

			$response = $this->requestJson(self::EMBEDDING_ENDPOINT, $body, [
				"Authorization: Bearer " . $this->embeddingApiKey(),
				"Content-Type: application/json",
			], 15);

			if ($response === false) {
				return false;
			}

			$data = $response["data"] ?? null;

			if (!is_array($data) || !$data) {
				$this->Error = "Unexpected embeddings response from provider.";

				return false;
			}

			// Preserve input order via index when present.
			usort($data, function ($a, $b) {
				return ((int)($a["index"] ?? 0)) <=> ((int)($b["index"] ?? 0));
			});

			$out = [];

			foreach ($data as $row) {
				$vec = $row["embedding"] ?? null;

				if (!is_array($vec)) {
					$this->Error = "Invalid embedding vector in provider response.";

					return false;
				}

				$out[] = array_map("floatval", $vec);
			}

			return $out;
		}

		/**
		 * Models allowed for a provider (or all providers if $service is empty).
		 *
		 * @return list<array{id:string,label:string}>|array<string,list<array{id:string,label:string}>>
		 */
		public static function modelsForService(string $service = "") {
			if ($service === "") {
				return self::MODELS;
			}

			return self::MODELS[$service] ?? [];
		}

		/**
		 * Whether $model is in the allowlist for $service.
		 */
		public static function isValidModel(string $service, string $model): bool {
			foreach (self::MODELS[$service] ?? [] as $entry) {
				if ($entry["id"] === $model) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Whether the DB can store VECTOR columns (MySQL 9+ / MariaDB 11.7+).
		 */
		public static function vectorStoreSupported(): bool {
			static $cached = null;

			if ($cached !== null) {
				return $cached;
			}

			try {
				$version = (string)SQL::fetchSingle("SELECT VERSION()");
			} catch (Throwable $e) {
				$cached = false;

				return false;
			}

			$cached = self::parseVectorSupport($version);

			return $cached;
		}

		/**
		 * Pure test of a VERSION() string for VECTOR support (MySQL 9+ /
		 * MariaDB 11.7+). Extracted from vectorStoreSupported() so it can be unit
		 * tested without a DB connection. VERSION() looks like "11.7.1-MariaDB",
		 * "10.11.6-MariaDB", "9.0.1", or "8.0.36".
		 */
		public static function parseVectorSupport(string $version): bool {
			if ($version === "") {
				return false;
			}

			$is_maria = stripos($version, "mariadb") !== false;

			if (!preg_match('/^(\d+)\.(\d+)/', $version, $m)) {
				return false;
			}

			$major = (int)$m[1];
			$minor = (int)$m[2];

			if ($is_maria) {
				return $major > 11 || ($major === 11 && $minor >= 7);
			}

			return $major >= 9;
		}

		/**
		 * mysql | mariadb | unknown
		 */
		public static function vectorDialect(): string {
			static $dialect = null;

			if ($dialect !== null) {
				return $dialect;
			}

			try {
				$version = (string)SQL::fetchSingle("SELECT VERSION()");
			} catch (Throwable $e) {
				$dialect = "unknown";

				return $dialect;
			}

			$dialect = stripos($version, "mariadb") !== false ? "mariadb" : "mysql";

			return $dialect;
		}

		/**
		 * Chat completion with optional tool definitions.
		 *
		 * Messages use OpenAI-style roles: system, user, assistant, tool.
		 * Tools are OpenAI-style function tools:
		 *   [ ["type" => "function", "function" => ["name" => ..., "description" => ..., "parameters" => ...]], ... ]
		 *
		 * Returns:
		 *   [
		 *     "content" => string|null,
		 *     "tool_calls" => list of [ "id", "name", "arguments" => array ],
		 *     "raw" => mixed,
		 *   ]
		 * or false on failure ($this->Error is set).
		 *
		 * @param list<array<string,mixed>> $messages
		 * @param list<array<string,mixed>> $tools
		 * @param array<string,mixed> $options  max_tokens, temperature, etc.
		 * @return array{content:?string,tool_calls:list<array<string,mixed>>,raw:mixed}|false
		 */
		public function chat(array $messages, array $tools = [], array $options = []) {
			$this->Error = false;

			if (!$this->isConfigured()) {
				$this->Error = "AI service is not configured.";

				return false;
			}

			if ($this->Service === "anthropic") {
				return $this->chatAnthropic($messages, $tools, $options);
			}

			return $this->chatOpenAICompatible($messages, $tools, $options);
		}

		/**
		 * xAI and OpenAI share the Chat Completions shape.
		 *
		 * @param list<array<string,mixed>> $messages
		 * @param list<array<string,mixed>> $tools
		 * @param array<string,mixed> $options
		 * @return array{content:?string,tool_calls:list<array<string,mixed>>,raw:mixed}|false
		 */
		private function chatOpenAICompatible(array $messages, array $tools, array $options) {
			$url = self::ENDPOINTS[$this->Service];
			$body = [
				"model" => $this->Model,
				"messages" => $messages,
			];

			if (!empty($options["max_tokens"])) {
				$body["max_tokens"] = (int)$options["max_tokens"];
			}

			if (array_key_exists("temperature", $options)) {
				$body["temperature"] = (float)$options["temperature"];
			}

			if ($tools) {
				$body["tools"] = $tools;
				$body["tool_choice"] = $options["tool_choice"] ?? "auto";
			}

			$response = $this->requestJson($url, $body, [
				"Authorization: Bearer " . $this->Settings["api_key"],
				"Content-Type: application/json",
			]);

			if ($response === false) {
				return false;
			}

			$choice = $response["choices"][0]["message"] ?? null;

			if (!is_array($choice)) {
				$this->Error = "Unexpected response from AI provider.";

				return false;
			}

			$tool_calls = [];

			foreach ($choice["tool_calls"] ?? [] as $call) {
				$fn = $call["function"] ?? [];
				$args_raw = $fn["arguments"] ?? "{}";
				$args = is_string($args_raw) ? json_decode($args_raw, true) : $args_raw;

				if (!is_array($args)) {
					$args = [];
				}

				$tool_calls[] = [
					"id" => (string)($call["id"] ?? ""),
					"name" => (string)($fn["name"] ?? ""),
					"arguments" => $args,
				];
			}

			$content = $choice["content"] ?? null;

			if (is_array($content)) {
				// Some models return content parts; flatten text.
				$parts = [];

				foreach ($content as $part) {
					if (is_string($part)) {
						$parts[] = $part;
					} elseif (is_array($part) && isset($part["text"])) {
						$parts[] = (string)$part["text"];
					}
				}

				$content = implode("", $parts);
			}

			return [
				"content" => $content === null || $content === "" ? null : (string)$content,
				"tool_calls" => $tool_calls,
				"raw" => $response,
			];
		}

		/**
		 * Anthropic Messages API — maps OpenAI-style messages/tools in and out.
		 *
		 * @param list<array<string,mixed>> $messages
		 * @param list<array<string,mixed>> $tools
		 * @param array<string,mixed> $options
		 * @return array{content:?string,tool_calls:list<array<string,mixed>>,raw:mixed}|false
		 */
		private function chatAnthropic(array $messages, array $tools, array $options) {
			$system = "";
			$anthropic_messages = [];

			foreach ($messages as $msg) {
				$role = (string)($msg["role"] ?? "user");

				if ($role === "system") {
					$system .= ($system === "" ? "" : "\n\n") . (string)($msg["content"] ?? "");

					continue;
				}

				if ($role === "tool") {
					$anthropic_messages[] = [
						"role" => "user",
						"content" => [[
							"type" => "tool_result",
							"tool_use_id" => (string)($msg["tool_call_id"] ?? ""),
							"content" => is_string($msg["content"] ?? null)
								? $msg["content"]
								: json_encode($msg["content"] ?? new stdClass()),
						]],
					];

					continue;
				}

				if ($role === "assistant" && !empty($msg["tool_calls"])) {
					$content_blocks = [];

					if (!empty($msg["content"])) {
						$content_blocks[] = [
							"type" => "text",
							"text" => (string)$msg["content"],
						];
					}

					foreach ($msg["tool_calls"] as $call) {
						$fn = $call["function"] ?? $call;
						$args_raw = $fn["arguments"] ?? ($call["arguments"] ?? []);

						if (is_string($args_raw)) {
							$args = json_decode($args_raw, true);

							if (!is_array($args)) {
								$args = [];
							}
						} else {
							$args = is_array($args_raw) ? $args_raw : [];
						}

						$content_blocks[] = [
							"type" => "tool_use",
							"id" => (string)($call["id"] ?? ""),
							"name" => (string)($fn["name"] ?? $call["name"] ?? ""),
							"input" => $args === [] ? new stdClass() : $args,
						];
					}

					$anthropic_messages[] = [
						"role" => "assistant",
						"content" => $content_blocks,
					];

					continue;
				}

				$anthropic_messages[] = [
					"role" => $role === "assistant" ? "assistant" : "user",
					"content" => (string)($msg["content"] ?? ""),
				];
			}

			// Merge consecutive same-role messages (Anthropic requires alternation,
			// but tool results are already role=user blocks).
			$anthropic_messages = $this->mergeAnthropicMessages($anthropic_messages);

			$body = [
				"model" => $this->Model,
				"max_tokens" => (int)($options["max_tokens"] ?? 2048),
				"messages" => $anthropic_messages,
			];

			if ($system !== "") {
				$body["system"] = $system;
			}

			if (array_key_exists("temperature", $options)) {
				$body["temperature"] = (float)$options["temperature"];
			}

			if ($tools) {
				$body["tools"] = array_map(function ($tool) {
					$fn = $tool["function"] ?? $tool;

					return [
						"name" => (string)($fn["name"] ?? ""),
						"description" => (string)($fn["description"] ?? ""),
						"input_schema" => $fn["parameters"] ?? [
							"type" => "object",
							"properties" => new stdClass(),
						],
					];
				}, $tools);
			}

			$response = $this->requestJson(self::ENDPOINTS["anthropic"], $body, [
				"x-api-key: " . $this->Settings["api_key"],
				"anthropic-version: 2023-06-01",
				"Content-Type: application/json",
			]);

			if ($response === false) {
				return false;
			}

			$content_text = null;
			$tool_calls = [];
			// Keep OpenAI-shaped assistant message for the next loop iteration.
			$openai_tool_calls = [];

			foreach ($response["content"] ?? [] as $block) {
				$type = $block["type"] ?? "";

				if ($type === "text") {
					$content_text = ($content_text ?? "") . (string)($block["text"] ?? "");
				} elseif ($type === "tool_use") {
					$tool_calls[] = [
						"id" => (string)($block["id"] ?? ""),
						"name" => (string)($block["name"] ?? ""),
						"arguments" => is_array($block["input"] ?? null) ? $block["input"] : [],
					];
					$openai_tool_calls[] = [
						"id" => (string)($block["id"] ?? ""),
						"type" => "function",
						"function" => [
							"name" => (string)($block["name"] ?? ""),
							"arguments" => json_encode(
								is_array($block["input"] ?? null) ? $block["input"] : new stdClass()
							),
						],
					];
				}
			}

			// Stash OpenAI-compatible tool_calls on raw so callers that re-feed
			// the assistant message can use a consistent shape.
			$response["_openai_tool_calls"] = $openai_tool_calls;

			return [
				"content" => $content_text === "" ? null : $content_text,
				"tool_calls" => $tool_calls,
				"raw" => $response,
			];
		}

		/**
		 * @param list<array<string,mixed>> $messages
		 * @return list<array<string,mixed>>
		 */
		private function mergeAnthropicMessages(array $messages): array {
			if (!$messages) {
				return $messages;
			}

			$merged = [];
			$current = null;

			foreach ($messages as $msg) {
				if ($current === null) {
					$current = $msg;

					continue;
				}

				if (($current["role"] ?? "") === ($msg["role"] ?? "")
					&& is_array($current["content"] ?? null)
					&& is_array($msg["content"] ?? null)
				) {
					$current["content"] = array_merge($current["content"], $msg["content"]);
				} else {
					$merged[] = $current;
					$current = $msg;
				}
			}

			if ($current !== null) {
				$merged[] = $current;
			}

			return $merged;
		}

		/**
		 * @param list<string> $headers
		 * @param array<string,mixed> $body
		 * @param int $timeout Per-request cap so one slow provider can't consume the
		 *                     whole PHP budget (aiSearch makes up to 6 sequential calls).
		 * @return array<string,mixed>|false
		 */
		private function requestJson(string $url, array $body, array $headers, int $timeout = 30) {
			global $bigtree;

			$payload = json_encode($body);

			if ($payload === false) {
				$this->Error = "Failed to encode request body.";

				return false;
			}

			// Explicit CURLOPT_TIMEOUT overrides BigTree::cURL's default of
			// max_execution_time - 5 (which would be the entire request budget).
			$raw = BigTree::cURL($url, $payload, [
				CURLOPT_HTTPHEADER => $headers,
				CURLOPT_POST => true,
				CURLOPT_TIMEOUT => $timeout,
				CURLOPT_CONNECTTIMEOUT => 5,
			]);

			$code = (int)($bigtree["last_curl_response_code"] ?? 0);

			if ($raw === false || $raw === null || $raw === "") {
				$this->Error = !empty($bigtree["last_curl_error"])
					? (string)$bigtree["last_curl_error"]
					: "Empty response from AI provider (HTTP $code).";

				return false;
			}

			$decoded = json_decode((string)$raw, true);

			if (!is_array($decoded)) {
				$this->Error = "Invalid JSON from AI provider (HTTP $code).";

				return false;
			}

			if ($code < 200 || $code >= 300) {
				$this->Error = $this->extractProviderError($decoded, $code);

				return false;
			}

			return $decoded;
		}

		/**
		 * @param array<string,mixed> $decoded
		 */
		private function extractProviderError(array $decoded, int $code): string {
			if (!empty($decoded["error"]["message"])) {
				return (string)$decoded["error"]["message"];
			}

			if (!empty($decoded["error"]) && is_string($decoded["error"])) {
				return $decoded["error"];
			}

			if (!empty($decoded["message"])) {
				return (string)$decoded["message"];
			}

			return "AI provider error (HTTP $code).";
		}
	}
