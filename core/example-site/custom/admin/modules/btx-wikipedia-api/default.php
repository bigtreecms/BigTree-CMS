<?
	include "_heading.php";
	include BigTree::path("admin/auto-modules/_nav.php"); 
?>
<div class="form_container" id="wikipedia_api">
	<section>
		<h2>Module Usage</h2>
		<p>The Wikipedia API module provides a simple way to interact with Wikipedia through the <a href="http://www.mediawiki.org/wiki/API:Main_page" target="_blank">WikiMedia API</a>.</p>
		<p>Create a new instance of the Wikipedia Class: (language defaults to 'en' for English)</p>
		<pre>$btxWikipediaAPI = new BTXWikipediaAPI("language");</pre>
		
		<p>Search for a topic:</p>
		<pre>$btxWikipediaAPI->search("Search Query");</pre>
		
		<p>Fetch an article's content by its URL:</p>
		<pre>$btxWikipediaAPI->article("http://en.wikipedia.com/...");</pre>
	</section>
</div>