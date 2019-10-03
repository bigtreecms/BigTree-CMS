<script>
	Vue.component("SettingsValue", {
		props: ["id", "name"],
		data: function() {
			return {
				buttons: [
					{ title: "Update", primary: true }
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
					BigTree.announcement = {
						type: "success",
						context: this.translate("Settings"),
						message: this.translate("Updated :name:", { ":name:": this.name }),
						visible: true
					};

					let cache = {};
					cache[this.id] = api.response.cache;

					await BigTreeAPI.updateLocalCacheByID("settings", cache);
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