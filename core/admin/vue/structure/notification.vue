<script>
	Vue.component("Notification", {
		props: ["context", "message", "type"],
		data: function() {
			return {
				hidden: false,
				hide_timer: null,
				remove_data_timer: null
			}
		},
		computed: {
			icon: function() {
				let icon = "";

				if (this.type === "success") {
					icon = "check";
				} else if (this.type === "alert") {
					icon = "warning";
				} else if (this.type === "error") {
					icon = "clear";
				}

				return icon;
			},
			visible: function() {
				let visible = (this.context || this.message) && !this.hidden;

				if (this.hide_timer) {
					clearTimeout(this.hide_timer);
				}

				if (visible) {
					if (this.remove_data_timer) {
						clearTimeout(this.remove_data_timer);
					}

					this.hide_timer = setTimeout(() => {
						this.hidden = true;
						this.remove_data();
					}, 5000);
				}

				return visible;
			}
		},
		methods: {
			close: function(ev) {
				if (ev) {
					ev.preventDefault();
				}

				this.hidden = true;
				this.remove_data();
			},
			remove_data: function() {
				setTimeout(() => {
					BigTree.notification = { context: "", message: "", type: "" };
					this.hidden = false;
				}, 500);
			}
		}
	});
</script>

<template>
	<div class="notification" :class="['theme_' + type, !visible ? 'closed' : '']" :aria-hidden="!visible" aria-live="polite">
		<div class="notification_inner">
			<span class="notification_flag">
				<icon wrapper="notification_flag" :icon="icon"></icon>
			</span>

			<div class="notification_body">
				<span class="notification_hint" v-if="context">{{ context }}</span>
				<span class="notification_label" v-if="message">{{ message }}</span>
			</div>

			<button v-on:click="close" class="notification_close">
				<icon wrapper="notification_close" icon="clear"></icon>
				<span class="notification_close_label">Close Notification</span>
			</button>
		</div>
	</div>
</template>