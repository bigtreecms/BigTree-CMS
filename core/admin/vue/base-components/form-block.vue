<script>
	Vue.component("FormBlock", {
		props: [
			"method",
			"action",
			"redirect"
		],
		
		data: function() {
			return {
				calculated_method: this.method ? this.method : "POST",
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
				console.log("done");
			},
			
			validate: function(ev) {
				ev.preventDefault();
				
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
	<div class="blocks_wrapper">
		<form :method="calculated_method" :action="action" v-on:submit="validate">
			<input type="submit" value="Submit">
			<slot></slot>
		</form>
	</div>
</template>