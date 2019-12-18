const BigTreeFieldType = Vue.extend({
	props: [
		"title",
		"subtitle",
		"name",
		"value",
		"required",
		"disabled"
	],

	data: function() {
		return {
			current_value: this.value,
			error: "",
			uid: this._uid
		}
	},

	methods: {
		validate: function() {
			if (this.required && !this.current_value) {
				this.error = this.translate("Required");
				this.$parent.$emit("field-error");

				return;
			}

			this.error = null;
			this.$parent.$emit("validated");
		}
	},

	mounted: function() {
		if (this.required && this.$parent && this.$parent.increment_validation_total) {
			this.$parent.increment_validation_total();
			BigTreeEventBus.$on("form-block-validation", this.validate);
		}
	}
});