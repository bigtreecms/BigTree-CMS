<script>
	Vue.component("ActionMenu", {
		data: function() {
			return {
				current_value: typeof this.actions[0].value !== "undefined" ? this.actions[0].value : this.actions[0].title,
				current_title: this.actions[0].title
			};
		},
		props: ["actions", "buttons", "base_path", "id", "escaped_actions"],
		methods: {
			change: function(ev) {
				let $target = $(ev.target);

				this.current_title = $target.text();
				this.current_value = $target.data("value");
				this.$parent.$emit("action_menu_change", { id: this.id, value: this.current_value });
			},

			click: async function(ev, index) {
				ev.preventDefault();
				
				if (typeof this.actions[index].confirm !== "undefined") {
					if (!await BigTree.confirm(this.actions[index].confirm)) {
						return;
					}
				}
				
				if (typeof this.actions[index].method !== "undefined") {
					this.actions[index].method(this.id, index);
				} else {
					BigTree.request_partial(this.compute_action_url(this.actions[index]));
				}
			},

			close: function() {
				$(this.$el).find(".action_menu_dropdown").removeClass("action_menu_dropdown_active");
			},

			compute_action_url: function(action) {
				if (action.url) {
					return action.url;
				}

				return ADMIN_ROOT + this.base_path + "/" + action.route + "/" + this.id + "/";
			},

			open: function(event) {
				event.preventDefault();
				event.stopPropagation();

				// Close any existing menus that are open
				$(window).off("click");
				$(".actiom_menu_dropdown").removeClass("action_menu_dropdown_active");

				// Open this menu
				$(window).on("click", this.close);
				$(this.$el).find(".action_menu_dropdown").addClass("action_menu_dropdown_active");
			}
		}
	});
</script>

<template>
	<div class="action_menu">
		<div class="action_menu_default">
			<a v-if="escaped_actions && !buttons" class="action_menu_label" :href="compute_action_url(actions[0])"
			   v-on:click="click($event, 0)" v-html="translate(current_title)"></a>
			<a v-else-if="!buttons" class="action_menu_label" :href="compute_action_url(actions[0])"
			   v-on:click="click($event, 0)">{{ translate(current_title) }}</a>
			<span v-else-if="escaped_actions" class="action_menu_label" v-html="translate(current_title)"></span>
			<span v-else class="action_menu_label">{{ translate(current_title) }}</span>
			<button class="action_menu_trigger" v-on:click="open" type="button"><icon wrapper="action_menu" icon="arrow_drop_down"></icon></button>
		</div>
		<ul class="action_menu_dropdown">
			<li v-for="(action, index) in actions" class="action_menu_item">
				<button v-if="action.method !== 'undefined' && escaped_actions" class="action_menu_link"
						v-on:click="click($event, index)" v-html="translate(action.title)"></button>
				<button v-else-if="action.method !== 'undefined'" class="action_menu_link" v-on:click="click($event, index)">
					{{ translate(action.title) }}
				</button>
				<a v-else-if="escaped_actions && !buttons" class="action_menu_link" :href="compute_action_url(action)"
				   v-on:click="click($event, index)" v-html="translate(action.title)"></a>
				<a v-else-if="!buttons" class="action_menu_link" :href="compute_action_url(action)"
				   v-on:click="click($event, index)">{{ translate(action.title) }}</a>
				<button v-else-if="escaped_actions" v-on:click="change" class="action_menu_link"
						:data-value="action.value" v-html="translate(action.title)"></button>
				<button v-else v-on:click="change" class="action_menu_link" :data-value="action.value">
					{{ translate(action.title) }}
				</button>
			</li>
		</ul>
	</div>
</template>