<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Vue 3 Dependency Audit

Use this file to track package-level migration status before changing the Vue runtime.

Status values:

- `pending`: not checked yet
- `upgrade`: compatible upgrade path exists
- `replace`: package should be replaced
- `remove`: package can be removed entirely
- `blocked`: migration currently blocked on this package

## Core framework packages

| Package | Current usage | Status | Action | Notes |
| --- | --- | --- | --- | --- |
| `vue` | runtime | blocked | target `3.5.x` and remove compat-only APIs | Current lockfile is `2.7.16`; move to the stable Vue 3.5 line once the Nextcloud UI layer is ready. |
| `vue-loader` | SFC build | upgrade | keep `17.4.2+` and pair it with the Vue 3 compiler stack | Current `17.4.2` is already on the modern loader line and does not itself block the migration. |
| `vue-router` | main app router | blocked | target `4.3.x` or newer stable `4.4.x` | Current `3.6.5` is the Vue 2 router line. |
| `vuex` | main, overview, dashboard stores | blocked | target `4.1.x` | Current `3.6.2` has a Vue 2 peer dependency. Keep the store migration scoped and avoid combining it with Pinia. |
| `vuex-router-sync` | router-state sync | replace | remove dependency and sync route state manually where needed | Current `5.0.0` peers on Router 3 and Vuex 3 only, and the repo uses it only in [../src/main.js](../src/main.js). |
| `@vue/test-utils` | component tests | upgrade | keep `2.4.6+` and verify final test setup after runtime switch | Current `2.4.6` is already the Vue 3 test-utils generation. No frontend tests currently reference it. |
| `@vue/vue2-jest` | Vue test transform | replace | switch to a Vue 3-compatible Jest transformer | Current `29.2.6` peers on `vue ^2.x` and `vue-template-compiler ^2.x`. |
| `vue-template-compiler` | Vue 2 SFC compiler | remove | replace with the Vue 3 compiler package | Current `2.7.16` is Vue 2-only. |

## Nextcloud integration packages

| Package | Current usage | Status | Action | Notes |
| --- | --- | --- | --- | --- |
| `@nextcloud/vue` | shared UI components, composables, reference helpers, deep imports | blocked | upgrade to the first Vue 3-compatible major published by Nextcloud, likely `9.x` or `10.x` | Current `8.35.0` depends on `vue ^2.7.16`, `vue-router ^3.6.5`, `@nextcloud/vue-select` with `vue 2.x`, `vue2-datepicker`, and other Vue 2 packages. This is the main ecosystem blocker and the exact target major is still unconfirmed. |
| `@nextcloud/dialogs` | toasts, undo, loading, file picker helpers | blocked | upgrade in lockstep with the first Vue 3-compatible `@nextcloud/vue` major, likely `7.x` or `8.x` | Current `6.4.2` peers on `@nextcloud/vue ^8.24.0` and `vue ^2.7.16`. The exact target major is still unconfirmed. |
| `@nextcloud/event-bus` | app event integration | upgrade | keep `3.3.x+` unless a newer Nextcloud bundle version is required by upgraded UI packages | Current `3.3.3` has no Vue peer dependency and should not block the runtime switch by itself. |
| `@nextcloud/webpack-vue-config` | webpack baseline | upgrade | keep `6.3.0+` and align it with the final Vue version and compiler stack | Current `6.3.0` explicitly peers on `vue ^2.7.16 || ^3.5.13` and `vue-loader ^15 || ^17`, so the config layer is already migration-friendly. |

## Vue-adjacent UI and behavior packages

