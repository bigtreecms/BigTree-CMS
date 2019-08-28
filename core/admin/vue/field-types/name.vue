<script>
	Vue.component("field-name", {
		props: [
			"title",
			"subtitle",
			"name",
			"value",
			"id"
		]
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true">
		<div class="field_name">
			<div class="field_group">
				<div class="field_wrapper">
					<label class="field_label" :for="'field_first_name_' + this._uid">{{ translate("First Name") }}</label>
					<input class="field_input" :id="'field_first_name_' + this._uid" :name="name + '[first]'"
						   :value="value.first" :placeholder="translate('First Name')" type="text">
				</div>
				
				<div class="field_wrapper">
					<label class="field_label" :for="'field_last_name_' + this._uid">{{ translate("Last Name") }}</label>
					<input class="field_input" :id="'field_last_name_' + this._uid" :name="name + '[last]'"
						   :value="value.last" :placeholder="translate('Last Name')" type="text">
				</div>
			</div>
		</div>
	</field>
</template>