<form>
	<div class="container">
		<summary>
			<h2>Available Interfaces</h2>
		</summary>
		<section>
			<div class="contain">
				<div class="left">
					<h3><span class="icon_small_category"></span> Views</h3>
					<p>Views are lists of database content. Views can have associated actions such as featuring, archiving, approving, editing, and deleting content.</p>
					<a href="<?=DEVELOPER_ROOT?>modules/views/add/<?=$_GET["module"]?>/" class="button">Add View</a>
				</div>
				<div class="right">
					<h3><span class="icon_small_bar_graph"></span> Reports</h3>
					<p>Reports allow your admin users to filter database content. Reports can either generate a filtered view (based on an existing View interface) or export the data to a CSV.</p>
					<a href="<?=DEVELOPER_ROOT?>modules/reports/add/<?=$_GET["module"]?>/" class="button">Add Report</a>
				</div>
			</div>
			<hr />
			<div class="contain">
				<div class="left">
					<h3><span class="icon_small_blog"></span> Forms</h3>
					<p>Forms are used for creating and editing database content by admin users.</p>
					<a href="<?=DEVELOPER_ROOT?>modules/forms/add/<?=$_GET["module"]?>/" class="button">Add Form</a>
				</div>
				<div class="right">
					<h3><span class="icon_small_file_default"></span> Embeddable Forms</h3>
					<p>Embeddable forms allow your front-end users to create database content using your existing field types via iframes.</p>
					<a href="<?=DEVELOPER_ROOT?>modules/embeds/add/<?=$_GET["module"]?>/" class="button">Add Embeddable Form</a>
				</div>
			</div>
		</section>
	</div>
</form>