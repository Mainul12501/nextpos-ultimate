<template>
  <div class="page">
    <PageHeader
      :title="$t('Woo_Guide_Page_Title')"
      :subtitle="$t('Woo_Guide_Page_Subtitle')"
      :breadcrumb="[$t('Settings'), 'WooCommerce', $t('Guide')]"
    >
      <template #actions>
        <a-button type="primary" @click="$router.push('/woocommerce')">
          <template #icon><SettingOutlined /></template>
          {{ $t('Woo_Settings_Button') }}
        </a-button>
      </template>
    </PageHeader>

    <a-card size="small">
      <a-spin :spinning="loading">
        <a-alert
          v-if="!loading && !connected"
          type="info" show-icon style="margin-bottom: 16px"
          :message="$t('Woo_No_Shop_Connected')"
          :description="$t('Woo_No_Shop_Connected_Desc')"
        />
        <WooCommerceGuide :settings="settings" :cron="cron" @open-settings="$router.push('/woocommerce')" />
      </a-spin>
    </a-card>
  </div>
</template>

<script setup>
/**
 * The WooCommerce guide as a page of its own, for the sidebar. Renders the
 * same component as the hub's Guide tab — one copy of the content, two ways in.
 */
import { ref, computed, onMounted } from 'vue';
import { SettingOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../../components/PageHeader.vue';
import WooCommerceGuide from './WooCommerceGuide.vue';
import http from '../../../lib/http';

const loading = ref(false);
const settings = ref(null);
const cron = ref({});

const connected = computed(() => !!(settings.value?.store_url && settings.value?.consumer_key));

onMounted(async () => {
  loading.value = true;
  try {
    const data = await http.get('woocommerce/settings');
    settings.value = data.settings || null;
    cron.value = data.cron || {};
  } catch (e) {
    // First run, or no permission to read settings — the generic guide still
    // reads correctly without them.
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.page {
  padding-bottom: 20px;
}
</style>
