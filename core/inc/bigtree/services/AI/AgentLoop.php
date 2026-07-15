<?php
	namespace BigTree\Services\AI;

	use BigTreeAI;

	/**
	 * The generic tool-calling agent loop, factored out of SearchService::aiSearch
	 * so search and chat drive the same machinery:
	 *
	 *   - bounded rounds (each round is one paid provider call),
	 *   - OpenAI-shaped re-feed of the assistant's tool_calls turn,
	 *   - server-side dispatch through the per-user AIToolRegistry,
	 *   - a final no-tools fallback when rounds run out before a text answer.
	 *
	 * The loop stays domain-agnostic: it collects nothing itself. A caller that
	 * needs the tools' navigable artifacts (search results, proposal cards) passes
	 * an $on_tool_result callback and accumulates them however it likes. Everything
	 * the model sees is AIToolResult::toModelPayload(); artifacts never reach it.
	 */
	class AgentLoop {
		const DEFAULT_MAX_ROUNDS = 4;

		/** @var BigTreeAI */
		private $ai;

		/** @var AIToolRegistry */
		private $registry;

		/** @var int */
		private $max_rounds;

		public function __construct(BigTreeAI $ai, AIToolRegistry $registry, int $max_rounds = self::DEFAULT_MAX_ROUNDS) {
			$this->ai = $ai;
			$this->registry = $registry;
			$this->max_rounds = max(1, $max_rounds);
		}

		/**
		 * Drive the loop until the model produces a final text answer, the rounds
		 * are exhausted, or the provider errors.
		 *
		 * @param list<array<string,mixed>> $messages Seed conversation (system + prior turns + new user turn).
		 * @param AIToolContext $context Acting user + limits, re-checked on every dispatch.
		 * @param array<string,mixed> $options max_tokens, temperature, final_max_tokens.
		 * @param callable|null $on_tool_result fn(AIToolResult $result, array $call): void — collect artifacts.
		 * @return array{answer:?string,rounds:int,messages:list<array<string,mixed>>,tool_activity:list<array<string,mixed>>,error:?string}
		 */
		public function run(array $messages, AIToolContext $context, array $options = [], ?callable $on_tool_result = null): array {
			$tools = $this->registry->definitions($context->user);
			$chat_options = [
				"max_tokens" => (int)($options["max_tokens"] ?? 1024),
				"temperature" => (float)($options["temperature"] ?? 0.2),
			];
			$answer = null;
			$error = null;
			$rounds = 0;
			$tool_activity = [];

			while ($rounds < $this->max_rounds) {
				$rounds++;
				$result = $this->ai->chat($messages, $tools, $chat_options);

				if ($result === false) {
					$error = $this->ai->Error ?: "AI request failed";

					break;
				}

				$tool_calls = $result["tool_calls"] ?? [];

				if (!$tool_calls) {
					$answer = $result["content"];

					break;
				}

				// Append the assistant turn (OpenAI-shaped tool_calls) so the next
				// round and the tool results reference the same call ids.
				$messages[] = [
					"role" => "assistant",
					"content" => $result["content"],
					"tool_calls" => $this->openAiToolCalls($result, $tool_calls),
				];

				foreach ($tool_calls as $call) {
					$name = (string)($call["name"] ?? "");
					$args = is_array($call["arguments"] ?? null) ? $call["arguments"] : [];
					$tool_result = $this->registry->execute($name, $args, $context);

					$tool_activity[] = [
						"name" => $name,
						"arguments" => $args,
						"status" => $tool_result->type,
					];

					if ($on_tool_result !== null) {
						$on_tool_result($tool_result, $call);
					}

					// Fence the tool result as untrusted data (PromptGuard) so content
					// the tool fetched can't read as instructions to the model.
					$messages[] = [
						"role" => "tool",
						"tool_call_id" => (string)($call["id"] ?? ""),
						"content" => PromptGuard::wrapToolResult($tool_result->toModelPayload()),
					];
				}
			}

			// Exhausted rounds without a final text turn — ask once more with no
			// tools so the model has to answer from what it already gathered.
			if ($answer === null && $error === null && $rounds >= $this->max_rounds) {
				$final = $this->ai->chat($messages, [], [
					"max_tokens" => (int)($options["final_max_tokens"] ?? 512),
					"temperature" => $chat_options["temperature"],
				]);

				if ($final !== false) {
					$answer = $final["content"];
				} else {
					$error = $this->ai->Error ?: $error;
				}
			}

			return [
				"answer" => $answer !== null ? (string)$answer : null,
				"rounds" => $rounds,
				"messages" => $messages,
				"tool_activity" => $tool_activity,
				"error" => $error,
			];
		}

		/**
		 * Streaming counterpart to run(): drives the same bounded tool loop but calls
		 * BigTreeAI::chatStream so the model's text tokens arrive incrementally, and
		 * reports progress through $emit as it goes:
		 *
		 *   ["type" => "text", "text" => $chunk]  — a visible answer token
		 *   ["type" => "reset"]                   — discard streamed text; that round
		 *                                            turned into tool calls, so the
		 *                                            real answer comes in a later round
		 *   ["type" => "tool", "name", "arguments", "status"] — a tool ran
		 *
		 * The returned array matches run() exactly ({answer, rounds, messages,
		 * tool_activity, error}), so the caller persists and reconciles from an
		 * authoritative final answer regardless of what streamed — the token stream
		 * is a live preview, never the source of truth.
		 *
		 * @param list<array<string,mixed>> $messages
		 * @param array<string,mixed> $options
		 * @param callable $emit fn(array $event): void
		 * @param callable|null $on_tool_result fn(AIToolResult $result, array $call): void
		 * @return array{answer:?string,rounds:int,messages:list<array<string,mixed>>,tool_activity:list<array<string,mixed>>,error:?string}
		 */
		public function runStreaming(array $messages, AIToolContext $context, array $options, callable $emit, ?callable $on_tool_result = null): array {
			$tools = $this->registry->definitions($context->user);
			$chat_options = [
				"max_tokens" => (int)($options["max_tokens"] ?? 1024),
				"temperature" => (float)($options["temperature"] ?? 0.2),
			];
			$answer = null;
			$error = null;
			$rounds = 0;
			$tool_activity = [];

			while ($rounds < $this->max_rounds) {
				$rounds++;
				$streamed = "";
				$result = $this->ai->chatStream($messages, $tools, $chat_options, function (string $chunk) use ($emit, &$streamed): void {
					$streamed .= $chunk;
					$emit(["type" => "text", "text" => $chunk]);
				});

				if ($result === false) {
					$error = $this->ai->Error ?: "AI request failed";

					break;
				}

				$tool_calls = $result["tool_calls"] ?? [];

				if (!$tool_calls) {
					$answer = $result["content"];

					break;
				}

				// This round called tools; any text it streamed was a preamble, not
				// the answer — tell the client to clear the partial before we continue.
				if ($streamed !== "") {
					$emit(["type" => "reset"]);
				}

				$messages[] = [
					"role" => "assistant",
					"content" => $result["content"],
					"tool_calls" => $this->openAiToolCalls($result, $tool_calls),
				];

				foreach ($tool_calls as $call) {
					$name = (string)($call["name"] ?? "");
					$args = is_array($call["arguments"] ?? null) ? $call["arguments"] : [];
					$tool_result = $this->registry->execute($name, $args, $context);

					$activity = ["name" => $name, "arguments" => $args, "status" => $tool_result->type];
					$tool_activity[] = $activity;
					$emit(["type" => "tool"] + $activity);

					if ($on_tool_result !== null) {
						$on_tool_result($tool_result, $call);
					}

					$messages[] = [
						"role" => "tool",
						"tool_call_id" => (string)($call["id"] ?? ""),
						"content" => PromptGuard::wrapToolResult($tool_result->toModelPayload()),
					];
				}
			}

			// Exhausted rounds without a text answer — stream one more no-tools call.
			if ($answer === null && $error === null && $rounds >= $this->max_rounds) {
				$final = $this->ai->chatStream($messages, [], [
					"max_tokens" => (int)($options["final_max_tokens"] ?? 512),
					"temperature" => $chat_options["temperature"],
				], function (string $chunk) use ($emit): void {
					$emit(["type" => "text", "text" => $chunk]);
				});

				if ($final !== false) {
					$answer = $final["content"];
				} else {
					$error = $this->ai->Error ?: $error;
				}
			}

			return [
				"answer" => $answer !== null ? (string)$answer : null,
				"rounds" => $rounds,
				"messages" => $messages,
				"tool_activity" => $tool_activity,
				"error" => $error,
			];
		}

		/**
		 * The OpenAI-shaped tool_calls array to re-feed as the assistant turn.
		 * BigTreeAI stashes a ready-made one on raw for Anthropic; otherwise build
		 * it from the normalized calls.
		 *
		 * @param array<string,mixed> $result
		 * @param list<array<string,mixed>> $tool_calls
		 * @return list<array<string,mixed>>
		 */
		private function openAiToolCalls(array $result, array $tool_calls): array {
			$openai_calls = $result["raw"]["_openai_tool_calls"] ?? null;

			if (is_array($openai_calls)) {
				return $openai_calls;
			}

			$openai_calls = [];

			foreach ($tool_calls as $call) {
				$openai_calls[] = [
					"id" => $call["id"] ?? "",
					"type" => "function",
					"function" => [
						"name" => $call["name"] ?? "",
						"arguments" => json_encode($call["arguments"] ?? new \stdClass()),
					],
				];
			}

			return $openai_calls;
		}
	}
