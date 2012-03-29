<h1>
	<span class="integrity"></span>Site Integrity Check
	<? include BigTree::path("admin/modules/dashboard/vitals-statistics/_jump.php"); ?>
</h1>

<p>The site integrity check will search your site for broken/dead links and alert you to their presence should they exist.</p>
<p>You may choose either to include external links (links to other sites) or ignore them.<br />Including external links will significantly slow down the integrity check and may throw false positives.</p>
<br />
<p>
	<a href="external/" class="button"><span class="icon_small icon_small_world"></span>Include External Links</a>
	&nbsp;
	<a href="internal/" class="button"><span class="icon_small icon_small_server"></span>Only Internal Links</a>
</p>