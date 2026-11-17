<template>
  <LanguagePacksPage
    api-base="admin/store"
    translations-path="/store/translations"
    :labels="{
      title: 'Store_Languages',
      section: 'Store',
      visit: 'Visit_Online_Store',
      activeOn: 'StoreLang_Active_On_Store',
      showOn: 'StoreLang_Show_On_Store',
      availableHint: 'StoreLang_Available_Hint',
      installed: 'StoreLang_Installed_Success',
      deleteConfirm: 'StoreLang_Delete_Confirm',
    }"
  />
</template>

<script setup>
/** Store → Languages (storefront i18n, store_languages / store_translations). */
import LanguagePacksPage from '../../components/languages/LanguagePacksPage.vue';
</script>
