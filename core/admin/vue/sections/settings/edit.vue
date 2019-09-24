<script>
	Vue.component("SettingsValue", {
		props: ["id"],
		data: function() {
			return {
				buttons: [
					{ title: "Update", primary: true }
				]
			}
		},
		methods: {
			submit: function(response) {
			
			}
		}
	});
</script>

<template>
	<form-block v-on:response="submit" :action="WWW_ROOT + 'api/settings/update-value/'" :buttons="buttons">
		<field-type-hidden-value name="id" :value="id"></field-type-hidden-value>
		<div class="fields_wrapper">
			<slot></slot>
		</div>
	</form-block>
</template>