<script>
	Vue.component("FieldTypeName", {
		extends: FieldType
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true" :required="required" :error="error">
		<div class="field_name">
			<div class="field_group">
				<div class="field_wrapper">
					<label class="field_label" :for="'field_first_name_' + uid">{{ translate("First Name") }}</label>
					<input class="field_input" :id="'field_first_name_' + uid" :name="name + '[first]'"
						   :value="value.first" :placeholder="translate('First Name')" type="text" :required="required">
				</div>
				
				<div class="field_wrapper">
					<label class="field_label" :for="'field_last_name_' + uid">{{ translate("Last Name") }}</label>
					<input class="field_input" :id="'field_last_name_' + uid" :name="name + '[last]'"
						   :value="value.last" :placeholder="translate('Last Name')" type="text" :required="required">
				</div>
			</div>
		</div>
	</field>
</template>