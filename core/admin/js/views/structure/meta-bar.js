Vue.component("meta-bar", {
	"props": ["items"],
	"template":
		`<div class="meta_bar">
			<div v-for="item in items" class="meta_bar_detail">
				<span class="meta_bar_label">{{ item.title }}</span>
				<template v-if="item.type == 'visual'">
					<span class="meta_bar_value meta_bar_value_graph">
						<span class="meta_bar_visual" :class='{ "meta_bar_visual_bad": item.value < 30, "meta_bar_visual_good": item.value > 30 && item.value < 70, "meta_bar_visual_great": item.value > 70 }' :style="'width: ' + item.value + '%;'">{{ item.value }}</span>
					</span>
				</template>
				<template v-else>
					<span class="meta_bar_value meta_bar_value_text">{{ item.value }}</span>
				</template>
			</div>
		</div>`
});