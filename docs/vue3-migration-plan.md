<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Vue 3 Migration Implementation Plan

This document is the working implementation plan for migrating Deck from Vue 2.7 to Vue 3.

Use this file as the source of truth for sequencing, progress tracking, and exit criteria.

## Migration principles

- [ ] Keep the initial migration behavior-compatible. Avoid feature work in the same branch.
- [ ] Keep the Options API during the migration unless a file already needs a deeper rewrite.
- [ ] Migrate one workstream at a time and keep each step independently reviewable.
- [ ] Use the Vue 3 migration build only as a temporary compatibility aid, not as the end state.
- [ ] Remove Vue 2-only patterns before or during the runtime switch instead of layering workarounds on top.

## Target outcomes

- [ ] Run the main app on Vue 3.
- [ ] Run dashboard, reference widgets, and collaboration pickers on Vue 3.
- [ ] Replace Vue 2 instance APIs and deprecated template patterns.
- [ ] Keep the existing route structure and user-visible behavior intact.
- [ ] Restore green linting, tests, and production build output.

## Phase 0: Preparation and baseline

- [ ] Create a dedicated migration branch.
- [ ] Record the current baseline build and test status.
- [ ] Confirm the supported Nextcloud and Node.js versions for the migration target.
- [x] Audit Vue-related dependencies in [vue3-dependency-audit.md](vue3-dependency-audit.md).
- [x] Confirm the first Vue 3-compatible major versions of `@nextcloud/vue` and `@nextcloud/dialogs` from published releases or maintainers.
- [x] Decide which packages can be upgraded directly and which need replacement or isolation.
- [x] Record the target versions and replacement candidates from [vue3-dependency-audit.md](vue3-dependency-audit.md).
- [x] Identify the first local wrapper seams from [vue3-dependency-audit.md](vue3-dependency-audit.md).
- [ ] Decide whether a temporary migration-build branch is needed after the Nextcloud package upgrade path is confirmed.

### Exit criteria

- [ ] All Vue-facing dependencies are categorized as `upgrade`, `replace`, `remove`, or `verify later`.
- [ ] Known blockers are listed before touching the runtime.
- [ ] The Nextcloud UI package upgrade path is confirmed, or an explicit mitigation plan exists for waiting on it.

## Phase 1: Remove Vue 2-only application patterns

### 1.1 Bootstrapping and app globals

- [x] Replace `new Vue(...)` mounting in [../src/main.js](../src/main.js) with a shared root-mount helper.
- [x] Replace `Vue.prototype` usage in [../src/main.js](../src/main.js), [../src/init-collections.js](../src/init-collections.js), [../src/init-dashboard.js](../src/init-dashboard.js), [../src/init-reference.js](../src/init-reference.js), and [../src/init-talk.js](../src/init-talk.js) with shared bootstrap configuration.
- [x] Introduce shared Vue 3 mount helpers for standalone entrypoints.
- [x] Normalize entrypoint Vue imports so both Vue 2 default exports and Vue 3 module-namespace imports work through the shared helpers.
- [x] Move global properties, directives, and plugins to app-level registration.

### 1.1a Build isolation seams first

- [x] Introduce a local `clickOutside` directive and replace direct `vue-click-outside` imports.
- [x] Remove `vuex-router-sync` source usage. No local sync helper is needed because Deck does not read synced route state from the store.
- [x] Funnel direct `@nextcloud/dialogs` calls through local helper modules.
- [x] Replace deep `@nextcloud/vue/dist/...` imports with local adapters.
- [x] Introduce a local infinite-loader component before the Vue 3 runtime switch.
- [x] Wrap the legacy Markdown editor behind a local `DeckMarkdownEditor` component.
- [x] Route board and stack drag-and-drop through a local DnD adapter.

### 1.2 Manual widget lifecycle management

- [x] Replace `Vue.extend(...)` usage in [../src/init-dashboard.js](../src/init-dashboard.js) and [../src/init-reference.js](../src/init-reference.js).
- [x] Replace `$destroy()`-based teardown in [../src/helpers/selector.js](../src/helpers/selector.js), [../src/views/FileSharingPicker.js](../src/views/FileSharingPicker.js), and [../src/init-reference.js](../src/init-reference.js).
- [x] Standardize mount and unmount behavior for widgets, selectors, and custom picker elements via [../src/lib/mountComponent.js](../src/lib/mountComponent.js).

### 1.3 Event flow cleanup

