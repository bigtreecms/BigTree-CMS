<?
	class SampleGlossary extends BigTreeModule {

		var $Table = "sample_glossary";
		var $Module = "10";
		
		public function getNav($page) {
			global $cms;
			
			$pageLink = $cms->getLink($page["id"]);
			$glossaryMod = new SampleGlossary;
			$terms = $glossaryMod->getApproved("term ASC");
			
			$nav = array();
			
			foreach ($terms as $term) {
				$nav[] = array(
					"title" => $term["term"], 
					"link" => $pageLink.urlencode($term["route"])."/"
				);
			}
			
			return $nav;
		}
		
	}
?>
