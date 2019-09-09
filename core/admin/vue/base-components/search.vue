<script>
	Vue.component("Search", {
		props: ["label", "placeholder"],
		data: function() {
			return { query: "" };
		},
		methods: {
			save: function() {
				this.$parent.$emit("search.change", this.query);
			}
		}
	});
</script>

<template>
	<div class="component">
		<div class="component_body">
			<form class="search">
				<icon wrapper="search" icon="search"></icon>
				<label class="search_label" for="search_input">{{ translate(label) }}</label>
				<input class="search_input" id="search_input" type="search"
					   :placeholder="translate(placeholder)" v-model="query" v-on:input="save" />
				<input class="search_submit" type="submit" :value="translate('Search')" />
			</form>
		</div>
	</div>
</template>