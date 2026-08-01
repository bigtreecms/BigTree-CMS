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

		/**
		 * How many tool calls one turn may make in total, across every round.
		 *
		 * max_rounds bounds the *conversation* with the model, not the work: a single
		 * round can ask for any number of parallel calls, so a loop that fans out ten
		 * calls a round was unbounded in the only dimension that costs anything. This
		 * is the backstop, deliberately well above what a legitimate turn needs.
		 */
		const MAX_TOOL_CALLS = 40;

		/**
		 * How many proposals one turn may stage. A turn that stages dozens of cards
		 * is a runaway, not a plan — and every one of them is a live, approvable
		 * write sitting in the user's queue for 24 hours.
		 */
		const MAX_PROPOSALS = 10;

		public function __construct(BigTreeAI $ai, AIToolRegistry $registry, int $max_rounds = self::DEFAULT_MAX_ROUNDS) {
			$this->ai = $ai;
			$this->registry = $registry;
			$this->max_rounds = max(1, $max_rounds);
		}

		/**
		 * Whether a turn has hit either per-turn cap, given what it has run so far.
		 * Returns the refusal to hand the model, or null when there is room.
		 *
		 * @param list<array<string,mixed>> $tool_activity
		 */
		private static function capReached(array $tool_activity): ?string {
			if (count($tool_activity) >= self::MAX_TOOL_CALLS) {

				return "This turn has already used its limit of " . self::MAX_TOOL_CALLS . " tool calls. Summarize "
					. "what you have so far and stop.";
			}

			$proposals = 0;

			foreach ($tool_activity as $activity) {
				if ((string)($activity["status"] ?? "") === AIToolResult::PROPOSAL) {
					$proposals++;
				}
			}

			if ($proposals >= self::MAX_PROPOSALS) {

				return "This turn has already staged its limit of " . self::MAX_PROPOSALS . " changes for approval. "
					. "Tell the user what is waiting for them and stop.";
			}

			return null;
		}

		/**
		 * The note that belongs on the final, no-tools synthesis call when the loop ran
		 * out of rounds mid-plan.
		 *
		 * Both *cap* refusals hand the model an explicit "summarize what you have and
		 * stop" (see capReached), and round exhaustion — the third and most common way a
		 * turn ends early — said nothing at all: the loop simply fell out of the while
		 * and into a call with no tools, so a turn cut off part-way through a
		 * read-then-stage sequence answered as if it had finished. That is the worst
		 * shape the answer can take, because "I've prepared that for you" over a
		 * proposal that was never staged is indistinguishable from the real thing
		 * (audit #14 C1).
		 *
		 * Delivered as a `system` message because BigTreeAI folds every system turn into
		 * the provider's system block for both providers, so it can't be mistaken for
		 * something the user said.
		 *
		 * @return array<string,string>
		 */
		private function exhaustionNote(): array {

			return [
				"role" => "system",
				"content" => "This turn has used its limit of " . $this->max_rounds . " tool-calling rounds, so it "
					. "ended before you signalled you were finished. Answer now from what you already have. Say "
					. "plainly which parts of the request you completed and which you did not get to, and do not "
					. "describe anything as prepared, staged or done unless a tool result above confirms it.",
			];
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
			$exhausted = false;

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
					$capped = self::capReached($tool_activity);
					$tool_result = $capped !== null
						? AIToolResult::error($capped)
						: $this->registry->execute($name, $args, $context);

					$tool_activity[] = self::activityFor($name, $args, $tool_result);

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

				// Set at the bottom of a round that asked for tools: reaching here on the
				// last allowed round is exactly "the model was still working when the
				// budget ran out". A round that broke out above never gets here.
				$exhausted = $rounds >= $this->max_rounds;
			}

			// No text to show — either the rounds ran out before a final turn, or a
			// round produced neither tool calls nor content (the providers normalize
			// empty content to null). Ask once more with no tools so the model has to
			// answer from what it already gathered.
			//
			// This used to be gated on $rounds >= max_rounds, so a round-1 empty break
			// wasn't covered: the turn was persisted and returned as a *successful*
			// empty answer, which the client rendered as a blank assistant bubble —
			// sitting above a ProposalCard with nothing explaining what the user was
			// being asked to approve.
			if (self::isBlank($answer) && $error === null) {
				if ($exhausted) {
					$messages[] = $this->exhaustionNote();
				}

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

			[$answer, $error] = self::resolveBlankAnswer($answer, $error);

			return [
				"answer" => $answer !== null ? (string)$answer : null,
				"rounds" => $rounds,
				"messages" => $messages,
				"tool_activity" => $tool_activity,
				"error" => $error,
			];
		}

		/**
		 * One tool call's record, as persisted and streamed.
		 *
		 * A needs_input result carries the question and its options through to the
		 * client, which renders them as choice chips — AIToolResult has described that
		 * affordance since it was written, but nothing outside the model ever received
		 * the fields, so the user was left reading the model's paraphrase of a question
		 * it had already been handed verbatim.
		 *
		 * @param array<string,mixed> $args
		 * @return array<string,mixed>
		 */
		private static function activityFor(string $name, array $args, AIToolResult $result): array {
			$activity = ["name" => $name, "arguments" => $args, "status" => $result->type];

			if ($result->type === AIToolResult::NEEDS_INPUT) {
				$payload = $result->toModelPayload();
				$activity["question"] = (string)($payload["question"] ?? "");
				$activity["options"] = is_array($payload["options"] ?? null) ? $payload["options"] : [];
			}

			return $activity;
		}

		/**
		 * Whether a model turn produced no visible text. Covers both shapes a provider
		 * can hand back for "said nothing": null (the normalized form) and "".
		 *
		 * @param mixed $answer
		 */
		private static function isBlank($answer): bool {

			return $answer === null || trim((string)$answer) === "";
		}

		/**
		 * The final verdict on a turn with no text: an error, never a successful blank
		 * answer. Both drivers only raise when `error !== null`, so leaving a blank
		 * answer here is what let an empty assistant bubble be persisted and returned
		 * as a normal, successful reply.
		 *
		 * @param mixed $answer
		 * @return array{0:?string,1:?string}
		 */
		private static function resolveBlankAnswer($answer, ?string $error): array {
			if ($error !== null) {

				return [self::isBlank($answer) ? null : (string)$answer, $error];
			}

			if (self::isBlank($answer)) {

				return [null, "The assistant produced no response. Try asking again."];
			}

			return [(string)$answer, null];
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
			$exhausted = false;

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
					$capped = self::capReached($tool_activity);
					$tool_result = $capped !== null
						? AIToolResult::error($capped)
						: $this->registry->execute($name, $args, $context);

					$activity = self::activityFor($name, $args, $tool_result);
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

				// See run(): the streaming loop is cut off the same way and answers from
				// the same final call, so it needs the same note.
				$exhausted = $rounds >= $this->max_rounds;
			}

			// No text to show — see run() for why this isn't gated on round count.
			if (self::isBlank($answer) && $error === null) {
				if ($exhausted) {
					$messages[] = $this->exhaustionNote();
				}

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

			[$answer, $error] = self::resolveBlankAnswer($answer, $error);

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
