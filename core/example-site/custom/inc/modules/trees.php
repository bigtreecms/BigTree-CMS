<?
	class DemoTrees extends BigTreeModule {

		var $Table = "demo_trees";
		var $Module = "1";

		function __construct() {
			global $cms;
			$this->Link = $cms->getLink(2);
		}
		
		function get($item) {
			$item = parent::get($item);
			$item["detail_link"] = $this->Link."detail/".$item["route"]."/";
			return $item;
		}

		function getNav($page) {
			$items = $this->getAllPositioned();
			$nav = array();
			foreach ($items as $item) {
				$nav[] = array("title" => $item["title"],"link" => WWW_ROOT.$page["path"]."/detail/".$item["route"]."/");
			}
			return $nav;
		}
		
		function getPrevious($tree) {
			$trees = $this->getAllPositioned();
			$position = array_search($tree,$trees);
			return isset($trees[$position - 1]) ? $trees[$position - 1] : false;
		}
		
		function getNext($tree) {
			$trees = $this->getAllPositioned();
			$position = array_search($tree,$trees);
			return isset($trees[$position + 1]) ? $trees[$position + 1] : false;
		}
	}
?>
