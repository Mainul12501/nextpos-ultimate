<template>
  <div class="page">
    <a-result
      status="404"
      :title="$t('Gen_Page_Not_Available')"
      :sub-title="$t('Gen_Page_Not_Available_Desc')"
    >
      <template #extra>
        <a-button type="primary" @click="$router.push('/dashboard')">{{ $t('Gen_Go_To_Dashboard') }}</a-button>
      </template>
    </a-result>
  </div>
</template>
