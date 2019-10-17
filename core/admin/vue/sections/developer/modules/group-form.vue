<script>
	Vue.component("ModuleGroupForm", {
		props: ["id", "name", "action"],
		data: function() {
			return {
				buttons: [
					{
						title: this.translate(this.action === "update" ? "Update" : "Create"),
						primary: true
					}
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
					await BigTreeAPI.updateCache("module-groups", api.response.cache["module-groups"]);

					BigTree.announcement = {
						type: "success",
						context: this.translate("Module Groups"),
						message: this.translate((this.action === "create" ? "Created" : "Updated") + " Group"),
						visible: true
					};

					BigTree.request_partial(ADMIN_ROOT + "developer/modules/groups/");
				}
			}
		}
	});
</script>

<template>
	<form-block v-on:response="submit" :action="WWW_ROOT + 'api/module-groups/' + this.action + '/'" :buttons="buttons">
		<field-type-hidden-value v-if="id" name="id" :value="id"></field-type-hidden-value>
		<div class="fields_wrapper">
			<field-type-text :name="'name'" :value="name" required="true" :title="this.translate('Name')"></field-type-text>
		</div>
	</form-block>
</template>