| Package | Current usage | Status | Action | Notes |
| --- | --- | --- | --- | --- |
| `vue-click-outside` | click-outside directive | replace | replace with a local Vue 3 directive | The repo uses it broadly as a directive in [../src/main.js](../src/main.js), [../src/init-reference.js](../src/init-reference.js), and multiple components. Replacing it locally is simpler than carrying a legacy plugin. |
| `vue-easymde` | markdown editor wrapper | replace | preferred: wrap `easymde` directly in a local Vue 3 component; fallback: evaluate `@nextcloud/text` if it can fully cover the current editor flow | The repo imports `vue-easymde/dist/VueEasyMDE.common.js` and reaches into wrapper internals via refs in [../src/components/card/Description.vue](../src/components/card/Description.vue), which makes this a high-risk migration point. |
| `vue-infinite-loading` | activity, search, comments infinite lists | replace | preferred: local `IntersectionObserver` wrapper using `@vueuse/core`; fallback: a Vue 3 virtual-scroll library for longer lists | Current `2.4.5` peers on `vue ^2.6.10`. Used in [../src/components/ActivityList.vue](../src/components/ActivityList.vue), [../src/components/search/GlobalSearchResults.vue](../src/components/search/GlobalSearchResults.vue), and [../src/components/card/CardSidebarTabComments.vue](../src/components/card/CardSidebarTabComments.vue). |
| `vue-smooth-dnd` | board and stack drag and drop | replace | preferred: `@dnd-kit/vue`; fallback: `sortablejs` with a thin local Vue 3 wrapper | Central interaction dependency used in [../src/components/board/Board.vue](../src/components/board/Board.vue) and [../src/components/board/Stack.vue](../src/components/board/Stack.vue). Keeping it would add avoidable migration risk. |
| `vue-at` | mentions | remove | remove dependency unless a hidden integration is discovered outside `src/` | No usage was found in `src/`; the current mention UI appears to come from Nextcloud components instead. |
| `vue-material-design-icons` | icon components | upgrade | keep `5.3.1+` and verify import format after runtime switch | Used as SFC icon components, for example in [../src/components/card/CommentItem.vue](../src/components/card/CommentItem.vue). This is lower risk than the interactive wrappers above. |

## Concrete target summary

### Upgrade targets

| Package | Recommended target |
| --- | --- |
| `vue` | `3.5.x` |
| `vue-loader` | `17.4.2+` |
| `vue-router` | `4.3.x` or newer stable `4.4.x` |
| `vuex` | `4.1.x` |
| `@vue/test-utils` | `2.4.6+` |
| `@nextcloud/event-bus` | `3.3.x+` |
| `@nextcloud/webpack-vue-config` | `6.3.0+` |
| `vue-material-design-icons` | `5.3.1+` |

### Replacement targets

| Current package | Recommended replacement |
| --- | --- |
| `vue-click-outside` | local `v-click-outside` directive in `src/directives/` |
| `vue-easymde` | local Vue 3 wrapper around `easymde` |
| `vue-infinite-loading` | local observer-based pagination wrapper built on `@vueuse/core` |
| `vue-smooth-dnd` | `@dnd-kit/vue` |
| `vuex-router-sync` | remove package and sync route state manually |
| `@vue/vue2-jest` | `@vue/vue3-jest` |
| `vue-template-compiler` | `@vue/compiler-sfc` |

### Fallback replacement options

| Current package | Fallback option |
| --- | --- |
| `vue-easymde` | evaluate `@nextcloud/text` if it covers the full editing flow |
| `vue-infinite-loading` | Vue 3 virtual-scroll library for long lists |
| `vue-smooth-dnd` | `sortablejs` with a thin local wrapper |

## Isolation-first wrapper candidates

These are the packages that should be hidden behind local abstractions before the runtime switch. The goal is to reduce the number of direct call sites that need to change when Vue 3 work begins.

### Priority 1: Small, high-spread wrappers

| Package | Why isolate first | Current footprint | Suggested local seam |
| --- | --- | --- | --- |
| `vue-click-outside` | Small behavior surface, many call sites, easy to replace without behavior changes | Used in [../src/main.js](../src/main.js), [../src/components/board/Stack.vue](../src/components/board/Stack.vue), [../src/components/navigation/AppNavigation.vue](../src/components/navigation/AppNavigation.vue), [../src/components/navigation/AppNavigationBoard.vue](../src/components/navigation/AppNavigationBoard.vue), [../src/components/cards/CardItem.vue](../src/components/cards/CardItem.vue), and other templates | local `src/directives/clickOutside.js` plus centralized registration |
| `vuex-router-sync` | Single import today, but it couples the app shell to Router 3 and Vuex 3 | Only used in [../src/main.js](../src/main.js) | local route-to-store sync helper in `src/router/` or `src/store/` |
| `@nextcloud/dialogs` | Broad usage, but much of it is already thin helper-style code | Used directly in board, card, navigation, and upload flows; partially wrapped already by [../src/helpers/errors.js](../src/helpers/errors.js) | extend local helpers such as `src/helpers/errors.js` and add a `src/helpers/dialogs.js` facade |

