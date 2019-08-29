<?php
	namespace BigTree;
	
	Router::setLayout("new");
?>
<div class="blocks_wrapper">
	<field-html title="HTML Field" value="<h2>Current Contents</h2>" name="html"></field-html>
	<field-address title="Address" name="address" value="{}"></field-address>
	<field-phone title="Phone Number" name="phone" :value="{'phone_1': '410', 'phone_2': '537', 'phone_3': '5007' }"></field-phone>
	<field-name title="Name" subtitle="Name subtitle" name="name" :value="{'first':'Tim','last':'Buckingham'}"></field-name>
	<field-text title="This is the title" subtitle="this is a subtitle" name="poop" value="poopers" maxlength="20"></field-text>
	<field-text type="textarea" title="This is a text area" name="textarea" value="This is the current content" maxlength="200"></field-text>
</div>