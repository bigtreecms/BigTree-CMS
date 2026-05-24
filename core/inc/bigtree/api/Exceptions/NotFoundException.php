<?php
	namespace BigTree\Api\Exceptions;

	class NotFoundException extends ApiException {
		public $status = 404;
		public $code_string = "resource_not_found";
	}
