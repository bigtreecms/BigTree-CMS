<?php
	namespace BigTree;
	
	Router::setLayout("new");
	
	if (count($_POST)) {
		print_r($_POST);
		print_r($_FILES);
		die();
	}
?>
<help-text text="Donec id elit non mi porta gravida at eget metus. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus."></help-text>
<form-block action="<?=ADMIN_ROOT?>test/" method="POST" :buttons="[{
	title: 'Save',
	event: 'save'
}, {
	title: 'Save & Publish',
	event: 'publish',
	primary: true
}]">
	<div class="fields_wrapper theme_grid">
		<field-type-relationship title="One to Many Field" subtitle="A subtitle" :value="[]" :options="[
 { 'value': '1', 'title': 'First One'},
 { 'value': '2', 'title': 'Second'},
 { 'value': '3', 'title': 'Third' }
]" name="one_to_many" draggable="true" required="true" minimum="2" maximum="3"></field-type-relationship>
		<?php /*
		<field-type-matrix title="Test Matrix Field" subtitle="A subtitle" limit="3" :value='[
		
			{"alt": "Test alt text", "description": "<p>This is a description.</p>", "__internal-title": "Test alt text", "__internal-subtitle": "This is a description." },
			{"alt": "Second entry alt text", "description": "<p>This is a second description.</p>", "__internal-title": "Second entry alt text", "__internal-subtitle": "This is a second description." },
		]' :columns='[
			{
                            "type": "text",
                            "id": "alt",
                            "title": "Alt description",
                            "subtitle": "",
                            "settings": ""
                        },
                        {
                            "type": "html",
                            "id": "description",
                            "title": "Description",
                            "subtitle": "",
                            "display_title": "on",
                            "settings": "{\"simple\":\"on\",\"simple_by_permission\":\"0\"}"
                        }
		]'>
			<template v-slot:'test'>
				<field-type-text>
			</template>
			
			in theory the form could loop through drawing <template v-slot> for all the existing entries and then a <template v-slot-empty> for
			the empty one to be copied, but would Javascript hooks be preserved? Probably not. Maybe we have an AJAX request for adding another option?
			We need to test this with custom fields that aren't vue based.
			
		</field-type-matrix>
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
 */ ?>
	</div>
</form-block>