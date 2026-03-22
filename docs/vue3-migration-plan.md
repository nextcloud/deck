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
- [ ] Confirm the first Vue 3-compatible major versions of `@nextcloud/vue` and `@nextcloud/dialogs` from published releases or maintainers.
- [ ] Decide which packages can be upgraded directly and which need replacement or isolation.
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
- [ ] Move global properties, directives, and plugins to app-level registration.

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

- [ ] Upgrade routing from Vue Router 3 to Vue Router 4.
- [ ] Port [../src/router.js](../src/router.js) to Vue Router 4 APIs.
- [ ] Re-verify navigation guards, redirects, and history base handling.
- [x] Re-evaluate `vuex-router-sync` usage. No replacement is required because Deck uses `$route` directly instead of synced store route state.

### 2.2 Store

- [ ] Upgrade from Vuex 3 to Vuex 4 unless a deliberate Pinia migration is approved separately.
- [ ] Replace `Vue.use(Vuex)` in [../src/store/main.js](../src/store/main.js), [../src/store/dashboard.js](../src/store/dashboard.js), and [../src/store/overview.js](../src/store/overview.js).
- [ ] Remove `Vue.set(...)` and `Vue.delete(...)` usage across store modules.
- [ ] Re-test reactivity-sensitive flows for board, stack, card, comment, and attachment updates.

### Exit criteria

- [ ] Router and store boot successfully on Vue 3-compatible APIs.
- [ ] Store mutations no longer rely on removed Vue 2 reactivity helpers.

## Phase 3: Component and template compatibility cleanup

### 3.1 Template syntax

- [x] Replace `.sync` patterns with `v-model:prop` or explicit `update:prop` events.
- [ ] Verify component contracts for all Nextcloud Vue components that currently use `.sync`.
- [ ] Re-test dialogs, board controls, sidebars, settings, and clone/export flows.

### 3.2 Functional components and render helpers

- [ ] Replace `functional: true` components in [../src/components/ActivityEntry.vue](../src/components/ActivityEntry.vue) and [../src/components/card/CommentItem.vue](../src/components/card/CommentItem.vue).
- [ ] Review render functions and slot usage for Vue 3 compatibility.

### 3.3 Lifecycle hooks and directives

- [ ] Rename component lifecycle hooks such as `beforeDestroy` and `destroyed`.
- [ ] Port directive hooks in [../src/directives/focus.js](../src/directives/focus.js) to Vue 3 hook names and instance access patterns.
- [ ] Re-test focus handling, keyboard shortcuts, and editor teardown behavior.

### Exit criteria

- [ ] Templates, directives, and lifecycle hooks are free of Vue 2-only syntax.

## Phase 4: Entrypoint-by-entrypoint migration

### 4.1 Small standalone pickers and helpers

- [ ] Migrate [../src/helpers/selector.js](../src/helpers/selector.js).
- [ ] Migrate [../src/views/FileSharingPicker.js](../src/views/FileSharingPicker.js).
- [ ] Verify collaboration board and card selector flows.

### 4.2 Dashboard widgets

- [ ] Migrate [../src/init-dashboard.js](../src/init-dashboard.js).
- [ ] Verify upcoming, today, and tomorrow dashboard widgets.

### 4.3 Reference widgets

- [ ] Migrate [../src/init-reference.js](../src/init-reference.js).
- [ ] Verify board, card, comment, and custom picker render lifecycles.

### 4.4 Main app shell

- [ ] Migrate [../src/main.js](../src/main.js).
- [ ] Verify board loading, navigation, sidebar behavior, modal behavior, and unified search integration.

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
- [ ] Confirm whether `@nextcloud/vue` and related helpers can be upgraded directly in the same branch.
- [ ] Confirm whether `vuex-router-sync` remains viable or should be removed.
- [ ] Confirm replacement strategy for any Vue 2-only third-party packages.

## Working notes

- Prefer small PRs grouped by subsystem rather than one long-lived migration branch.
- If migration build is used, treat it as instrumentation and not as a shipping target.
- Keep this file updated when phases are split, reordered, or blocked.