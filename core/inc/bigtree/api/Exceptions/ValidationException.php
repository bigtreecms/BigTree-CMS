<?php
	namespace BigTree\Api\Exceptions;

	class ValidationException extends ApiException {
		public $status = 422;
		public $code_string = "validation_failed";

		public function __construct(array $errors) {
			parent::__construct("Validation failed", "validation_failed", 422, ["errors" => $errors]);
		}
	}
