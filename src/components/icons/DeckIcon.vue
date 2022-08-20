<template>
	<svg xmlns="http://www.w3.org/2000/svg"
		:height="size"
		:width="size"
		version="1.1"
		viewBox="0 0 16 16">
		<rect ry="1"
			height="8"
			width="14"
			y="7"
			x="1" />
		<rect ry=".5"
			height="1"
			width="12"
			y="5"
			x="2" />
		<rect ry=".5"
			height="1"
			width="10"
			y="3"
			x="3" />
		<rect ry=".5"
			height="1"
			width="8"
			y="1"
			x="4" />
	</svg>
</template>
<script>
export default {
	name: 'DeckIcon',
	props: {
		size: {
			type: Number,
			default: 16,
		},
	},
}
</script>
