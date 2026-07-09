module.exports = {
	root: true,
	extends: [
		' @nextcloud',
	],
	rules: {
		'jsdoc/require-param-description': ['warn'],
		'jsdoc/require-param-type': ['warn'],
		'jsdoc/check-param-names': ['warn'],
		'jsdoc/no-undefined-types': ['warn'],
		'jsdoc/require-property-description': ['warn'],
		'import/no-named-as-default-member': ['off'],
	},
}