<?php
	namespace BigTree;
	
	function include_with(string $path, array $variables = []): void {
		foreach ($variables as $key => $value) {
			$$key = $value;
		}
		
		if (file_exists($path)) {
			include $path;
		} else {
			include Router::getIncludePath("layouts/partials/".$path.".php");
		}
	}
	
	function icon(string $class, string $name): void {
?>
<span class="<?=$class?>_icon">
	<svg class="icon icon_<?=$name?>">
		<use xlink:href="<?=ADMIN_ROOT?>images/icons.svg#<?=$name?>"></use>
	</svg>
</span>
<?php
	}
	
	function button(string $class, string $title, string $url, string $icon): void {
?>
<a class="<?=$class?>_button" href="<?=$url?>">
	<span class="<?=$class?>_button_inner">
		<span class="<?=$class?>_button_icon"><?php icon($class."_button", $icon) ?></span>
		<span class="<?=$class?>_button_label"><?=Text::translate($title)?></span>
	</span>
</a>
<?php
	}

	
	