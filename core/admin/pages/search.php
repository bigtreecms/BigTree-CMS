<?php
	namespace BigTree;
	
	$total_results = 0;
	$results = array();
	
	$search_term = $_GET["query"];
	// If this is a link, see if it's internal.
	if (substr($search_term, 0, 7) == "http://" || substr($search_term, 0, 8) == "https://") {
		$search_term = Link::encode($search_term);
	}
	
	$w = "'%".SQL::escape($search_term)."%'";
	
	// Get the "Pages" results.
	$page_results = Page::search($search_term, array("title", "resources", "meta_keywords", "meta_description", "nav_title"), "50");
	$pages = array();

	foreach ($page_results as $page) {
		$access_level = $page->UserAccessLevel;

		if ($access_level) {
			$breadcrumb = $page->Breadcrumb;
			$breadcrumb_parts = array();

			foreach ($breadcrumb as $part) {
				$breadcrumb_parts[] = '<a href="'.ADMIN_ROOT.'pages/view-tree/'.$part["id"].'/">'.$part["title"].'</a>';
			}

			$pages[] = array(
				"id" => $page->ID,
				"title" => $page->NavigationTitle,
				"description" => Text::trimLength(strip_tags($page->Resources["page_content"]), 450),
				"link" => ADMIN_ROOT."pages/edit/".$page->ID."/",
				"breadcrumb" => implode(" &rsaquo; ", $breadcrumb_parts)
			);
		}
	}

	if ($count = count($pages)) {
		$results["Pages"] = $pages;
		$total_results += $count;
	}
	
	// Get every module's results based on auto module views.
	$modules = Module::all("name ASC");

	foreach ($modules as $module) {
		$views = ModuleView::allByModule($module->ID);

		foreach ($views as $view) {
			$table_description = SQL::describeTable($view->Table);
			$query_parts = array();

			foreach ($table_description["columns"] as $column => $data) {
				$query_parts[] = "`$column` LIKE $w";
			}

			// Get matching results
			$module_results = SQL::fetchAll("SELECT * FROM `".$view["table"]."` WHERE ".implode(" OR ", $query_parts));

			if ($count = count($module_results)) {
				$total_results += $count;
				$results[$module->Name][] = array(
					"view" => $view,
					"results" => Link::decode($module_results),
					"module" => $module
				);
			}
		}
	}
?>
<form class="adv_search" method="get" action="<?=ADMIN_ROOT?>search/">
	<h3><?=number_format($total_results)?> <?=Text::translate("Search results for")?> &ldquo;<?=htmlspecialchars($_GET["query"])?>&rdquo;</h3>
	<label for="search_field_query" class="visually_hidden">Query</label>
	<input id="search_field_query" type="search" name="query" autocomplete="off" value="<?=htmlspecialchars($_GET["query"])?>"/>
	<input type="submit"/>
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
								foreach ($results as $key => $page_results) {
									$x++;
							?>
							<a<?php if ($x == 1) { ?> class="active"<?php } ?> href="#<?=Link::urlify($key)?>"><?=htmlspecialchars($key)?></a>
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
		<section class="content" id="content_<?=Link::urlify($key)?>"<?php if ($x != 1) { ?> style="display: none;"<?php } ?>>
			<?php
				if ($key != "Pages") {
					foreach ($set as $data) {
						$view = $data["view"];
						$items = $data["results"];
						$module = $data["module"];

						if ($view["type"] == "images" || $view["type"] == "images-group") {
							include Router::getIncludePath("admin/pages/search-views/images.php");
						} else {
							include Router::getIncludePath("admin/pages/search-views/table.php");
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
		<p><?=Text::translate("No results were found.")?></p>
	</section>
	<?php } ?>
</div>
<script>
	// Override default controls
	$(".container nav a").click(function () {
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