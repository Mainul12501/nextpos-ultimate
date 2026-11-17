<template>
  <LanguagePacksPage
    api-base="admin/system"
    translations-path="/settings/translations"
    allow-custom
    :labels="{
      title: 'Languages',
      section: 'Settings',
      visit: 'Languages',
      activeOn: 'AdminLang_Active_In_App',
      showOn: 'AdminLang_Show_In_App',
      availableHint: 'AdminLang_Available_Hint',
      installed: 'AdminLang_Installed_Success',
      deleteConfirm: 'AdminLang_Delete_Confirm',
    }"
  />
</template>

<script setup>
/**
 * Settings → Languages (back office). Same page as Store / Portal Languages
 * (shared LanguagePacksPage) on the shared admin API under admin/system,
 * plus custom languages with an uploaded flag (allow-custom). The topbar
 * language menu re-reads GET /languages each time it opens, so installs and
 * toggles here show up there without a reload.
 */
import LanguagePacksPage from '../../components/languages/LanguagePacksPage.vue';
</script>
