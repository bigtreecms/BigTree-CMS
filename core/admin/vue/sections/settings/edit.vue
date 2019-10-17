<script>
	Vue.component("SettingsValue", {
		props: ["id", "name"],
		data: function() {
			return {
				buttons: [
					{ title: this.translate("Update"), primary: true }
				]
			}
		},
		methods: {
			submit: async function(api) {
				if (api.error) {
					BigTree.announcement = {
						type: "error",
						context: this.translate("Submission Failed"),
						message: this.translate(api.message),
						visible: true
					};
				} else {
					await BigTreeAPI.updateCache("settings", api.response.cache.settings);

					BigTree.announcement = {
						type: "success",
						context: this.translate("Settings"),
						message: this.translate("Updated :name:", { ":name:": this.name }),
						visible: true
					};

					BigTree.request_partial(ADMIN_ROOT + "settings/");
				}
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