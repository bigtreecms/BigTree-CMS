<?
	class SampleFeatures extends BigTreeModule {

		var $Table = "sample_features";
		var $Module = "6";
		
		function __construct() {
			$this->imageBase = SERVER_ROOT . "site/files/features/";
		}
		
		// OVERRIDE 'get()' TO ENSURE WE 
		// HAVE ALL IMAGE SIZES AVAILABLE
		function get($item) {
			$item = parent::get($item);
			
			// CREATE IMAGE SIZES 
			$src = explode("/", $item["image"]);
			$src = end($src);
			if (!file_exists($this->imageBase . "lrg_" . $src)) {
				$originalImg = $this->imageBase . "xlrg_" . $src;
				$lrgImg = $this->imageBase . "lrg_" . $src;
				$medImg = $this->imageBase . "med_" . $src;
				$smImg = $this->imageBase . "sm_" . $src;
				
				BigTree::centerCrop($originalImg, $lrgImg, 1000, 625);
				BigTree::createThumbnail($lrgImg, $medImg, 750, 0);
				BigTree::createThumbnail($lrgImg, $smImg, 500, 0);
			}
			
			return $item;
		}
	}
?>
