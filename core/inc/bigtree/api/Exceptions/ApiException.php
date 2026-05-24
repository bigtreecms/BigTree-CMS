<?php
	namespace BigTree\Api\Exceptions;

	use Exception;

	class ApiException extends Exception {
		public $status = 500;
		public $code_string = "internal_error";
		public $details = [];

		public function __construct($message = "", $code_string = null, $status = null, array $details = []) {
			parent::__construct($message ?: $this->code_string);
			if ($code_string !== null) $this->code_string = $code_string;
			if ($status !== null) $this->status = $status;
			$this->details = $details;
		}
	}
