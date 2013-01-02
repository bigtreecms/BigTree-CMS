<?
	include "_header.php";
	
	$currentPage = BigTree::currentURL();
	
	// Get all the categories, authors, and months with posts in them.
	$categories = $dogwood->getCategories();
	$authors = $dogwood->getAuthors();
	$archives = $dogwood->getMonths();
?>
<div class="row_12 blog" id="subpage">
	<aside class="cell_3 right sidebar">
		<nav class="subnav blognav">
			<a href="#" class="nav_label">Navigation</a>
			<div class="nav_options">
				<?					
					if (count($categories)) {
				?>
				<strong>Categories</strong>
				<ul>
					<? 
						$i = 0;
						foreach ($categories as $category) {
							$i++; 
							$link = $blog_link."category/".$category["route"]."/";
					?>
					<li<? if ($i == count($categories)) { ?> class="last"<? } ?>><a href="<?=$link?>"<? if (strpos($currentPage,$link) !== false) { ?> class="active"<? } ?>><?=$category["title"]?></a></li>
					<?
						} 
					?>
				</ul>
				<? 
					}
					
					if (count($authors)) {
				?>
				<strong>Authors</strong>
				<ul>
					<? 
						$i = 0;
						foreach ($authors as $author) {
							$i++;
							$link = $blog_link."author/".$author["route"]."/";
					?>
					<li<? if ($i == count($authors)) { ?> class="last"<? } ?>><a href="<?=$link?>"<? if (strpos($currentPage,$link) !== false) { ?> class="active"<? } ?>><?=$author["name"]?></a></li>
					<?
						} 
					?>
				</ul>
				<? 
					}
					
					if (count($archives)) {
				?>
				<strong>Archives</strong>
				<ul>
					<? 
						$i = 0;
						foreach ($archives as $archive) { 
							$i++;
							$month = date("F Y", strtotime($archive));
							$link = $blog_link."month/".date("Y-m",strtotime($archive))."/";
					?>
					<li<? if ($i == count($archives)) { ?> class="last"<? } ?>><a href="<?=$link?>"<? if (strpos($currentPage,$link) !== false) { ?> class="active"<? } ?>><?=$month?></a></li>
					<?
						} 
					?>
				</ul>
				<?
					}
				?>
				<strong>Search</strong>
				<form class="dogwood_search" method="post" action="<?=$blog_link?>search/">
					<input type="text" name="query" class="query" value="" placeholder="Query" />
					<input type="submit" value="Go" class="submit" />
				</form>
			</div>
		</nav>
	</aside>
	<div class="cell_9 content">
		<?=$bigtree["content"]?>
	</div>
</div>
<script src="http://s7.addthis.com/js/250/addthis_widget.js#pubid=xa-4ff2070613781eec"></script>
<? include "_footer.php" ?>