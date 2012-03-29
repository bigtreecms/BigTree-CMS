<nav class="sub">
	<ul>
		<li><a href="<?=$admin_root?>dashboard/vitals-statistics/404/"<? if (end($path) == "404") { ?> class="active"<? } ?>><span class="icon_small icon_small_error"></span>Active 404s</a></li>
		<li><a href="<?=$admin_root?>dashboard/vitals-statistics/404/ignored/"<? if (end($path) == "ignored") { ?> class="active"<? } ?>><span class="icon_small icon_small_ignored"></span>Ignored 404s</a></li>
		<li><a href="<?=$admin_root?>dashboard/vitals-statistics/404/301/"<? if (end($path) == "301") { ?> class="active"<? } ?>><span class="icon_small icon_small_sign"></span>301 Redirects</a></li>
	</ul>
</div>