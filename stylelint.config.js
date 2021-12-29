const stylelintConfig = require('@nextcloud/stylelint-config')

stylelintConfig.rules['at-rule-no-unknown'] = null
stylelintConfig.rules['scss/at-rule-no-unknown'] = true

module.exports = stylelintConfig
