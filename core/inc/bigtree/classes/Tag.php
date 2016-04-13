<?php
	/*
		Class: BigTree\Tag
			Provides an interface for handling BigTree tags.
	*/

	namespace BigTree;

	class Tag extends BaseObject {

		static $Table = "bigtree_tags";

		protected $ID;
		protected $Metaphone;
		protected $Route;

		public $Name;

		/*
			Constructor:
				Builds a Tag object referencing an existing database entry.

			Parameters:
				tag - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($tag) {
			// Passing in just an ID
			if (!is_array($tag)) {
				$tag = SQL::fetch("SELECT * FROM bigtree_feeds WHERE id = ?", $tag);
			}

			// Bad data set
			if (!is_array($tag)) {
				trigger_error("Invalid ID or data set passed to constructor.", E_USER_WARNING);
			} else {
				$this->ID = $tag["id"];
				$this->Metaphone = $tag["metaphone"];
				$this->Route = $tag["route"];

				$this->Name = $tag["tag"];
			}
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

		static function allForEntry($table, $id, $return_arrays = false) {
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

		static function allSimilar($name,$count = 8,$return_only_name = false) {
			$tags = $distances = array();
			$meta = metaphone($name);

			// Get all tags to get sound-alike tags
			$all_tags = SQL::fetchAll("SELECT * FROM bigtree_tags");
			foreach ($all_tags as $tag) {
				// Calculate distance between letters of the sound of both tags
				$distance = levenshtein($tag["metaphone"],$meta);
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
			array_multisort($distances,SORT_ASC,$tags);

			// Return only the number requested
			return array_slice($tags,0,$count);
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

		static function create($name) {
			$name = strtolower(html_entity_decode(trim($name)));

			// If this tag already exists, just ignore it and return the ID
			$existing = SQL::fetch("SELECT * FROM bigtree_tags WHERE tag = ?", $name);
			if ($existing) {
				return new Tag($existing);
			}

			// Create tag
			$id = SQL::insert("bigtree_tags",array(
				"tag" => Text::htmlEncode($name),
				"metaphone" => metaphone($name),
				"route" => SQL::unique("bigtree_tags","route",Link::urlify($name))
			));

			AuditTrail::track("bigtree_tags",$id,"created");

			return new Tag($id);
		}

	}
