<?php
	namespace BigTree\Services\AI;

	/**
	 * Assembles a provider's Server-Sent-Events stream into the same normalized
	 * result shape BigTreeAI::chat() returns for a buffered call
	 * ({content, tool_calls, raw}), while forwarding each text token to a callback
	 * as it arrives.
	 *
	 * The two provider stream formats are handled here so the curl-streaming code in
	 * BigTreeAI stays thin and this delta-assembly logic — the fiddly part — is pure
	 * and unit-testable against captured chunks:
	 *
	 *   - OpenAI / xAI (chat completions, stream:true): each `data: {json}` carries
	 *     choices[0].delta.content (text) and choices[0].delta.tool_calls (array of
	 *     {index, id, function:{name, arguments}} where arguments arrive in
	 *     fragments). The stream ends with `data: [DONE]`.
	 *   - Anthropic (messages, stream:true): typed events — content_block_start
	 *     (text or tool_use), content_block_delta (text_delta.text or
	 *     input_json_delta.partial_json), content_block_stop, message_stop.
	 *
	 * feedLine() is called once per raw SSE line; result() returns the assembled
	 * turn once the stream is exhausted.
	 */
	class StreamAccumulator {
		/** @var string openai|xai|anthropic */
		private $service;

		/** @var callable fn(string $text_chunk): void */
		private $on_delta;

		/** @var string Accumulated visible text. */
		private $text = "";

		/**
		 * Tool calls under assembly, keyed by stream index:
		 *   index => ["id" => string, "name" => string, "arguments" => string]
		 * @var array<int,array<string,string>>
		 */
		private $tool_calls = [];

		/** @var bool Set once a terminal marker ([DONE] / message_stop) is seen. */
		private $done = false;

		/**
		 * @param string $service openai|xai|anthropic
		 * @param callable $on_delta fn(string $text_chunk): void
		 */
		public function __construct(string $service, callable $on_delta) {
			$this->service = $service;
			$this->on_delta = $on_delta;
		}

		public function isDone(): bool {

			return $this->done;
		}

		/**
		 * Feed one raw SSE line (trailing CR already stripped). Non-data lines and
		 * unparseable payloads are ignored.
		 */
		public function feedLine(string $line): void {
			if (strncmp($line, "data:", 5) !== 0) {

				return;
			}

			$payload = trim(substr($line, 5));

			if ($payload === "") {

				return;
			}

			if ($payload === "[DONE]") {
				$this->done = true;

				return;
			}

			$json = json_decode($payload, true);

			if (!is_array($json)) {

				return;
			}

			if ($this->service === "anthropic") {
				$this->feedAnthropic($json);

				return;
			}

			$this->feedOpenAI($json);
		}

		/**
		 * @param array<string,mixed> $json
		 */
		private function feedOpenAI(array $json): void {
			$delta = $json["choices"][0]["delta"] ?? null;

			if (!is_array($delta)) {

				return;
			}

			$content = $delta["content"] ?? null;

			if (is_string($content) && $content !== "") {
				$this->appendText($content);
			}

			foreach ($delta["tool_calls"] ?? [] as $call) {
				if (!is_array($call)) {

					continue;
				}

				$index = (int)($call["index"] ?? 0);
				$this->ensureSlot($index);

				if (isset($call["id"]) && $call["id"] !== "") {
					$this->tool_calls[$index]["id"] = (string)$call["id"];
				}

				$fn = $call["function"] ?? [];

				if (isset($fn["name"]) && $fn["name"] !== "") {
					$this->tool_calls[$index]["name"] = (string)$fn["name"];
				}

				if (isset($fn["arguments"]) && is_string($fn["arguments"])) {
					$this->tool_calls[$index]["arguments"] .= $fn["arguments"];
				}
			}
		}

		/**
		 * @param array<string,mixed> $json
		 */
		private function feedAnthropic(array $json): void {
			$type = (string)($json["type"] ?? "");

			if ($type === "content_block_start") {
				$index = (int)($json["index"] ?? 0);
				$block = $json["content_block"] ?? [];

				if (($block["type"] ?? "") === "tool_use") {
					$this->tool_calls[$index] = [
						"id" => (string)($block["id"] ?? ""),
						"name" => (string)($block["name"] ?? ""),
						"arguments" => "",
					];
				}

				return;
			}

			if ($type === "content_block_delta") {
				$index = (int)($json["index"] ?? 0);
				$delta = $json["delta"] ?? [];
				$dtype = (string)($delta["type"] ?? "");

				if ($dtype === "text_delta") {
					$text = (string)($delta["text"] ?? "");

					if ($text !== "") {
						$this->appendText($text);
					}
				} elseif ($dtype === "input_json_delta" && isset($this->tool_calls[$index])) {
					$this->tool_calls[$index]["arguments"] .= (string)($delta["partial_json"] ?? "");
				}

				return;
			}

			if ($type === "message_stop") {
				$this->done = true;
			}
		}

		private function appendText(string $chunk): void {
			$this->text .= $chunk;
			($this->on_delta)($chunk);
		}

		private function ensureSlot(int $index): void {
			if (!isset($this->tool_calls[$index])) {
				$this->tool_calls[$index] = ["id" => "", "name" => "", "arguments" => ""];
			}
		}

		/**
		 * The assembled turn, in the exact shape BigTreeAI::chat() returns so a
		 * streaming round is a drop-in for a buffered one in AgentLoop. Tool-call
		 * argument strings are JSON-decoded; a fragment that never formed valid JSON
		 * degrades to an empty argument object rather than throwing.
		 *
		 * @return array{content:?string,tool_calls:list<array<string,mixed>>,raw:array<string,mixed>}
		 */
		public function result(): array {
			$tool_calls = [];
			$openai_tool_calls = [];

			ksort($this->tool_calls);

			foreach ($this->tool_calls as $slot) {
				$name = (string)($slot["name"] ?? "");

				if ($name === "") {

					continue;
				}

				$args_raw = (string)($slot["arguments"] ?? "");
				$args = $args_raw !== "" ? json_decode($args_raw, true) : [];

				if (!is_array($args)) {
					$args = [];
				}

				$id = (string)($slot["id"] ?? "");

				$tool_calls[] = [
					"id" => $id,
					"name" => $name,
					"arguments" => $args,
				];

				$openai_tool_calls[] = [
					"id" => $id,
					"type" => "function",
					"function" => [
						"name" => $name,
						"arguments" => $args === [] ? "{}" : json_encode($args),
					],
				];
			}

			return [
				"content" => $this->text === "" ? null : $this->text,
				"tool_calls" => $tool_calls,
				"raw" => ["_openai_tool_calls" => $openai_tool_calls],
			];
		}
	}
