<?
	if ($_POST["query"]) {
		header("Location: ".urlencode($_POST["query"])."/");
		die();
	}
	
	$breadcrumb = array(array("link" => "search/","title" => "Advanced Search"), array("link" => "search/".urlencode(end($path))."/", "title" => "Query: &ldquo;".end($path)."&rdquo;"));
	$module_title = "Advanced Search";
	
	$total_results = 0;
	$results = array();
	$w = "'%".mysql_real_escape_string(end($path))."%'";
	
	// Get the "Pages" results.
	$r = $admin->searchPages(end($path),array("title","resources","meta_keywords","meta_description","nav_title"),"50");
	$pages = array();
	foreach ($r as $f) {
		$res = json_decode($f["resources"],true);
		$bc = $cms->getBreadcrumbByPage($f);
		$bc_parts = array();
		foreach ($bc as $part) {
			$bc_parts[] = '<a href="'.$admin_root.'pages/view-tree/'.$part["id"].'/">'.$part["title"].'</a>';
		}
		$result = array(
			"id" => $f["id"],
			"title" => $f["nav_title"],
			"description" => BigTree::trimLength(strip_tags($res["page_content"]),450),
			"link" => $admin_root."pages/edit/".$f["id"]."/",
			"breadcrumb" => implode(" &rsaquo; ",$bc_parts)
		);
		$pages[] = $result;
		$total_results++;
	}
	$results["Pages"] = $pages;
	
	// Get every module's results based on auto module views.
	$modules = $admin->getModules("name ASC");
	foreach ($modules as $m) {
		// Get all auto module view actions for this module.
		$actions = $admin->getModuleActions($m);
		foreach ($actions as $action) {
			if ($action["view"]) {
				$view = BigTreeAutoModule::getView($action["view"]);
				$m_results = array();
				
				$qcolumns = sqlcolumns($view["table"]);
				$qparts = array();
				foreach ($qcolumns as $column => $data) {
					$qparts[] = "`$column` LIKE $w";
				}
				
				// Get matching results
				$qs = sqlquery("SELECT * FROM `".$view["table"]."` WHERE ".implode(" OR ",$qparts));
				while ($r = sqlfetch($qs)) {
					$m_results[] = $r;
					$total_results++;
				}
				
				if (count($m_results)) {
					$results[$m["name"]][] = array(
						"view" => $view,
						"results" => $m_results,
						"module" => $m
					);
				}
			}
		}
	}
?>
<h1>Advanced Search</h1>
<form class="adv_search" method="post" action="<?=$admin_root?>search/">
	<h3><?=number_format($total_results)?> Search results for &ldquo;<?=end($path)?>&rdquo;</h3>
	<input type="image" src="<?=$admin_root?>images/quick-search-icon.png" />
	<input type="search" name="query" autocomplete="off" value="<?=htmlspecialchars(end($path))?>" />
</form>

<div class="form_container">
	<header>
		<nav>
			<div class="more">
				<div>
					<? $x = 0; foreach ($results as $key => $r) { $x++; ?>
					<a<? if ($x == 1) { ?> class="active"<? } ?> href="#<?=$cms->urlify($key)?>"><?=htmlspecialchars($key)?></a>
					<? } ?>
				</div>
			</div>
		</nav>
	</header>
	<div class="content_container">
		<? $x = 0; foreach ($results as $key => $set) { $x++; ?>
		<section class="content" id="content_<?=$cms->urlify($key)?>"<? if ($x != 1) { ?> style="display: none;"<? } ?>>
			<?
				if ($key != "Pages") {
					foreach ($set as $data) {
						$view = $data["view"];
						$items = $data["results"];
						$module = $data["module"];
						if ($view["type"] == "images" || $view["type"] == "images-group") {
							include BigTree::path("admin/pages/search-views/images.php");
						} else {
							include BigTree::path("admin/pages/search-views/table.php");
						}
					}
				} else {
			?>
			<ul class="adv_search_page_results">
				<? foreach ($set as $item) { ?>
				<li>
					<strong><a href="<?=$admin_root?>pages/edit/<?=$item["id"]?>/"><?=$item["title"]?></a></strong>
					<p><?=$item["description"]?></p>
					<span><?=$item["breadcrumb"]?></span>
				</li>
				<? } ?>
			</ul>
			<?
				}
			?>
		</section>
		<? } ?>	
	</div>
</div>
<script type="text/javascript">
	$(".form_container nav a").click(function() {
		$(".content_container .content").hide();
		href = "content_" + $(this).attr("href").substr(1);
		if ($(href)) {
			$(".form_container nav a").removeClass("active");
			$(this).addClass("active");
			$("#" + href).show();
		}
		
		return false;
	});
	
	BigTreeFormNavBar.init();
</script>