<hr>

<h3>Thumbnail / Poster Image Options</h3>

<fieldset>
	<label>Upload Directory <small>(relative to SITE_ROOT)</small></label>
	<input type="text" name="directory" value="<?=htmlspecialchars($data["directory"])?>" />
</fieldset>

<?php
	// Just use the regular image options
	include BigTree::path("admin/field-types/_image-options.php");
