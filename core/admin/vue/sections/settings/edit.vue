<script>
	Vue.component("SettingsValue", {
		props: ["id", "value"],
		data: function() {
			return {
				buttons: [
					{ title: "Update", primary: true }
				]
			}
		},
		methods: {
			submit: function(response) {
				console.log(response);
			}
		}
	});
</script>

<template>
	<form-block v-on:response="submit" :action="WWW_ROOT + 'api/settings/update-value/'" :buttons="buttons">
		<field-type-hidden-value name="id" :value="id"></field-type-hidden-value>
		<slot></slot>
	</form-block>
</template>