### Priority 2: Medium-scope component wrappers

| Package | Why isolate next | Current footprint | Suggested local seam |
| --- | --- | --- | --- |
| `vue-infinite-loading` | Only three components depend on it, and the contract is simple: load-more on sentinel reach | Used in [../src/components/ActivityList.vue](../src/components/ActivityList.vue), [../src/components/search/GlobalSearchResults.vue](../src/components/search/GlobalSearchResults.vue), and [../src/components/card/CardSidebarTabComments.vue](../src/components/card/CardSidebarTabComments.vue) | local `InfiniteLoader` component backed by `IntersectionObserver` |
| `@nextcloud/vue/dist/...` deep imports | High break risk during package upgrades because they rely on internals | Seen in [../src/init-reference.js](../src/init-reference.js), [../src/views/BoardReferenceWidget.vue](../src/views/BoardReferenceWidget.vue), and [../src/components/card/CardSidebar.vue](../src/components/card/CardSidebar.vue) | local wrapper exports under `src/lib/nextcloud-vue.js` or feature-local adapters |

### Priority 3: Complex interactive wrappers

| Package | Why isolate later | Current footprint | Suggested local seam |
| --- | --- | --- | --- |
| `vue-easymde` | Single main usage, but deep custom behavior and editor-internal access | Concentrated in [../src/components/card/Description.vue](../src/components/card/Description.vue) | local `DeckMarkdownEditor` component |
| `vue-smooth-dnd` | Only two files import it, but the interaction model and CSS coupling are deep | Used in [../src/components/board/Board.vue](../src/components/board/Board.vue) and [../src/components/board/Stack.vue](../src/components/board/Stack.vue) | local `BoardDnDContainer` and `CardDnDContainer` wrappers or a thin `src/lib/dnd/` adapter |

### Isolation order recommendation

1. Introduce a local click-outside directive.
2. Replace `vuex-router-sync` with a local route sync helper.
3. Funnel all direct dialog calls through local helper modules.
4. Add a local infinite-loader component.
5. Remove deep `@nextcloud/vue/dist/...` imports behind local adapters.
6. Wrap the Markdown editor.
7. Replace drag-and-drop behind local DnD adapters.

## Repo-specific findings

- Current Nextcloud UI packages in the lockfile are still on Vue 2. `@nextcloud/vue` and `@nextcloud/dialogs` are both hard blockers for a direct runtime switch.
- The webpack baseline is not the blocker. `@nextcloud/webpack-vue-config` already advertises support for both Vue 2 and Vue 3 peer ranges.
- Deep imports from `@nextcloud/vue/dist/...` increase migration risk because they couple Deck to package internals. Current examples include [../src/init-reference.js](../src/init-reference.js), [../src/views/BoardReferenceWidget.vue](../src/views/BoardReferenceWidget.vue), and [../src/components/card/CardSidebar.vue](../src/components/card/CardSidebar.vue).
- Several third-party packages are better replaced than upgraded because Deck uses them as thin adapters: click-outside, infinite loading, drag and drop, and the Markdown editor wrapper.
- No frontend tests currently reference `@vue/test-utils`, so test tooling work will mostly be setup work rather than fixture migration.
- The exact Vue 3-compatible major versions of `@nextcloud/vue` and `@nextcloud/dialogs` still need confirmation from published Nextcloud releases or maintainers. Until that is confirmed, the rest of the package targets should be treated as provisional.

## Audit checklist

- [x] Check each package for Vue 3 support, peer dependencies, and maintenance status.
- [x] Record the exact target version for every package marked `upgrade`.
- [x] Record the replacement candidate for every package marked `replace`.
- [x] Identify packages that can be isolated behind local wrappers before the runtime switch.
- [ ] Confirm test tooling changes required for the final package set.

