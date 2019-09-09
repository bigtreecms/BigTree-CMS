<script>
	Vue.component("FieldTypePhoneNumber", {
		extends: FieldType
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true" :required="required" :error="error">
		<div class="field_phone">
			<div class="field_group">
				<div class="field_wrapper">
					<label class="field_label" :for="'field_phone_1_' + uid">{{ translate("First 3 Digits") }}</label>
					<input class="field_input" :id="'field_phone_1_' + uid" :name="name + '[phone_1]'"
						   :value="value.phone_1" :required="required" placeholder="***" type="tel">
				</div>
				
				<div class="field_wrapper">
					<label class="field_label" :for="'field_phone_2_' + uid">{{ translate("Second 3 Digits") }}</label>
					<input class="field_input" :id="'field_phone_2_' + uid" :name="name + '[phone_2]'"
						   :value="value.phone_2" :required="required" placeholder="***" type="tel">
				</div>
				
				<div class="field_wrapper">
					<label class="field_label" :for="'field_phone_3_' + uid">{{ translate("Last 4 Digits") }}</label>
					<input class="field_input" :id="'field_phone_3_' + uid" :name="name + '[phone_3]'"
						   :value="value.phone_3" :required="required" placeholder="****" type="tel">
				</div>
			</div>
		</div>
	</field>
</template>