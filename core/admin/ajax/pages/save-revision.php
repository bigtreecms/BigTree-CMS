<?php
	namespace BigTree;
	
	header("Content-type: text/javascript");
	
	if (is_numeric($_POST["id"])) {
		$revision = new PageRevision($_POST["id"]);
		$origin_page = new Page($revision->Page);
		
		if ($origin_page->UserAccessLevel != "p") {
			$this->stop("You must be a publisher to manage revisions.");
		}
		
		$revision->update($_POST["description"]);
	} else {
		$page = new Page(substr($_POST["id"], 1));
		
		if ($page->UserAccessLevel != "p") {
			$this->stop("You must be a publisher to manage revisions.");
		}
		
		PageRevision::create($page, $_POST["description"]);
	}
	
	Utils::growl("Pages", "Saved Revision");
?>
window.location.reload();