<script>
	Vue.component("field-hidden-value", {
		props: ["name", "value"]
	});
</script>

<template>
	<input type="hidden" :name="name" :value="value">
</template>