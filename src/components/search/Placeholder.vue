<template>
	<div class="card--placeholder">
		<svg class="card-placeholder__gradient">
			<defs>
				<linearGradient id="card-placeholder__gradient">
					<stop offset="0%" :stop-color="light">
						<animate attributeName="stop-color"
							:values="`${light}; ${light}; ${dark}; ${dark}; ${light}`"
							dur="2s"
							repeatCount="indefinite" />
					</stop>
					<stop offset="100%" :stop-color="dark">
						<animate attributeName="stop-color"
							:values="`${dark}; ${light}; ${light}; ${dark}; ${dark}`"
							dur="2s"
							repeatCount="indefinite" />
					</stop>
				</linearGradient>
			</defs>
		</svg>
		<svg class="card-placeholder__placeholder"
			:class="{ 'standalone': standalone }"
			xmlns="http://www.w3.org/2000/svg"
			fill="url(#card-placeholder__gradient)">
			<rect class="card-placeholder__placeholder-line-header" :style="{width: `calc(${randWidth()}%)`}" />
			<rect class="card-placeholder__placeholder-line-one" />
			<rect class="card-placeholder__placeholder-line-two" :style="{width: `calc(${randWidth()}%)`}" />
		</svg>
	</div>
</template>

<script>
export default {
	name: 'Placeholder',
	data() {
		return {
			light: null,
			dark: null,
			standalone: true,
		}
	},
	mounted() {
		const styles = getComputedStyle(document.documentElement)
		this.dark = styles.getPropertyValue('--color-placeholder-dark')
		this.light = styles.getPropertyValue('--color-placeholder-light')
	},

	methods: {
		randWidth() {
			return Math.floor(Math.random() * 20) + 40
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../css/variables';
$clickable-area: 44px;

.card--placeholder {
	width: $stack-width;
	margin-right: $stack-spacing;
	padding: $card-padding;
	transition: box-shadow 0.1s ease-in-out;
	box-shadow: 0 0 2px 0 var(--color-box-shadow);
	border-radius: var(--border-radius-large);
	font-size: 100%;
	margin-bottom: $card-spacing;
	height: 100px;
}

.card-placeholder__gradient {
	position: fixed;
	height: 0;
	width: 0;
	z-index: -1;
}

.card-placeholder__placeholder {
	width: 100%;
	&-line-header,
	&-line-one,
	&-line-two {
		width: 100%;
		height: 1em;
		x: 0;
	}
	&-line-header {
		visibility: hidden;
	}
	&-line-one {
		y: 5px;
	}

	&-line-two {
		y: 25px;
	}

	&.standalone {
		.card-placeholder__placeholder-line-header {
			visibility: visible;
			y: 5px;
		}
		.card-placeholder__placeholder-line-one {
			y: 40px;
		}

		.card-placeholder__placeholder-line-two {
			y: 60px;
		}
	}
}

</style>
