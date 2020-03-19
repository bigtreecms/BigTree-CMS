<script>
	Vue.component("FieldTypeLink", {
		extends: BigTreeFieldType,
		props: ["placeholder", "show_value"],
		data: function() {
			return {
				$container: null,
				$input: null,
				$results: null,
				$value: null
			}
		},
		
		methods: {
			blur: function(event) {
				setTimeout(() => {
					this.$results.hide();
				}, 250);
			},
			
			focus: function(event) {
				if (this.$results.html()) {
					this.$results.show();
				}
			},
			
			keyup: function(event) {
				const query = this.$input.val().trim();

				this.query_change(query);
			},
			
			paste: function(event) {
				const clipboard_data = event.originalEvent.clipboardData || window.clipboardData;
				const pasted_data = clipboard_data.getData('Text');

				this.query_change(pasted_data);
			},
			
			query_change: function(query) {
				this.$value.val(query);
				this.$input.attr("placeholder", "");

				if (!query.length) {
					this.$results.hide().html("");
				} else {
					if (query.toLowerCase().substr(0, 7) === "http://" || query.toLowerCase().substr(0, 8) === "https://") {
						this.$results.hide().html("");
					} else {
						this.$results.load("admin_root/ajax/link-field-search/", { query: query }, () => {
							this.$results.show();
						});
					}
				}
			},
			
			result_click: function(event) {
				event.preventDefault();
				event.stopPropagation();
				
				const $clicked = $(event.target);

				this.$value.val($clicked.attr("href"));
				this.$input.val("").attr("placeholder", $clicked.attr("data-placeholder"));
				this.$results.hide().html("");
			}
		},
		
		mounted: function() {
			this.$container = $(this.$el);
			this.$input = this.$container.find("input[type=text]");
			this.$results = this.$container.find(".field_link_results_wrapper");
			this.$value = this.$container.find("input[type=hidden]");
			this.$results.on("click", "a", this.result_click);
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" :label_for="'field_' + uid" :required="required" :error="error">
		<input type="hidden" :name="name" :value="value">
		<div class="field_link">
			<icon wrapper="field_link" icon="link"></icon>
			<input class="field_input field_input_link" type="text" :name="name" :value="show_value ? value : ''"
				   :placeholder="placeholder" :id="'field_' + uid" :disabled="disabled"
				   v-on:keyup="keyup" v-on:paste="paste" v-on:focus="focus" v-on:blur="blur">
		</div>
		<div class="field_link_results_wrapper" style="display: none;"></div>
	</field>
</template>