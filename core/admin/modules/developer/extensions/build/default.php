<div class="container">
	<section>
		<h3>Extensions vs. Packages</h3>
		<p><strong>Extensions</strong> are self-contained in their own directories. They can not install files that live outside the extension directory. This allows them to easily be installed on any BigTree installation without worrying about colliding with other BigTree components. Extensions can be automatically upgraded through BigTree and can be uninstalled.</p>
		<p><strong>Packages</strong> install into the global component system and can install files into any directory. They are meant to be installed in a controlled manner in a development environment. They are not updatable but can be uninstalled.</p>
		<hr />
		<h3>Extension Guidelines</h3>
		<p>In the examples below, <code>{id}</code> refers to the ID of your extension.</p>
		<ul>
			<li>
				All files for extensions must live in their <code>/extensions/{id}/</code> folder or <code>/site/extensions/{id}/</code> folder.
			</li>
			<li>
				<strong>Feeds</strong> will have their URL changed from <code>/feeds/route/</code> to <code>/feeds/{id}/route/</code>.
			</li>
			<li>
				<strong>Settings</strong> will have their ID changed internally to <code>{id}*{setting}</code>. Your extension can still get/set them via their regular ID but they will need to be called by their new ID if used outside your extension.
			</li>
			<li>
				When including CSS or JavaScript in the admin area, extensions must use the <code>$bigtree["css"]</code> and <code>$bigtree["js"]</code> arrays to include the files. Treat the <code>/extensions/{id}/css/</code> and <code>/extensions/{id}/js/</code> folders as your roots, respectively. For example:
				<pre><code>
$bigtree["js"][] = "news.js";
$bigtree["css"][] = "news.css";
				</code></pre>
			</li>
			<li>
				When including CSS or JavaScript from a front end template, it should be stored in <code>/site/extensions/{id}/css/</code> and <code>/site/exteions/{id}/js/</code> respectively. You should include this manually in link/script tags as there is no direct knowledge of the end user's markup. For example:
				<pre><code>
&lt;script src="&lt;?=SITE_ROOT?&gt;extensions/com.fastspot.news/js/news.js"&gt;&lt;/script&gt;
&lt;link rel="stylesheet" type="text/css" media="screen"
      href="&lt;?=SITE_ROOT?&gt;extensions/com.fastspot.news/css/news.css" /&gt;
				</code></pre>
			</li>
			<li>
				When calling AJAX requests from your front end templates or admin side modules you must append <code>/*/{id}/</code> before the AJAX path. Examples:<br />
				<pre><code>
$.ajax("&lt;?=WWW_ROOT?&gt;*/com.fastspot.news/ajax/get-more-news/"); // Front End Templates
$.ajax("&lt;?=ADMIN_ROOT?&gt;*/com.fastspot.news/ajax/load-news-page/"); // Back End Modules
				</code></pre>
			</li>
			<li>
				To use a layout included in an extension in a template you must set <code>$bigtree["extension_layout"] = "{id}"</code> in addition to <code>$bigtree["layout"] = "layout_file"</code>. For example:
				<pre><code>
$bigtree["extension_layout"] = "com.fastspot.news";
$bigtree["layout"] = "news-layout";
				</code></pre>
			</li>
			<li>
				After creating an extension with BigTree components (modules, templates, field types, etc) they will be automatically moved into your extension directory.
			</li>
			<li>
				Your <code>/site/extensions/{id}/</code> and <code>/extensions/{id}/</code> folders will be automatically included when creating your extension.
			</li>
			<li>
				Your included components will be automatically moved to your <code>/extensions/{id}/</code> directory on extension creation. After initial creation they should be maintained there.
			</li>
			<li>
				You will be given the opportunity to include additional files required by your callouts/templates from your <code>/templates/ajax/</code> and <code>/templates/layouts/</code> folders during the extension creation process. These files will be moved into your <code>/extensions/{id}/</code> directory upon creation.
			</li>
			<li>
				You can not rely on including anything in <code>/custom/inc/required/</code>. Additional files must be manually loaded from your <code>/extensions/{id}/</code>, only module class files will be added to PHP's autoloader.
			</li>
		</ul>
	</section>
	<footer>
		<a class="button blue" href="<?=DEVELOPER_ROOT?>extensions/build/new/">Build an Extension</a>
		<a class="button" href="<?=DEVELOPER_ROOT?>packages/build/">Build a Package</a>
	</footer>
</div>
