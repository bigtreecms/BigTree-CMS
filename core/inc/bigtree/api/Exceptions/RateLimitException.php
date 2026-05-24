<?php
	namespace BigTree\Api\Exceptions;

	class RateLimitException extends ApiException {
		public $status = 429;
		public $code_string = "rate_limited";
		public $retry_after = 60;

		public function __construct($retry_after = 60) {
			$this->retry_after = (int)$retry_after;
			parent::__construct("Rate limit exceeded", "rate_limited", 429, ["retry_after" => $this->retry_after]);
		}
	}
