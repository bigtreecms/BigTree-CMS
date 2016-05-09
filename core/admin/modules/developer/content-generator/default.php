<?php
    namespace BigTree;
    $forms = $admin->getModuleForms();
?>
<section class="inset_block">
    <p><?=Text::translate("<strong>Content Generator</strong> allows you to create dummy content for your module data, aiding in testing your designs, search, and pagination.")?></p>
</section>
<div class="container">
    <form method="post" action="<?=DEVELOPER_ROOT?>content-generator/generate/">
        <section>
            <fieldset>
                <label><?=Text::translate('Module Form <small>(populates test content into the table for this form)</small>')?></label>
                <select name="form">
                    <?php foreach ($forms as $form) { ?>
                    <option value="<?=$form["id"]?>"><?=$form["title"]?> &mdash; <?=$form["table"]?></option>
                    <?php } ?>
                </select>
            </fieldset>
            <fieldset>
                <label><?=Text::translate('Number of Entries to Create <small>(defaults to 25)</small>')?></label>
                <input type="text" name="count" />
            </fieldset>
        </section>
        <footer>
            <input type="submit" value="<?=Text::translate("Submit", true)?>" class="button blue" />
        </footer>
    </form>
</div>