- [x] Remove `$root.$on(...)` and `$root.$emit(...)` patterns in [../src/helpers/selector.js](../src/helpers/selector.js), [../src/BoardSelector.vue](../src/BoardSelector.vue), [../src/CardSelector.vue](../src/CardSelector.vue), [../src/components/board/Board.vue](../src/components/board/Board.vue), [../src/components/cards/CardItem.vue](../src/components/cards/CardItem.vue), and [../src/components/cards/CardMenuEntries.vue](../src/components/cards/CardMenuEntries.vue).
- [x] Replace root-instance messaging with explicit emits, callback props, or a dedicated external emitter.
- [x] Document the chosen event pattern and use it consistently across entrypoints.

### Exit criteria

- [ ] No application code depends on `new Vue`, `Vue.extend`, `$destroy`, or root-instance event APIs.

## Phase 2: Framework and store migration

### 2.1 Router

- [x] Upgrade routing from Vue Router 3 to the Vue 3-compatible router line used by `@nextcloud/vue 9.x`.
- [x] Isolate router base URL logic, route records, and global guards behind reusable helpers in [../src/router/config.js](../src/router/config.js).
- [x] Centralize router creation options behind [../src/router/config.js](../src/router/config.js) so only the concrete router constructor API remains in [../src/router.js](../src/router.js).
- [x] Normalize redirect decisions behind a reusable guard helper in [../src/router/config.js](../src/router/config.js) so the Vue 3 router can consume return-based guard results.
- [x] Isolate duplicate-navigation suppression behind [../src/router/navigation.js](../src/router/navigation.js) instead of inline `router.push(...).catch(...)` calls.
- [x] Normalize component navigation calls behind [../src/router/navigation.js](../src/router/navigation.js) so route transitions do not depend on Router 3-specific promise behavior.
- [x] Convert [../src/router.js](../src/router.js) to a `createDeckRouter()` factory so the final Vue 3 router runtime swap is localized.
- [x] Isolate Router 3 vs Router 4/5 plugin and constructor differences behind [../src/router/runtime.js](../src/router/runtime.js).
- [x] Port [../src/router.js](../src/router.js) to the Vue 3 router APIs.
- [x] Re-verify navigation guards, redirects, and history base handling.
- [x] Re-evaluate `vuex-router-sync` usage. No replacement is required because Deck uses `$route` directly instead of synced store route state.

### 2.2 Store

- [x] Upgrade from Vuex 3 to Vuex 4 unless a deliberate Pinia migration is approved separately.
- [x] Replace module-level `Vue.use(Vuex)` calls in [../src/store/main.js](../src/store/main.js), [../src/store/dashboard.js](../src/store/dashboard.js), and [../src/store/overview.js](../src/store/overview.js) with the shared helpers in [../src/lib/vuex.js](../src/lib/vuex.js).
- [x] Centralize store construction behind [../src/lib/vuex.js](../src/lib/vuex.js) so the Vuex 4 constructor swap is localized.
- [x] Isolate Vuex 3 vs Vuex 4 plugin/constructor differences behind [../src/lib/vuex.js](../src/lib/vuex.js).
- [x] Replace the safe array-index and existing-property `Vue.set(...)` / `Vue.delete(...)` usage in [../src/store/main.js](../src/store/main.js), [../src/store/stack.js](../src/store/stack.js), [../src/store/comment.js](../src/store/comment.js), [../src/store/card.js](../src/store/card.js), and [../src/store/attachment.js](../src/store/attachment.js).
- [x] Replace the remaining dynamic-key `Vue.set(...)` usage in [../src/store/comment.js](../src/store/comment.js), [../src/store/attachment.js](../src/store/attachment.js), and [../src/store/card.js](../src/store/card.js) with object/item replacement that is compatible with Vue 3 reactivity.
- [x] Re-test reactivity-sensitive flows for board, stack, card, comment, and attachment updates.

### Exit criteria

- [ ] Router and store boot successfully on Vue 3-compatible APIs.
- [ ] Store mutations no longer rely on removed Vue 2 reactivity helpers.

## Phase 3: Component and template compatibility cleanup

### 3.1 Template syntax

- [x] Replace `.sync` patterns with `v-model:prop` or explicit `update:prop` events.
- [x] Verify component contracts for all Nextcloud Vue components that currently use `.sync`.
- [x] Re-test dialogs, board controls, sidebars, settings, and clone/export flows.

### 3.2 Functional components and render helpers

- [x] Replace `functional: true` components in [../src/components/ActivityEntry.vue](../src/components/ActivityEntry.vue) and [../src/components/card/CommentItem.vue](../src/components/card/CommentItem.vue).
- [x] Review render functions and slot usage for Vue 3 compatibility.

