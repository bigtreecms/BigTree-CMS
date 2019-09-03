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
				current_value: this.value,
				uid: this._uid
			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" :label_for="'field_' + uid">
		<div class="field_select_wrapper">
			<select :name="name" :id="'field_' + uid" class="field_select" :required="required" v-model="current_value">
				<option v-for="option in options" :value="option.value">{{ option.title }}</option>
			</select>
			<icon icon="arrow_drop_down" wrapper="field_select"></icon>
		</div>
	</field>
</template>