<?php
	$total_results = 0;
	$results = array();
	
	$search_term = $_GET["query"];
	// If this is a link, see if it's internal.
	if (substr($search_term,0,7) == "http://" || substr($search_term,0,8) == "https://") {
		$search_term = $admin->makeIPL($search_term);
	}
	
	$w = "'%".sqlescape($search_term)."%'";
	
	// Get the "Pages" results.
	$r = $admin->searchPages($search_term,array("title","resources","meta_keywords","meta_description","nav_title"),"50");
	$pages = array();
	foreach ($r as $f) {
		$access_level = $admin->getPageAccessLevel($f["id"]);
		if ($access_level) {
			$res = json_decode($f["resources"],true);
			$bc = $cms->getBreadcrumbByPage($f);
			$bc_parts = array();
			foreach ($bc as $part) {
				$bc_parts[] = '<a href="'.ADMIN_ROOT.'pages/view-tree/'.$part["id"].'/">'.$part["title"].'</a>';
			}
			$result = array(
				"id" => $f["id"],
				"title" => $f["nav_title"],
				"description" => BigTree::trimLength(strip_tags($res["page_content"]),450),
				"link" => ADMIN_ROOT."pages/edit/".$f["id"]."/",
				"breadcrumb" => implode(" &rsaquo; ",$bc_parts)
			);
			$pages[] = $result;
			$total_results++;
		}
	}
	if (count($pages)) {
		$results["Pages"] = $pages;
	}
	
	// Get every module's results based on auto module views.
	$modules = $admin->getModules("name ASC");
	foreach ($modules as $module) {
		$views = $admin->getModuleViews($module["id"]);
		foreach ($views as $view) {
			$module_results = array();
				
			$table_description = BigTree::describeTable($view["table"]);
			$qparts = array();
			foreach ($table_description["columns"] as $column => $data) {
				$qparts[] = "`$column` LIKE $w";
			}
				
			// Get matching results
			$qs = sqlquery("SELECT * FROM `".$view["table"]."` WHERE ".implode(" OR ",$qparts));
			// Ignore SQL failures because we might have bad collation.
			while ($r = sqlfetch($qs,true)) {
				foreach ($r as &$piece) {
					$piece = $cms->replaceInternalPageLinks($piece);
				}
				unset($piece);
				$module_results[] = $r;
				$total_results++;
			}
			
			if (count($module_results)) {
				$results[$m["name"]][] = array(
					"view" => $view,
					"results" => $module_results,
					"module" => $module
				);
			}
		}
	}
?>
<form class="adv_search" method="get" action="<?=ADMIN_ROOT?>search/">
	<h3><?=number_format($total_results)?> Search results for &ldquo;<?=htmlspecialchars($_GET["query"])?>&rdquo;</h3>
	<input type="search" name="query" autocomplete="off" value="<?=htmlspecialchars($_GET["query"])?>" />
	<input type="submit" />
</form>

<div class="container">
	<?php if (count($results)) { ?>
	<header>
		<div class="sticky_controls">
			<div class="shadow">
				<nav>
					<div class="more">
						<div>
							<?php
								$x = 0;
								foreach ($results as $key => $r) {
									$x++;
							?>
							<a<?php if ($x == 1) { ?> class="active"<?php } ?> href="#<?=$cms->urlify($key)?>"><?=htmlspecialchars($key)?></a>
							<?php
								}
							?>
						</div>
					</div>
				</nav>
			</div>
		</div>
	</header>
	<div class="content_container">
		<?php
			$x = 0;
			foreach ($results as $key => $set) {
				$x++;
		?>
		<section class="content" id="content_<?=$cms->urlify($key)?>"<?php if ($x != 1) { ?> style="display: none;"<?php } ?>>
			<?php
				if ($key != "Pages") {
					foreach ($set as $data) {
						$view = $data["view"];
						$items = $data["results"];
						$module = $data["module"];
						$view["edit_url"] = str_replace("MODULE_ROOT",ADMIN_ROOT.$module["route"]."/",$view["edit_url"]);

						if ($view["type"] == "images" || $view["type"] == "images-group") {
							include BigTree::path("admin/pages/search-views/images.php");
						} else {
							include BigTree::path("admin/pages/search-views/table.php");
						}
					}
				} else {
			?>
			<ul class="adv_search_page_results">
				<?php foreach ($set as $item) { ?>
				<li>
					<strong><a href="<?=ADMIN_ROOT?>pages/edit/<?=$item["id"]?>/"><?=$item["title"]?></a></strong>
					<p><?=$item["description"]?></p>
					<span><?=$item["breadcrumb"]?></span>
				</li>
				<?php } ?>
			</ul>
			<?php
				}
			?>
		</section>
		<?php
			}
		?>	
	</div>
	<?php } else { ?>
	<section>
		<p>No results were found.</p>
	</section>
	<?php } ?>
</div>
<script>
	// Override default controls
	$(".container nav a").click(function() {
		$(".content_container .content").hide();
		var href = "content_" + $(this).attr("href").substr(1);
		if ($(href)) {
			$(".container nav a").removeClass("active");
			$(this).addClass("active");
			$("#" + href).show();
		}
		
		return false;
	});

	BigTreeFormNavBar.init();
</script>