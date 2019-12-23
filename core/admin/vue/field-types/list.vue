<script>
	Vue.component("FieldTypeList", {
		extends: BigTreeFieldType,
		props: ["options"]
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" :label_for="'field_' + uid" :required="required" :error="error">
		<div class="field_select_wrapper">
			<select :name="name" :id="'field_' + uid" class="field_select" :required="required" :disabled="disabled"
					v-model="current_value">
				<option v-for="option in options" :value="option.value">{{ option.title }}</option>
			</select>
			<icon icon="arrow_drop_down" wrapper="field_select"></icon>
		</div>
	</field>
</template>