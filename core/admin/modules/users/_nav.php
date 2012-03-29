<nav class="sub">
	<ul>
		<li><a href="<?=$admin_root?>users/view/"<? if (end($path) == "view") { ?> class="active"<? } ?>><span class="icon_small icon_small_list"></span>View Users</a></li>
		<li><a href="<?=$admin_root?>users/add/"<? if (end($path) == "add") { ?> class="active"<? } ?>><span class="icon_small icon_small_add"></span>Add User</a></li>
	</ul>
</nav>