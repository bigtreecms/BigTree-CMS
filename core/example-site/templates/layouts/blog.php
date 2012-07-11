<? include "_header.php" ?>
<div class="row_12 blog" id="subpage">
	<aside class="cell_2 right sidebar">
		<nav class="subnav blognav">
			<a href="#" class="nav_label">Navigation</a>
			<div class="nav_options">
				<?
					$currentPage = BigTree::currentURL();
					
					$categories = $dogwood->getCategories();
					$authors = $dogwood->getAuthors();
					$archives = $dogwood->getMonths();
					
					$count = count($categories) - 1;
					if ($count > -1) {
				?>
				<strong>Categories</strong>
				<ul>
					<? 
						$i = 0;
						foreach ($categories as $category) { 
							$link = $blog_link . "category/" . $category["route"] . "/";
					?>
					<li<? if ($i == $count) { ?> class="last"<? } ?>><a href="<?=$link?>"<? if ($link == $currentPage) { ?> class="active"<? } ?>><?=$category["title"]?></a></li>
					<? 
							$i++;
						} 
					?>
				</ul>
				<? 
					}
					
					$count = count($authors) - 1;
					if ($count > -1) {
				?>
				<strong>Authors</strong>
				<ul>
					<? 
						$i = 0;
						foreach ($authors as $author) { 
							$link = $blog_link . "author/" . $author["route"] . "/";
					?>
					<li<? if ($i == $count) { ?> class="last"<? } ?>><a href="<?=$link?>"<? if ($link == $currentPage) { ?> class="active"<? } ?>><?=$author["name"]?></a></li>
					<? 
							$i++;
						} 
					?>
				</ul>
				<? 
					}
					
					$count = count($archives) - 1;
					if ($count > -1) {
				?>
				<strong>Archives</strong>
				<ul>
					<? 
						$i = 0;
						foreach ($archives as $archive) { 
							$month = date("F", strtotime($archive));
							$link = $blog_link . "month/" . strtolower($month) . "/";
					?>
					<li<? if ($i == $count) { ?> class="last"<? } ?>><a href="<?=$link?>"<? if ($link == $currentPage) { ?> class="active"<? } ?>><?=$month?></a></li>
					<? 
							$i++;
						} 
					?>
				</ul>
				<?
					}
				?>
			</div>
		</nav>
	</aside>
	<div class="cell_10 content">
		<?=$bigtree["content"]?>
	</div>
</div>
<script type="text/javascript" src="http://s7.addthis.com/js/250/addthis_widget.js#pubid=xa-4ff2070613781eec"></script>
<? include "_footer.php" ?>