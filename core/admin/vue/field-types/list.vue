<script>
	Vue.component("field-list", {
		props: [
			"title",
			"subtitle",
			"name",
			"options",
			"value",
			"required"
		],

		data: function() {
			return {
				current_value: this.value
			}
		},
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" :label_for="'field_' + this._uid">
		<div class="field_select_wrapper">
			<select :name="name" :id="'field_' + this._uid" class="field_select" :required="required">
				<option v-for="option in options" :value="option.value" :selected="option.value === value">{{ option.title }}</option>
			</select>
			<icon icon="arrow_drop_down" wrapper="field_select"></icon>
		</div>
	</field>
</template>