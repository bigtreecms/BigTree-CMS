<?php
	namespace BigTree;
	
	Router::setLayout("new");
	
	if (count($_POST)) {
		print_r($_POST);
		print_r($_FILES);
		die();
	}
?>
<form-block action="<?=ADMIN_ROOT?>test/" method="POST" :buttons="[{
	title: 'Save',
	event: 'save'
}, {
	title: 'Save & Publish',
	event: 'publish',
	primary: true
}]">
	<div class="blocks_wrapper">
	<!--
	<field-type-address title="Address" name="address" value="{}" required="true"></field-type-address>
	<field-type-file-upload title="File Upload" name="file" required="true"></field-type-file-upload>
	<field-type-checkbox-group title="Checkbox Group Field" name="checkboxes" required="true" :options="[{'value':'two','title':'Option Two'},{'value':'one','title':'First One'}]"></field-type-checkbox-group>
	<field-type-list name="list" required="true" title="List Field" value="one" :options="[{'value': '', 'title': ''}, {'value':'two','title':'Option Two'},{'value':'one','title':'First One'}]"></field-type-list>
	<field-type-radio title="Radio Field" value="" required="true" :options="[{'value':'two','title':'Option Two'},{'value':'one','title':'First One'}]"></field-type-radio>
	<field-type-html title="HTML Field" value="<h2>Current Contents</h2>" name="html" type="simple" required="true"></field-type-html>
	<field-type-phone-number title="Phone Number" required="true" name="phone" :value="{'phone_1': '410', 'phone_2': '537', 'phone_3': '5007' }"></field-type-phone-number>
	<field-type-name title="Name" subtitle="Name subtitle" required="true" name="name" :value="{'first':'Tim','last':'Buckingham'}"></field-type-name>
	-->
	<field-type-text title="This is the title" subtitle="this is a subtitle" name="poop" value="poopers" maxlength="20" required="true"></field-type-text>
	<!--
	<field-type-text required="true" type="textarea" title="This is a text area" name="textarea" value="This is the current content" maxlength="200"></field-type-text>
	<field-type-hidden-value name="hidden" value="testing"></field-type-hidden-value>-->
	</div>
</form-block>