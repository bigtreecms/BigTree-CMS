<?php
	namespace BigTree\GraphQL;
	
	use GraphQL\Language\AST\BooleanValueNode;
	use GraphQL\Language\AST\FloatValueNode;
	use GraphQL\Language\AST\IntValueNode;
	use GraphQL\Language\AST\ListValueNode;
	use GraphQL\Language\AST\Node;
	use GraphQL\Language\AST\ObjectValueNode;
	use GraphQL\Language\AST\StringValueNode;
	use GraphQL\Type\Definition\ScalarType;
	
	// Courtesy of NoMan2000 https://github.com/webonyx/graphql-php/issues/129
	class JSON extends ScalarType
	{
		public $name = 'JSON';
		public $description = 'Used for dynamic response data not specifically specifed in query payload.';
		
		public function __construct(?string $name = null)
		{
			if ($name) {
				$this->name = $name;
			}
			
			parent::__construct();
		}
		
		public function parseValue($value)
		{
			return $this->identity($value);
		}
		
		public function serialize($value)
		{
			return $this->identity($value);
		}
		
		public function parseLiteral(Node $valueNode, ?array $variables = null)
		{
			switch ($valueNode) {
				case ($valueNode instanceof StringValueNode):
				case ($valueNode instanceof BooleanValueNode):
					return $valueNode->value;
				case ($valueNode instanceof IntValueNode):
				case ($valueNode instanceof FloatValueNode):
					return floatval($valueNode->value);
				case ($valueNode instanceof ObjectValueNode): {
					$value = [];
					
					foreach ($valueNode->fields as $field) {
						$value[$field->name->value] = $this->parseLiteral($field->value);
					}
					
					return $value;
				}
				case ($valueNode instanceof ListValueNode):
					return array_map([$this, 'parseLiteral'], $valueNode->values);
				default:
					return null;
			}
		}
		
		private function identity($value)
		{
			return $value;
		}
		
	}
