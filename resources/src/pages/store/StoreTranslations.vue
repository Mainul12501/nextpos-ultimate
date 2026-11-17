<template>
  <TranslationsEditorPage
    api-base="admin/store"
    translations-path="/store/translations"
    languages-path="/store/languages"
    :labels="{ title: 'Store_Translations', section: 'Store', languagesTitle: 'Store_Languages' }"
  />
</template>

<script setup>
/** Store → Translations for one storefront language (route param :locale). */
import TranslationsEditorPage from '../../components/languages/TranslationsEditorPage.vue';
</script>
