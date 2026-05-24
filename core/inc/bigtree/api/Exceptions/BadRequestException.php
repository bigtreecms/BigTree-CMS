<?php
	namespace BigTree\Api\Exceptions;

	class BadRequestException extends ApiException {
		public $status = 400;
		public $code_string = "bad_request";
	}
