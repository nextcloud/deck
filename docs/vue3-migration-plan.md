<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Vue 3 Migration Plan

This plan documents the essential migration path that was actually required to go from Vue 2 to Vue 3 in Deck, based on:

- baseline commit: `c5ee27e59bcc88db5e5e0f4a12160b79e190be41`
- PoC target commit: `5fb7e12da4ed7d36d832e7f5c11d69b83336a24e`

Scope of this document: behavior-compatible migration only (no feature work, no Composition API rewrite).

## Migration principles

- Keep behavior and route structure compatible.
- Prefer local adapter seams over broad rewrites.
- Migrate by subsystem, validate after each subsystem.
- Do not rely on Vue compat build unless strictly necessary.

## Step-by-step implementation

## 1) Upgrade framework and tooling dependencies

Update `package.json` to the Vue 3-compatible stack and test/build tooling:

- `vue` -> `^3.5.x`
- `vue-router` -> `^5.x`
- `vuex` -> `^4.x`
- `@nextcloud/vue` -> `^9.x`
- `@nextcloud/dialogs` -> `^7.x`
- replace Vue 2 jest transformer with `@vue/vue3-jest`
- add `@vue/compiler-sfc` and keep `vue-loader@17+`

Also remove obsolete Vue 2-only dependencies from runtime usage (`vue-click-outside`, `vue-infinite-loading`, `vue-easymde`, `vue-smooth-dnd`, `vuex-router-sync`).

Validation focus:

- install succeeds without peer-dependency conflicts
- build and unit tests can run on the new dependency graph

## 2) Introduce a shared Vue 3 bootstrap/mount layer

Create a small runtime abstraction so all entrypoints migrate consistently:

- `src/lib/vue.js` for `createApp`, global properties, directives, error handler, and render helper
- `src/lib/mountComponent.js` for mount/unmount of dynamic widgets and pickers

This centralizes APIs that replaced `new Vue(...)`, `Vue.prototype`, `Vue.extend(...)`, and `$destroy()`.

Validation focus:

- every entrypoint mounts via shared helpers
- teardown is explicit and idempotent

## 3) Migrate global app configuration from Vue 2 APIs

Replace Vue 2 global setup patterns in entrypoints:

- `Vue.prototype.*` -> `app.config.globalProperties.*`
- global directives registered via `app.directive(...)`
- global error handling registered via `app.config.errorHandler`

Applied to:

- `src/main.js`
- `src/init-collections.js`
- `src/init-dashboard.js`
- `src/init-reference.js`
- `src/init-talk.js`

Validation focus:

- translation helpers (`t`, `n`) and globals (`OC`, `OCA`) are available where previously expected

## 4) Replace Vue 2-only third-party integration points

Introduce local replacements/adapters for removed or incompatible Vue 2 packages:

- custom `clickOutside` directive in `src/directives/clickOutside.js`
- local infinite loader component `src/components/InfiniteLoader.vue`
- local markdown editor wrapper `src/components/card/DeckMarkdownEditor.vue`
- local DnD adapter `src/lib/dnd.js`
- local Nextcloud wrappers:
  - `src/lib/nextcloudVue/components.js`
  - `src/lib/nextcloudVue/reference.js`
- local dialogs helper `src/helpers/dialogs.js`

Validation focus:

- no direct runtime dependency on removed Vue 2-only packages
- behavior parity for loading, markdown, drag-and-drop, dialogs, and reference APIs

## 5) Port directives and lifecycle hook names

Port directive and component lifecycle APIs:

- directive hooks: `bind/inserted/componentUpdated/unbind` -> `mounted/updated/unmounted`
- component hooks: `beforeDestroy/destroyed` -> `beforeUnmount/unmounted`

Key files:

- `src/directives/focus.js`
- `src/directives/clickOutside.js`
- affected components under `src/components/**` and `src/views/**`

Validation focus:

- focus behavior, click-outside closing, and cleanup behavior after unmount

## 6) Migrate router to Vue Router 5 API

Move from Router 3 constructor style to Router 5 factory style:

- `new Router(...)` -> `createRouter(...)`
- `mode/base` -> `createWebHistory(base)`
- route and guard logic extracted into `src/router/config.js`
- router creation centralized in `createDeckRouter()` inside `src/router.js`

Validation focus:

