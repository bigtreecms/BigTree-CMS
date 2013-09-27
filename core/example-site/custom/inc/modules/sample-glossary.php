<?
	class SampleGlossary extends BigTreeModule {

		var $Table = "sample_glossary";
		
		function getBreadcrumb($page) {
			global $bigtree;
			$term = $this->getByRoute($bigtree["commands"][0]);
			if ($term) {
				return array(array("title" => $term["term"],"link" => WWW_ROOT.$page["path"]."/".$term["route"]."/"));
			}
			return array();
		}

		function getNav($page) {
			$nav = array();			

			$terms = $this->getApproved("term ASC");
			foreach ($terms as $term) {
				$nav[] = array(
					"title" => $term["term"], 
					"link" => WWW_ROOT.$page["path"]."/".$term["route"]."/"
				);
			}
			
			return $nav;
		}
		
	}
?>