### 3.3 Lifecycle hooks and directives

- [x] Rename component lifecycle hooks such as `beforeDestroy` and `destroyed`.
- [x] Port directive hooks in [../src/directives/focus.js](../src/directives/focus.js) to Vue 3 hook names and instance access patterns.
- [x] Port directive hooks in [../src/directives/clickOutside.js](../src/directives/clickOutside.js) to Vue 3 hook names (`mounted`, `updated`, `unmounted`).
- [x] Remove deprecated `@nextcloud/vue` 8.x NcModal props (`close-button-contained`, `clear-view-delay`) from [../src/App.vue](../src/App.vue) and [../src/components/board/Board.vue](../src/components/board/Board.vue).
- [x] Add frontend default for `cardDetailsInModal` config getter (`?? true`) to match the backend default and prevent modal regression when initial state is unavailable.
- [x] Re-test focus handling, keyboard shortcuts, and editor teardown behavior.

### Exit criteria

- [ ] Templates, directives, and lifecycle hooks are free of Vue 2-only syntax.

## Phase 4: Entrypoint-by-entrypoint migration

### 4.1 Small standalone pickers and helpers

- [x] Migrate [../src/helpers/selector.js](../src/helpers/selector.js).
- [x] Migrate [../src/views/FileSharingPicker.js](../src/views/FileSharingPicker.js).
- [x] Verify collaboration board and card selector flows.

### 4.2 Dashboard widgets

- [x] Migrate [../src/init-dashboard.js](../src/init-dashboard.js).
- [x] Verify upcoming, today, and tomorrow dashboard widgets.

### 4.3 Reference widgets

- [x] Migrate [../src/init-reference.js](../src/init-reference.js).
- [x] Verify board, card, comment, and custom picker render lifecycles.

### 4.4 Main app shell

- [x] Migrate [../src/main.js](../src/main.js).
- [x] Verify board loading, navigation, sidebar behavior, modal behavior, and unified search integration.

### Exit criteria

- [ ] Every published Deck frontend entrypoint runs on the Vue 3 stack.

## Phase 5: Migration-build branch, if needed

Use this phase only if the dependency audit shows that temporary compat mode will accelerate warning discovery without locking the project into a long-lived compatibility layer.

- [ ] Create a short-lived branch or draft PR dedicated to migration-build warnings.
- [ ] Enable compat warnings globally.
- [ ] Record each warning category and map it to a code fix.
- [ ] Fix warnings by subsystem rather than silencing them.
- [ ] Remove compat mode before merge.

### Exit criteria

- [ ] No production code depends on the migration build.
- [ ] The final branch uses the standard Vue 3 runtime only.

## Phase 6: Tooling, tests, and verification

- [ ] Update build tooling, compiler packages, and test transformers for Vue 3.
- [ ] Ensure `npm run build` succeeds.
- [ ] Ensure `npm run lint` succeeds.
- [ ] Re-run lint after the `package.json` script update in [../package.json](../package.json); command execution is currently blocked by the editor terminal/task integration in this session.
- [ ] Ensure `npm test` succeeds.
- [ ] Run the key user flows manually in a Nextcloud instance.
- [ ] Validate dashboard, sharing picker, collaboration picker, and reference widgets.

## Release checklist

- [ ] Remove temporary shims and compatibility helpers that were only needed during migration.
- [ ] Update contributor documentation if commands or tooling changed.
- [ ] Add a release note entry describing the migration impact and any known limitations.
- [ ] Confirm built assets are reproducible and committed according to project policy.

## Open decisions

- [ ] Confirm the supported Vue 3 version range for the surrounding Nextcloud frontend stack.
- [x] Confirm whether `@nextcloud/vue` and related helpers can be upgraded directly in the same branch.
- [x] Confirm whether `vuex-router-sync` remains viable or should be removed.
- [x] Confirm replacement strategy for any Vue 2-only third-party packages.

## Working notes

- Prefer small PRs grouped by subsystem rather than one long-lived migration branch.
- If migration build is used, treat it as instrumentation and not as a shipping target.
- Keep this file updated when phases are split, reordered, or blocked.
- The current target stack is `vue 3.5.x`, `@vue/compiler-sfc 3.5.x`, `vue-loader 17.4.2+`, `vuex 4.1.x`, `@nextcloud/vue 9.x`, `@nextcloud/dialogs 7.x`, and the Vue 3-compatible router line used by `@nextcloud/vue 9.x`.