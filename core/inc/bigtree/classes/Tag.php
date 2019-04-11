<?php
	/*
		Class: BigTree\Tag
			Provides an interface for handling BigTree tags.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read int $ID
	 * @property-read string $Metaphone
	 * @property-read string $Route
	 */
	class Tag extends BaseObject
	{
		
		protected $ID;
		protected $Metaphone;
		protected $Route;
		
		public $Name;
		
		public static $Table = "bigtree_tags";
		
		/*
			Constructor:
				Builds a Tag object referencing an existing database entry.

			Parameters:
				tag - Either an ID (to pull a record) or an array (to use the array as the record)
		*/
		
		public function __construct($tag = null)
		{
			if ($tag !== null) {
				// Passing in just an ID
				if (!is_array($tag)) {
					$tag = SQL::fetch("SELECT * FROM bigtree_tags WHERE id = ?", $tag);
				}
				
				// Bad data set
				if (!is_array($tag)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $tag["id"];
					$this->Metaphone = $tag["metaphone"];
					$this->Name = $tag["tag"];
					$this->Route = $tag["route"];
					$this->UsageCount = $tag["usage_count"];
				}
			}
		}
		
		public function __toString()
		{
			return (string) $this->Name;
		}
		
		/*
			Function: allForEntry
				Returns the tags for an entry.

			Parameters:
				table - The table the entry is in.
				id - The id of the entry.
				return_arrays - Whether to return arrays rather than objects (defaults to false)

			Returns:
				An array of tags from bigtree_tags.
		*/
		
		public static function allForEntry(string $table, string $id, bool $return_arrays = false): array
		{
			$tags = SQL::fetchAll("SELECT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel 
								   ON bigtree_tags_rel.tag = bigtree_tags.id 
								   WHERE bigtree_tags_rel.`table` = ? AND bigtree_tags_rel.entry = ? 
								   ORDER BY bigtree_tags.tag ASC", $table, $id);
			
			if (!$return_arrays) {
				foreach ($tags as &$tag) {
					$tag = new Tag($tag);
				}
			}
			
			return $tags;
		}
		
		/*
			Function: allSimilar
				Finds existing tags that are similar to the given tag name.

			Parameters:
				name - A tag name to find similar tags for.
				count - Number of tags to return at most (defaults to 8).
				return_only_name - Set to true to return only the tag name rather than full objects.

			Returns:
				An array of Tag objects.
		*/
		
		public static function allSimilar(string $name, int $count = 8, bool $return_only_name = false): array
		{
			$tags = $distances = [];
			$meta = metaphone($name);
			
			// Get all tags to get sound-alike tags
			$all_tags = SQL::fetchAll("SELECT * FROM bigtree_tags");
			
			foreach ($all_tags as $tag) {
				// Calculate distance between letters of the sound of both tags
				$distance = levenshtein($tag["metaphone"], $meta);
				
				if ($distance < 2) {
					if (!$return_only_name) {
						$tags[] = new Tag($tag);
					} else {
						$tags[] = $tag["tag"];
					}
					
					$distances[] = $distance;
				}
			}
			
			// Get most relevant first
			array_multisort($distances, SORT_ASC, $tags);
			
			// Return only the number requested
			return array_slice($tags, 0, $count);
		}
		
		/*
			Function: create
				Creates a new tag.
				If a duplicate tag exists, that tag is returned instead.

			Parameters:
				name - The name of the tag.

			Returns:
				A Tag object.
		*/
		
		public static function create(string $name): Tag
		{
			$name = strtolower(html_entity_decode(trim($name)));
			
			// If this tag already exists, just ignore it and return the ID
			$existing = SQL::fetch("SELECT * FROM bigtree_tags WHERE tag = ?", $name);
			
			if ($existing) {
				return new Tag($existing);
			}
			
			// Create tag
			$id = SQL::insert("bigtree_tags", [
				"tag" => Text::htmlEncode($name),
				"metaphone" => metaphone($name),
				"route" => SQL::unique("bigtree_tags", "route", Link::urlify($name))
			]);
			
			AuditTrail::track("bigtree_tags", $id, "created");
			
			return new Tag($id);
		}
		
		/*
			Function: merge
				Merges another tag into this tag.
		
			Parameters:
				id - The tag ID to merge into this tag.
		*/
		
		public function merge(int $id) : void
		{
			SQL::update("bigtree_tags_rel", ["tag" => $id], ["tag" => $this->ID]);
			SQL::delete("bigtree_tags", $id);
			
			AuditTrail::track("bigtree_tags", $id, "merged");
		
			// Clean up duplicate references
			SQL::query("DELETE tags_a FROM bigtree_tags_rel AS tags_a, bigtree_tags_rel AS tags_b
						WHERE (tags_a.`table` = tags_b.`table`)
						  AND (tags_a.`entry` = tags_b.`entry`)
						  AND (tags_a.`tag` = tags_b.`tag`)
						  AND (tags_a.`id` < tags_b.`id`)");
			
			static::updateReferenceCounts([$this->ID]);
		}
		
		/*
			Function: updateReferenceCounts
				Updates the reference counts for tags to accurately match active database entries.

			Parameters:
				tags - An array of tag IDs to update reference counts for (defaults to all tags)
		*/
		
		public static function updateReferenceCounts(?array $tags = []): void
		{
			if (!count($tags)) {
				$tags = SQL::fetchAllSingle("SELECT id FROM bigtree_tags");
			}
			
			foreach ($tags as $tag_id) {
				$tag_id = intval($tag_id);
				$query = SQL::query("SELECT * FROM bigtree_tags_rel WHERE `tag` = '$tag_id'");
				$reference_count = 0;
				
				while ($reference = $query->fetch()) {
					if (SQL::exists($reference["table"], $reference["entry"])) {
						$reference_count++;
					} else {
						SQL::delete("bigtree_tags_rel", ["table" => $reference["table"], "entry" => $reference["entry"]]);
					}
				}
				
				SQL::update("bigtree_tags", $tag_id, ["usage_count" => $reference_count]);
			}
		}
		
	}
