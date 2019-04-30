<?php
	namespace BigTree;
?>
<div class="container">
	<section>
		<h3><?=Text::translate("Extensions vs. Packages")?></h3>
		<p><?=Text::translate("<strong>Extensions</strong> are self-contained in their own directories. They can not install files that live outside the extension directory. This allows them to easily be installed on any BigTree installation without worrying about colliding with other BigTree components. Extensions can be automatically upgraded through BigTree and can be uninstalled. When creating an extension from existing assets (modules, field types, templates), they will be moved into the /extensions/ directory and may need to have their calls adjusted to meet the guidelines below.")?></p>
		<p><?=Text::translate("<strong>Packages</strong> install into the global component system and can install files into any directory. They are meant to be installed in a controlled manner in a development environment. They are not updatable but can be uninstalled.")?></p>
		<p><?=Text::translate('For more information on building an extension, visit the <a href=":documentation_link:" target="_blank">BigTree Developer Documentation</a>.', false, [":documentation_link:" => "https://www.bigtreecms.org/docs/dev-guide/advanced/extensions/"])?></p>
	</section>
	<footer>
		<a class="button blue" href="<?=DEVELOPER_ROOT?>extensions/build/new/"><?=Text::translate("Build an Extension")?></a>
		<a class="button" href="<?=DEVELOPER_ROOT?>packages/build/"><?=Text::translate("Build a Package")?></a>
	</footer>
</div>