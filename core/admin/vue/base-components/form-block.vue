<script>
	Vue.component("FormBlock", {
		props: [
			"method",
			"action",
			"buttons",
			"redirect"
		],
		
		data: function() {
			return {
				calculated_buttons: this.buttons ? this.buttons : [{ "title": this.translate("Submit"), "primary": true }],
				calculated_method: this.method ? this.method : "POST",
				submit_event_value: null,
				uid: this._uid,
				validation_count: 0,
				validation_timer: null,
				validation_total: 0
			}
		},
		
		methods: {
			check_validation_count: function() {
				if (this.validation_count === this.validation_total) {
					clearInterval(this.validation_timer);
					this.submit();
				}
			},
			
			increment_validation_total: function() {
				this.validation_total++;
			},
			
			submit: function() {
				let form = $(this.$el);
				
				if (this.redirect) {
					form.off("submit").submit();
				} else {
					let data = new FormData(form.get(0));
					
					$.ajax({
						url: form.attr("action"),
						data: data,
						type: this.calculated_method,
						processData: false,
						contentType: false,
						complete:  (response) => {
							this.$emit("response", response);
						}
					});
				}
			},
			
			validate: function(ev, event_value) {
				ev.preventDefault();

				if (event_value) {
					$("#form_action_" + this.uid).val(event_value);
				}
				
				if (this.validation_total > 0) {
					BigTreeEventBus.$emit("form-block-validation", this);
					this.validation_timer = setInterval(this.check_validation_count, 100);
				} else {
					this.submit();
				}
			}
		},
		
		mounted: function() {
			this.$on("validated", function() {
				this.validation_count++;
			});
		}
	});
</script>

<template>
	<form :method="calculated_method" :action="action" v-on:submit="validate" enctype="multipart/form-data">
		<input type="hidden" name="_bigtree_form_action_" :id="'form_action_' + uid">
		
		<slot></slot>
		
		<div class="save_actions">
			<span v-for="button in calculated_buttons">
				<a v-if="button.url" :href="button.url"
				   class="save_action" :class="button.primary ? 'save_action_primary' : ''">{{ button.title }}</a>
				<button v-else class="save_action" :class="button.primary ? 'save_action_primary' : ''"
						v-on:click="validate($event, button.event ? button.event : null)">{{ button.title }}</button>
			</span>
		</div>
	</form>
</template>