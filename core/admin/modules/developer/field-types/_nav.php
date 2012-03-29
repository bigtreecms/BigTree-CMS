<nav class="sub">
	<ul>
		<li><a href="<?=$section_root?>view/"<? if (end($path) == "view") { ?> class="active"<? } ?>><span class="icon_small icon_small_list"></span>View Field Types</a></li>
		<li><a href="<?=$section_root?>add/"<? if (end($path) == "add") { ?> class="active"<? } ?>><span class="icon_small icon_small_add"></span>Add Field Type</a></li>
	</ul>
</nav>