<template>
  <div class="page">
    <PageHeader :title="$t('WooCommerce_Settings')" :breadcrumb="[$t('Settings'), $t('WooCommerce_Settings')]">
      <template #extra>
        <a-tag :color="connectionOk === true ? 'success' : connectionOk === false ? 'error' : 'default'">
          {{ connectionOk === true ? $t('Connected') : connectionOk === false ? $t('Disconnected') : $t('Unknown') }}
        </a-tag>
      </template>
    </PageHeader>

    <a-card>
      <a-tabs v-model:activeKey="activeTab">
        <a-tab-pane key="status" :tab="$t('Status')">
          <StatusOverviewTab
            :total-products="totalProducts" :unsynced-count="unsyncedCount" :counts-loading="countsLoading"
            @ready="noop"
          />
        </a-tab-pane>
        <a-tab-pane key="settings" :tab="$t('Settings')">
          <SettingsTab
            @ready="noop" @connection="v => (connectionOk = v)" @updated="onChildRefreshed"
            @open-guide="activeTab = 'guide'"
          />
        </a-tab-pane>
        <a-tab-pane key="products" :tab="$t('Products')">
          <ProductsTab @ready="noop" @refreshed="onChildRefreshed" />
        </a-tab-pane>
        <a-tab-pane key="stock" :tab="$t('Stock')">
          <StockTab @ready="noop" @refreshed="onChildRefreshed" @view-logs="activeTab = 'logs'" />
        </a-tab-pane>
        <a-tab-pane key="categories" :tab="$t('Categories')">
          <CategoriesTab @ready="noop" @refreshed="onChildRefreshed" />
        </a-tab-pane>
        <a-tab-pane key="brands" :tab="$t('Brand')">
          <BrandsTab @ready="noop" @refreshed="onChildRefreshed" />
        </a-tab-pane>
        <a-tab-pane key="customers" :tab="$t('Customers')">
          <CustomersTab @ready="noop" @refreshed="onChildRefreshed" />
        </a-tab-pane>
        <a-tab-pane key="orders" :tab="$t('Orders')">
          <OrdersTab @ready="noop" @refreshed="onChildRefreshed" />
        </a-tab-pane>
        <a-tab-pane key="logs" :tab="$t('Logs')">
          <LogsTab @ready="noop" />
        </a-tab-pane>
        <a-tab-pane key="guide" :tab="$t('Guide')">
          <WooCommerceGuide :settings="settings" :cron="cron" @open-settings="activeTab = 'settings'" />
        </a-tab-pane>
      </a-tabs>
    </a-card>
  </div>
</template>

<script setup>
/**
 * WooCommerce integration hub — 9 tabs (antd renders a pane on first
 * activation, so each tab's requests fire lazily like legacy's lazy b-tabs).
 * Hub owns the header connection badge (POST woocommerce/test-connection)
 * and the product counts passed to the Status tab (GET products?limit=1
 * totalRows + GET woocommerce/unsynced-count).
 */
import { ref, defineAsyncComponent, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHeader from '../../components/PageHeader.vue';
import http from '../../lib/http';

const StatusOverviewTab = defineAsyncComponent(() => import('./woocommerce/StatusOverviewTab.vue'));
const SettingsTab = defineAsyncComponent(() => import('./woocommerce/SettingsTab.vue'));
const ProductsTab = defineAsyncComponent(() => import('./woocommerce/ProductsTab.vue'));
const StockTab = defineAsyncComponent(() => import('./woocommerce/StockTab.vue'));
const CategoriesTab = defineAsyncComponent(() => import('./woocommerce/CategoriesTab.vue'));
const BrandsTab = defineAsyncComponent(() => import('./woocommerce/BrandsTab.vue'));
const CustomersTab = defineAsyncComponent(() => import('./woocommerce/CustomersTab.vue'));
const OrdersTab = defineAsyncComponent(() => import('./woocommerce/OrdersTab.vue'));
const LogsTab = defineAsyncComponent(() => import('./woocommerce/LogsTab.vue'));
const WooCommerceGuide = defineAsyncComponent(() => import('./woocommerce/WooCommerceGuide.vue'));

useI18n();

const activeTab = ref('status');
const connectionOk = ref(null);
const totalProducts = ref(null);
const unsyncedCount = ref(null);
const countsLoading = ref(true);
// Read once for the Guide tab: the saved settings (so it can report what is
// switched on) and the cron lines the server builds for this installation.
const settings = ref(null);
const cron = ref({});

function noop() {}

async function fetchCounts() {
  countsLoading.value = true;
  try {
    const data = await http.get('products', { limit: 1 });
    totalProducts.value = data.totalRows ?? null;
  } catch (e) {
    totalProducts.value = null;
  }
  try {
    const data = await http.get('woocommerce/unsynced-count');
    unsyncedCount.value = data.count;
  } catch (e) {
    unsyncedCount.value = null;
  }
  countsLoading.value = false;
}

async function testConnection() {
  try {
    const data = await http.post('woocommerce/test-connection');
    connectionOk.value = !!data.ok;
  } catch (e) {
    connectionOk.value = false;
  }
}

function onChildRefreshed() {
  fetchCounts();
  testConnection();
  fetchGuideData();
}

async function fetchGuideData() {
  try {
    const data = await http.get('woocommerce/settings');
    settings.value = data.settings || null;
    cron.value = data.cron || {};
  } catch (e) { /* first run: nothing saved yet */ }
}

onMounted(() => {
  fetchCounts();
  testConnection();
  fetchGuideData();
});
</script>
