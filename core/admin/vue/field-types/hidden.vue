<script>
	Vue.component("FieldTypeHiddenValue", {
		extends: BigTreeFieldType
	});
</script>

<template>
	<input type="hidden" :name="name" :value="value">
</template>