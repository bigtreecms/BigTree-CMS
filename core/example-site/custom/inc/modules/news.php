<?php
	class TimberNews extends BigTreeModule {
		
		public $Link;
		public $Table = "timber_news";
		
		function __construct() {
			$this->Link = WWW_ROOT.SQL::fetchSingle("SELECT path FROM bigtree_pages WHERE template = 'news'")."/";
		}

		public function getBreadcrumb($page) {
			global $bigtree;

			$breadcrumb = [];

			if ($bigtree["routed_path"][0] === "story") {
				$story = $this->getByRoute($bigtree["commands"][0]);

				$breadcrumb[] = [
					"title" => $story["title"],
					"link" => "#"
				];
			} elseif ($bigtree["routed_path"][0] == "submit") {
				$breadcrumb[] = [
					"title" => "Submit a Story",
					"link" => "#"
				];
			}

			return $breadcrumb;
		}

		public function getNav($page) {
			$nav = [[
				"title" => "Submit a Story",
				"link"  => WWW_ROOT.$page["path"]."/submit/"
			]];

			return $nav;
		}
		
	}
	