- redirects and legacy URL compatibility
- base URL handling with and without `index.php`
- default-board redirect logic

## 7) Normalize navigation calls for Router 5 behavior

Ensure navigation calls are centralized and compatible with current promise behavior:

- add `src/router/navigation.js`
- route pushes from components should use this helper (or equivalent centralized path)

Validation focus:

- no duplicate-navigation regressions
- no swallowed navigation failures

## 8) Migrate store to Vuex 4 and remove Vue 2 reactivity helpers

Core store migration:

- `new Vuex.Store(...)` -> `createStore(...)`
- remove `Vue.use(Vuex)` patterns
- remove `Vue.set(...)` and `Vue.delete(...)` in favor of direct assignment/object replacement/array splice

Key files:

- `src/store/main.js`
- `src/store/card.js`
- `src/store/comment.js`
- `src/store/attachment.js`
- `src/store/stack.js`
- plus cleanup in `src/store/dashboard.js` and `src/store/overview.js`

Validation focus:

- card/comment/attachment/stack updates stay reactive
- ACL and board settings updates remain correct

## 9) Refactor dynamic widget and selector mounting

Replace Vue 2 dynamic instance patterns in dashboard/reference/selector flows:

- remove `Vue.extend(...)`
- remove `$destroy()` teardown assumptions
- use `mountComponent(...)` lifecycle with explicit `destroy()`

Key files:

- `src/init-dashboard.js`
- `src/init-reference.js`
- `src/helpers/selector.js`
- `src/views/FileSharingPicker.js`

Validation focus:

- dashboard widgets mount/unmount correctly
- reference widgets and custom picker elements clean up correctly

## 10) Remove root-instance event bus usage

Replace removed `$root.$on/$root.$emit/$root.$off` patterns with explicit emits/callbacks/event-emitter approach.

Key files include:

- `src/helpers/selector.js`
- `src/BoardSelector.vue`
- `src/CardSelector.vue`
- `src/components/cards/CardItem.vue`
- `src/components/cards/CardMenu.vue`

Validation focus:

- selection flows, card actions, and close/confirm events still propagate correctly

## 11) Update component contracts and templates

Apply required Vue 3 template/component contract changes:

- replace `.sync` usage with `v-model:*` or explicit update events
- replace/convert legacy functional component patterns
- keep event and prop contracts explicit

Representative files:

- dialogs and controls: `src/CardMoveDialog.vue`, `src/components/Controls.vue`
- app/board sidebars and navigation components under `src/components/**`
- render-function components: `src/components/ActivityEntry.vue`, `src/components/card/CommentItem.vue`

Validation focus:

- two-way bindings in dialogs/settings/filters
- emitted events still trigger parent updates

## 12) Address Nextcloud Vue 9 behavior changes

Apply compatibility fixes that surfaced during migration:

- remove outdated NcModal props no longer supported in `@nextcloud/vue` 9
- keep frontend fallback for `cardDetailsInModal` when config is not yet populated (`?? true` behavior in `src/store/main.js`)

Validation focus:

- card details modal opens reliably in full app and non-full-app contexts

## 13) Migrate entrypoints in this order

Recommended order (lowest to highest blast radius):

1. selectors/pickers (`src/helpers/selector.js`, `src/views/FileSharingPicker.js`)
2. dashboard (`src/init-dashboard.js`)
3. reference widgets (`src/init-reference.js`)
4. main app shell (`src/main.js`)

Run tests and manual checks after each stage before moving on.

## 14) Final verification and cleanup

Mandatory checks before merge:

- `npm run lint`
- `npm test`
- `npm run build`
- manual regression checks for board/card CRUD, sharing, drag-and-drop, dashboard, references, search, and navigation

Cleanup:

- remove any temporary compatibility shims introduced only for transition
- keep adapter files that represent stable integration boundaries

## Non-essential PoC artifacts to avoid in final plan

- treating Vue compat build as a required phase (it was not required here)
- broad speculative phases not backed by concrete code changes
- migration tasks unrelated to Vue runtime/API migration

## Exit criteria

Migration is complete when:

- no production code depends on Vue 2-only APIs (`new Vue`, `Vue.extend`, `Vue.prototype`, `Vue.set/delete`, `$destroy`, root instance events)
- all Deck entrypoints run on Vue 3 with equivalent behavior
- lint, tests, and production build are green