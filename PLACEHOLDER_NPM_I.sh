# PLACEHOLDER SCRIPT FOR GETTING DEPENDENCIES vue3 ready


npm remove @vue/vue2-jest vue-template-compiler vue-at vuex-router-sync --force
npm install vue@3 vuex@4.1.0 vue-router@4.5.0 @vue/vue3-jest ../../../../../webpack-vue-config/ ../../../../../nextcloud-dialogs @vue/compat @vue/compiler-sfc
npm ci