### Current blockers identified on 2026-03-21

- [x] `vue` is still pinned to the Vue 2 line.
- [x] `vue-router`, `vuex`, and `vuex-router-sync` are still pinned to the Vue 2 ecosystem.
- [x] `@nextcloud/vue` current major is Vue 2-based.
- [x] `@nextcloud/dialogs` current major peers on Vue 2.
- [x] `vue-infinite-loading` is Vue 2-only.
- [x] `vue-at` appears unused and can be removed.
- [x] Exact target replacement libraries have been chosen for the packages currently marked `replace`.

## Suggested verification commands

Run these commands while updating this file:

```bash
npm view <package-name> peerDependencies dependencies version --json
npm view <package-name> dist-tags --json
npm info <package-name> repository homepage
```

## Decision log

| Package | Decision | Date | Reason |
| --- | --- | --- | --- |
| `@nextcloud/vue` | blocked on current major | 2026-03-21 | Lockfile shows `8.35.0` still depends on Vue 2, Vue Router 3, and multiple Vue 2-only transitive packages. The likely target is a future `9.x` or `10.x` major, but that is still unconfirmed. |
| `@nextcloud/dialogs` | blocked on current major | 2026-03-21 | Lockfile shows `6.4.2` peers on Vue 2 and `@nextcloud/vue` 8.x. The likely target is a future `7.x` or `8.x` major, but that is still unconfirmed. |
| `@nextcloud/webpack-vue-config` | upgrade path available | 2026-03-21 | Lockfile shows `6.3.0` already advertises Vue 3 peer compatibility. |
| `vuex-router-sync` | replace | 2026-03-21 | The package is tightly coupled to Router 3 and Vuex 3, and repo usage is isolated to the main app bootstrap. |
| `vue-at` | remove | 2026-03-21 | No imports were found in `src/`, so it is currently dead weight for the migration. |
| `vue-easymde` | replace | 2026-03-21 | Deck reaches into wrapper internals in [../src/components/card/Description.vue](../src/components/card/Description.vue), so a local wrapper around `easymde` is safer than looking for a drop-in Vue 3 port. |
| `vue-smooth-dnd` | replace | 2026-03-21 | `@dnd-kit/vue` is the preferred target because it is Vue 3-native and modern; `sortablejs` remains the fallback if the interaction model fits better. |
| `vue-click-outside` | isolate first | 2026-03-21 | It has a small API surface and many call sites, so replacing it early cuts migration noise across the app. |
| `@nextcloud/dialogs` | isolate first | 2026-03-21 | Direct calls are spread across the app, but they already behave like helper functions and can be centralized behind local wrappers. |

## Implemented isolation seams

- [x] `vue-click-outside` has been replaced in source with a local directive at [../src/directives/clickOutside.js](../src/directives/clickOutside.js).
- [x] `vuex-router-sync` source usage has been removed from [../src/main.js](../src/main.js). No local replacement is needed because Deck uses `$route` directly.
- [x] `@nextcloud/dialogs` source usage has been consolidated behind [../src/helpers/dialogs.js](../src/helpers/dialogs.js) and [../src/helpers/errors.js](../src/helpers/errors.js).
- [x] Deep `@nextcloud/vue/dist/...` imports have been isolated behind [../src/lib/nextcloudVue/components.js](../src/lib/nextcloudVue/components.js) and [../src/lib/nextcloudVue/reference.js](../src/lib/nextcloudVue/reference.js).
- [x] `vue-infinite-loading` source usage has been replaced by [../src/components/InfiniteLoader.vue](../src/components/InfiniteLoader.vue).
- [x] `vue-easymde` source usage has been isolated behind [../src/components/card/DeckMarkdownEditor.vue](../src/components/card/DeckMarkdownEditor.vue).

## Blocking conditions

- [ ] No package remains in `blocked` state without an explicit mitigation plan.
- [x] The migration-build decision should be revisited only after the Nextcloud package upgrade path is known.