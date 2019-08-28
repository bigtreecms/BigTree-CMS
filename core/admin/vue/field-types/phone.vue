<script>
	Vue.component("field-phone", {
		props: [
			"title",
			"subtitle",
			"name",
			"value",
			"required"
		]
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true">
		<div class="field_phone">
			<div class="field_group">
				<div class="field_wrapper">
					<label class="field_label" :for="'field_phone_1_' + this._uid">{{ translate("First 3 Digits") }}</label>
					<input class="field_input" :id="'field_phone_1_' + this._uid" :name="name + '[phone_1]'"
						   :value="value.phone_1" :required="required" placeholder="***" type="tel">
				</div>
				
				<div class="field_wrapper">
					<label class="field_label" :for="'field_phone_2_' + this._uid">{{ translate("Second 3 Digits") }}</label>
					<input class="field_input" :id="'field_phone_2_' + this._uid" :name="name + '[phone_2]'"
						   :value="value.phone_2" :required="required" placeholder="***" type="tel">
				</div>
				
				<div class="field_wrapper">
					<label class="field_label" :for="'field_phone_3_' + this._uid">{{ translate("Last 4 Digits") }}</label>
					<input class="field_input" :id="'field_phone_3_' + this._uid" :name="name + '[phone_3]'"
						   :value="value.phone_3" :required="required" placeholder="****" type="tel">
				</div>
			</div>
		</div>
	</field>
</template>