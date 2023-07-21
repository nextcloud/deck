const stylelintConfig = require('@nextcloud/stylelint-config')

stylelintConfig.rules['selector-pseudo-element-no-unknown'][1].ignorePseudoElements.push('v-deep')

module.exports = stylelintConfig
