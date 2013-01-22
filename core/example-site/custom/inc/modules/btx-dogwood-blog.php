<?
	/*
		Class: BTXDogwood
			Implements the btx_dogwood Blog Engine for BigTree 4.
	*/
	
	class BTXDogwood extends BigTreeModule {		
		/*
			Function: getAuthor
				Returns an author along with his/her email address.
			
			Parameters:
				author - Author ID or author array.
			
			Returns:
				An author array with the related user's email address added.
		*/
			
		function getAuthor($author) {
			if (!is_array($author)) {
				$author = sqlfetch(sqlquery("SELECT * FROM btx_dogwood_authors WHERE id = '".sqlescape($author)."'"));
			}
			
			if (!$author) {
				return false;
			}
			
			$email = sqlfetch(sqlquery("SELECT email FROM bigtree_users WHERE id = '".$author["user"]."'"));
			$author["email"] = $email["email"];
			
			return $this->get($author);
		}
		
		/*
			Function: getAuthorByRoute
				Returns an author along with his/her email address for a given route.
			
			Parameters:
				route - A route string.
			
			Returns:
				An author array with the related user's email address added.
			
			See Also:
				<getAuthor>
		*/
		
		function getAuthorByRoute($route) {
			$author = sqlfetch(sqlquery("SELECT * FROM btx_dogwood_authors WHERE route = '".sqlescape($route)."'"));
			if (!$author) {
				return false;
			}
			return $this->getAuthor($author);
		}
		
		/*
			Function: getAuthorByUserId
				Returns a Dogwood Author for a BigTree User's ID
			
			Parameters:
				id - A BigTree user id.
			
			Returns:
				An author array.
		*/
		
		function getAuthorByUserId($id) {
			return sqlfetch(sqlquery("SELECT * FROM btx_dogwood_authors WHERE user = '".sqlescape($id)."'"));
		}
		
		/*
			Function: getAuthors
				Returns a list of authors.
			
			Parameters:
				sort - The sort order, defaults to positioned.
			
			Returns:
				An array of author arrays with email addresses.
		*/
		
		function getAuthors($sort = "position DESC, id ASC") {
			$items = array();
			$q = sqlquery("SELECT * FROM btx_dogwood_authors ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$items[] = $this->getAuthor($f);
			}
			return $items;
		}
		
		/*
			Function: getCategories
				Returns a list of categories.
			
			Parameters:
				sort - The sort order, defaults to positioned.
			
			Returns:
				An array of category arrays.
		*/
		
		function getCategories($sort = "position DESC, id ASC") {
			$items = array();
			$q = sqlquery("SELECT * FROM btx_dogwood_categories ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}
			return $items;
		}
		
		/*
			Function: getCategory
				Returns a category array for a given id.
			
			Parameters:
				id - The category ID to pull.
			
			Returns:
				A category array.
		*/
		
		function getCategory($id) {
			return sqlfetch(sqlquery("SELECT * FROM btx_dogwood_categories WHERE id = '".sqlescape($id)."'"));
		}
		
		/*
			Function: getCategoryByRoute
				Returns a category array for a given route.
			
			Parameters:
				route - The category route to pull.
			
			Returns:
				A category array.
		*/
		
		function getCategoryByRoute($route) {
			return sqlfetch(sqlquery("SELECT * FROM btx_dogwood_categories WHERE route = '".sqlescape($route)."'"));
		}
		
		/*
			Function: getFeaturedPosts
				Returns recent featured posts.
			
			Parameters:
				limit - The number of posts to return. Defaults to 5.
			
			Returns:
				An array of decoded post arrays with author information.
		*/
		
		function getFeaturedPosts($limit = 5) {
			$posts = array();
			$q = sqlquery("SELECT * FROM btx_dogwood_posts WHERE featured = 'on' AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."') ORDER BY date DESC LIMIT $limit");
			while ($f = sqlfetch($q)) {
				$posts[] = $this->getPost($f);
			}
			return $posts;
		}
		
		/*
			Function: getModuleId
				Returns the id for Dogwood's "Posts" module.
			
			Returns:
				A module id.
		*/
		
		function getModuleId() {
			$f = sqlfetch(sqlquery("SELECT bigtree_module_actions.module FROM bigtree_module_actions JOIN bigtree_module_forms ON bigtree_module_actions.form = bigtree_module_forms.id WHERE bigtree_module_forms.`table` = 'btx_dogwood_posts'"));
			return $f["module"];
		}
		
		/*
			Function: getMonths
				Returns a list of months that contain posts.
			
			Returns:
				An array of dates in the YYYY-MM-01 format.
		*/
		
		function getMonths() {
			$months = array();
			$q = sqlquery("SELECT DISTINCT(DATE_FORMAT(date,'%Y-%m-01')) AS month FROM btx_dogwood_posts WHERE (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."') ORDER BY date DESC");
			while ($f = sqlfetch($q)) {
				$months[] = $f["month"];
			}
			return $months;
		}
		
		/*
			Function: getPageOfPosts
				Returns a page of decoded posts with author information.
			
			Parameters:
				page - The page number to retrieve.
				per_page - The number of posts per page (defaults to 5).
			
			Returns:
				An array of decoded post arrays with author information.
			
			See Also:
				<getPost>
		*/
		
		function getPageOfPosts($page,$per_page = 5) {
			$posts = array();
			$start = $page * $per_page;
			$q = sqlquery("SELECT * FROM btx_dogwood_posts WHERE (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."') ORDER BY date DESC LIMIT $start,$per_page");
			while ($f = sqlfetch($q)) {
				$posts[] = $this->getPost($f);
			}
			return $posts;
		}
		
		/*
			Function: getPageOfPostsByAuthor
				Returns a page of decoded posts with author information for a given author.
			
			Parameters:
				page - The page number to retrieve.
				author - The author ID (or author array) to pull posts for.
				per_page - The number of posts per page (defaults to 5).
			
			Returns:
				An array of decoded post arrays with author information.
			
			See Also:
				<getPost>
		*/
		
		function getPageOfPostsByAuthor($page,$author,$per_page = 5) {
			$posts = array();
			if (is_array($author)) {
				$author = $author["id"];
			}
			$start = $page * $per_page;
			$q = sqlquery("SELECT * FROM btx_dogwood_posts WHERE author = '".sqlescape($author)."' AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."') ORDER BY date DESC LIMIT $start,$per_page");
			while ($f = sqlfetch($q)) {
				$posts[] = $this->getPost($f);
			}
			return $posts;
		}
		
		/*
			Function: getPageOfPostsInCategory
				Returns a page of decoded posts with author information for a given category.
			
			Parameters:
				page - The page number to retrieve.
				category - The category ID (or category array) to pull posts for.
				per_page - The number of posts per page (defaults to 5).
			
			Returns:
				An array of decoded post arrays with author information.
			
			See Also:
				<getPost>
		*/
		
		function getPageOfPostsInCategory($page,$category,$per_page = 5) {
			$posts = array();
			if (is_array($category)) {
				$category = $category["id"];
			}
			$start = $page * $per_page;
			$q = sqlquery("SELECT btx_dogwood_posts.* FROM btx_dogwood_posts JOIN btx_dogwood_post_categories WHERE btx_dogwood_posts.id = btx_dogwood_post_categories.post AND btx_dogwood_post_categories.category = '".sqlescape($category)."' AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."') ORDER BY date DESC LIMIT $start,$per_page");
			while ($f = sqlfetch($q)) {
				$posts[] = $this->getPost($f);
			}
			return $posts;
		}
		
		/*
			Function: getPageOfPostsInMonth
				Returns a page of decoded posts with author information for a given month.
			
			Parameters:
				page - The page number to retrieve.
				month - The month (in YYYY-MM-DD) to pull posts for.
				per_page - The number of posts per page (defaults to 5).
			
			Returns:
				An array of decoded post arrays with author information.
			
			See Also:
				<getPost>
		*/
		
		function getPageOfPostsInMonth($page,$month,$per_page = 5) {
			$posts = array();
			$start = date("Y-m-01 00:00:00",strtotime($month));
			$end = date("Y-m-t 23:59:59",strtotime($month));
			$begin = $page * $per_page;
			$q = sqlquery("SELECT * FROM btx_dogwood_posts WHERE date >= '$start' AND date <= '$end' AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."') ORDER BY date DESC LIMIT $begin,$per_page");
			while ($f = sqlfetch($q)) {
				$posts[] = $this->getPost($f);
			}
			return $posts;
		}
		
		/*
			Function: getPageOfPostsWithTag
				Returns a page of posts that have been tagged with a given tag.
			
			Parameters:
				page - The page number to retrieve.
				tag - The tag ID or tag array to pull posts for.
				per_page - The number of posts per page (defaults to 5).
			
			Returns:
				An array of decoded post arrays with author information.
			
			See Also:
				<getPost>
		*/
		
		function getPageOfPostsWithTag($page, $tag, $per_page = 5) {
			$posts = array();
			if (is_array($tag)) {
				$tag = $tag["id"];
			}
			$start = $page * $per_page;
			$q = sqlquery("SELECT btx_dogwood_posts.* FROM btx_dogwood_posts JOIN bigtree_tags_rel ON btx_dogwood_posts.id = bigtree_tags_rel.entry WHERE bigtree_tags_rel.`table` = 'btx_dogwood_posts' AND bigtree_tags_rel.tag = '".sqlescape($tag)."' AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."') ORDER BY date DESC LIMIT $start,$per_page");
			while ($f = sqlfetch($q)) {
				$posts[] = $this->getPost($f);
			}
			return $posts;
		}
		
		/*
			Function: getPendingPostAndTags
				Returns a post with all pending changes attached and content decoded with author information.
			
			Parameters:
				post - The post ID or pending post ID prefixed with a "p".
			
			Returns:
				An associative array containing the decoded post array with author information in "post" and tags in "tags".
		*/
		
		function getPendingPostAndTags($post) {
			if (is_numeric($post)) {
				$post = sqlfetch(sqlquery("SELECT * FROM btx_dogwood_posts WHERE id = '$post'"));
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `table` = 'btx_dogwood_posts' AND item_id = '".$post["id"]."'"));
				if (is_array($f)) {
					$changes = json_decode($f["changes"],true);
					if (is_array($changes)) {
						foreach ($changes as $key => $val) {
							$post[$key] = $val;
						}
					}
					
					$tags = array();
					$t = json_decode($f["tags_changes"],true);
					if (is_array($t)) {
						foreach ($t as $i) {
							$tags[] = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE id = '$i'"));
						}
					}
				} else {
					$tags = $this->getTagsForPost($post);
				}
				$post = $this->getPost($post);
				return array("post" => $post, "tags" => $tags);
			} else {
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".sqlescape(substr($post,1))."'"));
				$post = json_decode($f["changes"],true);
				$post = $this->getPost($post);

				$tags = array();
				$t = json_decode($f["tags_changes"],true);
				if (is_array($t)) {
					foreach ($t as $i) {
					    $tags[] = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE id = '$i'"));
					}
				}
				
				return array("post" => $post, "tags" => $tags);
			}
		}

		/*
			Function: getPost
				Returns a post with content decoded and author information.
			
			Parameters:
				post - A post ID or post array.
			
			Returns:
				A decoded post array with author information or false if a post couldn't be found.
		*/
		
		function getPost($post) {
			global $cms;
			
			// If we passed in a post array, we're just going to decode it, otherwise fetch it based on ID.
			if (!is_array($post)) {
				$post = sqlfetch(sqlquery("SELECT * FROM btx_dogwood_posts WHERE id = '".sqlescape($post)."'"));
			}
			
			if (!$post) {
				return false;
			}
			
			// If there isn't a blurb, make one.
			if (!strip_tags($post["blurb"])) {
				$post["blurb"] = BigTree::trimLength($post["content"], 1000);
			}
			
			$post["blurb"] = $cms->replaceInternalPageLinks($post["blurb"]);
			$post["blurb"] = $cms->replaceInternalPageLinks($post["blurb"]);
			$post["external_link"] = $cms->replaceInternalPageLinks($post["external_link"]);
			$post["author"] = $this->getAuthor($post["author"]);

			return $this->get($post);
		}
		
		/*
			Function: getPostByRoute
				Retrieves a post based on a given route.
			
			Parameters:
				route - A route string.
			
			Returns:
				A decoded post array with author information or false if a post couldn't be found.
			
			See Also:
				<getPost>
		*/
		
		function getPostByRoute($route) {
			$post = sqlfetch(sqlquery("SELECT * FROM btx_dogwood_posts WHERE route = '".sqlescape($route)."' AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."')"));
			if (!$post) {
				return false;
			}
			return $this->getPost($post);
		}
		
		/*
			Function: getPostCount
				Returns the total number of posts.
			
			Returns:
				The number of posts in the database.
		*/
		
		function getPostCount() {
			$f = sqlfetch(sqlquery("SELECT COUNT(*) AS `count` FROM btx_dogwood_posts WHERE (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."')"));
			return $f["count"];
		}
		
		/*
			Function: getPostCountForAuthor
				Returns the total number of posts for a given author.
			
			Parameters:
				author - The author ID (or author array) to pull posts for.
		
			Returns:
				The number of posts in the database for a given author.
		*/
		
		function getPostCountForAuthor($author) {
			if (is_array($author)) {
				$author = $author["id"];
			}
			$f = sqlfetch(sqlquery("SELECT COUNT(*) AS `count` FROM btx_dogwood_posts WHERE author = '".sqlescape($author)."' AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."')"));
			return $f["count"];
		}
		
		/*
			Function: getPostCountInCategory
				Returns the total number of posts with a given category.
			
			Parameters:
				category - The category ID (or category array) to pull posts for.
		
			Returns:
				The number of posts in the database with a given category.
		*/
		
		function getPostCountInCategory($category) {
			if (is_array($category)) {
				$category = $category["id"];
			}
			return sqlrows(sqlquery("SELECT btx_dogwood_posts.id FROM btx_dogwood_posts JOIN btx_dogwood_post_categories WHERE btx_dogwood_posts.id = btx_dogwood_post_categories.post AND btx_dogwood_post_categories.category = '".sqlescape($category)."' AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."')"));
		}
		
		/*
			Function: getPostCountInMonth
				Returns the total number of posts with a given month.
			
			Parameters:
				month - The month (in YYYY-MM-DD) to pull posts for.
		
			Returns:
				The number of posts in the database with a given month.
		*/
		
		function getPostCountInMonth($month) {
			$start = date("Y-m-01 00:00:00",strtotime($month));
			$end = date("Y-m-t 23:59:59",strtotime($month));
			$f = sqlfetch(sqlquery("SELECT COUNT(*) AS `count` FROM btx_dogwood_posts WHERE date >= '$start' AND date <= '$end' AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."')"));
			return $f["count"];
		}
		
		/*
			Function: getPostCountWithTag
				Returns the total number of posts with a given tag.
			
			Parameters:
				tag - The tag ID or tag array to pull posts for.
		
			Returns:
				The number of posts in the database with a given tag.
		*/
		
		function getPostCountWithTag($tag) {
			$posts = array();
			if (is_array($tag)) {
				$tag = $tag["id"];
			}
			return sqlrows(sqlquery("SELECT btx_dogwood_posts.id FROM btx_dogwood_posts JOIN bigtree_tags_rel ON btx_dogwood_posts.id = bigtree_tags_rel.entry WHERE bigtree_tags_rel.`table` = 'btx_dogwood_posts' AND bigtree_tags_rel.tag = '".sqlescape($tag)."' AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."')"));
		}
		
		/*
			Function: getRawPost
				Returns a raw post from the database.
			
			Parameters:
				id - A post ID.
			
			Returns:
				A raw post array.
		*/
		
		function getRawPost($id) {
			$id = sqlescape($id);
			$post = sqlfetch(sqlquery("SELECT * FROM btx_dogwood_posts WHERE id = '$id'"));
			return $post;
		}
		
		/*
			Function: getRecentPosts
				Returns a list of recent (decoded) posts.
			
			Parameters:
				count - The number of posts to return, defaults to 8.
				
			Returns:
				An array of decoded post arrays with author information.
			
			See Also:
				<getPost>
		*/
		
		function getRecentPosts($count = 8) {
			$posts = array();
			$q = sqlquery("SELECT * FROM btx_dogwood_posts WHERE (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."') ORDER BY date DESC LIMIT $count");
			while ($f = sqlfetch($q)) {
				$posts[] = $this->getPost($f);
			}
			return $posts;
		}
		
		/*
			Function: getRelatedPosts
				Returns posts related to the passed in post based on its tags.
				Returns the most relevant results (based on # of tag matches).
			
			Parameters:
				post - A post ID or post array.
				limit - The number of results to return.
			
			Returns:
				An array of posts.
		*/
		
		function getRelatedPosts($post,$limit = 5) {
			$tags = $this->getTagsForPost($post);
			foreach ($tags as $tag) {
				$q = sqlquery("SELECT btx_dogwood_posts.* FROM btx_dogwood_posts JOIN bigtree_tags_rel ON btx_dogwood_posts.id = bigtree_tags_rel.entry WHERE bigtree_tags_rel.`table` = 'btx_dogwood_posts' AND bigtree_tags_rel.tag = '".sqlescape($tag["id"])."' AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."')");
				while ($f = sqlfetch($q)) {
					if (!isset($posts[$f["id"]])) {
						$f["relevance"] = 1;
						$posts[$f["id"]] = $f;
					} else {
						$posts[$f["id"]]["relevance"] += 1;
					}
				}
			}
			// Go through all the posts, move the relevance around.
			$rel = array();
			foreach ($posts as $p) {
				$rel[] = $p["relevance"];
			}
			// Sort the most relevant to the top
			array_multisort($rel,SORT_DESC,$posts);
			// Cut out the limit we want.
			return array_slice($posts,0,$limit);
		}
		
		/*
			Function: getSearchPageOfPosts
				Returns a page of decoded posts with author information matching a given query.
			
			Parameters:
				page - The page number to retrieve.
				query - A string to query the title, blurb, and content against.
				per_page - The number of posts per page (defaults to 5).
			
			Returns:
				An array of decoded post arrays with author information.
			
			See Also:
				<getPost>
		*/
		
		function getSearchPageOfPosts($query,$page,$per_page = 5) {
			$posts = array();
			$begin = $page * $per_page;
			$q = explode(" ",$query);
			$qparts = array("1");
			foreach ($q as $i) {
				$i = sqlescape(strtolower($i));
				$qparts[] = "(LOWER(title) LIKE '%$i%' OR LOWER(blurb) LIKE '%$i%' OR LOWER(content) LIKE '%$i%')";
			}
			$q = sqlquery("SELECT * FROM btx_dogwood_posts WHERE ".implode(" AND ",$qparts)." AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."') ORDER BY date DESC LIMIT $begin,$per_page");
			while ($f = sqlfetch($q)) {
				$posts[] = $this->getPost($f);
			}
			return $posts;
		}
		
		/*
			Function: getSearchPostCount
				Returns the number of posts match a given search query.
			
			Parameters:
				query - A string to query the title, blurb, and content against.
			
			Returns:
				The number of posts matching a query.
		*/
		
		function getSearchPostCount($query) {
			$q = explode(" ",$query);
			$qparts = array("1");
			foreach ($q as $i) {
				$i = sqlescape(strtolower($i));
				$qparts[] = "(LOWER(title) LIKE '%$i%' OR LOWER(blurb) LIKE '%$i%' OR LOWER(content) LIKE '%$i%')";
			}
			$qparts[] = "(publish_date IS NULL OR publish_date <= '".date("Y-m-d")."')";
			$f = sqlfetch(sqlquery("SELECT COUNT(*) AS `count` FROM btx_dogwood_posts WHERE ".implode(" AND ",$qparts)));
			return $f["count"];
		}
		
		/*
			Function: getTagsForPost
				Returns a list of tags for a given post.
			
			Parameters:
				post - A post ID or post array.
			
			Returns:
				An array of tag entries from bigtree_tags
		*/
		
		function getTagsForPost($post) {
			if (is_array($post)) {
				$post = $post["id"];
			}
			$post = sqlescape($post);
			
			$q = sqlquery("SELECT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel ON bigtree_tags.id = bigtree_tags_rel.tag WHERE bigtree_tags_rel.`table` = 'btx_dogwood_posts' AND bigtree_tags_rel.entry = '$post' ORDER BY bigtree_tags.tag");
			$tags = array();
			while ($f = sqlfetch($q)) {
				$tags[] = $f;
			}
			return $tags;
		}
		
		/*
			Function: getUsedTags
				Gets a list of all tags used by Dogwood posts.
			
			Returns:
				An array of tag entries from bigtree_tags
		*/
		
		function getUsedTags() {
			$q = sqlquery("SELECT DISTINCT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel ON bigtree_tags.id = bigtree_tags_rel.tag WHERE bigtree_tags_rel.`table` = 'btx_dogwood_posts' AND (publish_date IS NULL OR publish_date <= '".date("Y-m-d")."') ORDER BY bigtree_tags.tag ASC");
			$tags = array();
			while ($f = sqlfetch($q)) {
				$tags[] = $f;
			}
			return $tags;
		}
		
		/*
			Function: updatePost
				Updates a post after submission in the admin with author information.
		*/
		
		static function updatePost($data) {
			global $cms,$admin;
			
			// If they already have an author ID, we don't want to change it, but still need to return the current value for permission's sake.
			if ($data["id"]) {
				if (!is_numeric($data["id"])) {
					// We need to figure out the pending data, get the author from there and return it.
					$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".sqlescape(substr($data["id"],1))."'"));
					$c = json_decode($f["changes"],true);
					return array("author" => $c["author"]);
				} else {
					// Get the active entry's author
					$f = sqlfetch(sqlquery("SELECT * FROM btx_dogwood_posts WHERE id = '".sqlescape($data["id"])."'"));
					return array("author" => $f["author"]);
				}
			}

			// Get author info based on who's logged in doing this update.
			$author = sqlfetch(sqlquery("SELECT * FROM btx_dogwood_authors WHERE user = '".sqlescape($admin->ID)."'"));
			if (!$author) {
				$author = array("id" => 0);
			}
			
			return array("author" => $author["id"]);
		}
		
		// Custom APRE function to allow for overriding Post Authors
		function updatePostCustomAuthor($data) {
			global $cms,$admin;
			
			if ($data["author"]) {
				return $author["id"] = $author;
			} else if ($data["id"]) {
			// If they already have an author ID, we don't want to change it, but still need to return the current value for permission's sake.
				if (!is_numeric($data["id"])) {
					// We need to figure out the pending data, get the author from there and return it.
					$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".sqlescape(substr($data["id"],1))."'"));
					$c = json_decode($f["changes"],true);
					return array("author" => $c["author"]);
				} else {
					// Get the active entry's author
					$f = sqlfetch(sqlquery("SELECT * FROM btx_dogwood_posts WHERE id = '".sqlescape($data["id"])."'"));
					return array("author" => $f["author"]);
				}
			}

			// Get author info based on who's logged in doing this update.
			$author = sqlfetch(sqlquery("SELECT * FROM btx_dogwood_authors WHERE user = '".sqlescape($admin->ID)."'"));
			if (!$author) {
				$author = array("id" => 0);
			}
			
			return array("author" => $author["id"]);

		}
	}
?>
