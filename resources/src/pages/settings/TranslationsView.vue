<template>
  <TranslationsEditorPage
    api-base="admin/system"
    translations-path="/settings/translations"
    languages-path="/settings/languages"
    :labels="{ title: 'Translations', section: 'Settings', languagesTitle: 'Languages' }"
    @saved="onSaved"
  />
</template>

<script setup>
/**
 * Settings → Translations for one back-office language (route param :locale).
 * Shared TranslationsEditorPage on admin/system; every save is applied to the
 * running app straight away (no "reload to apply" step any more).
 */
import TranslationsEditorPage from '../../components/languages/TranslationsEditorPage.vue';
import { applyTranslationEdit } from '../../i18n';

function onSaved({ locale, key, value }) {
  applyTranslationEdit(locale, key, value);
}
</script>
