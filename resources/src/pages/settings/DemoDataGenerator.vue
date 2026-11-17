<template>
  <div>
    <div v-if="loading" style="display: flex; justify-content: center; padding: 48px 0">
      <a-spin size="large" />
    </div>

    <template v-else>
      <!-- Real-data warning, as requested: generating is still allowed, but the
           admin is told demo rows will mix with real ones and how reset behaves. -->
      <a-alert
        v-if="hasRealData"
        type="warning"
        show-icon
        :message="$t('Set_Demo_Real_Data_Title')"
        style="margin-bottom: 16px"
      >
        <template #description>
          <i18n-t keypath="Set_Demo_Real_Data_Desc" tag="span">
            <template #summary>{{ realSummary }}</template>
            <template #reset><strong>{{ $t('Set_Reset_Demo_Data') }}</strong></template>
          </i18n-t>
        </template>
      </a-alert>
      <a-alert
        v-else
        type="info"
        show-icon
        :message="$t('Set_Demo_Intro_Title')"
        :description="$t('Set_Demo_Intro_Desc')"
        style="margin-bottom: 16px"
      />

      <!-- ============================ Generate ============================ -->
      <a-card :title="$t('Set_Generate_Demo_Data')" size="small" style="margin-bottom: 16px">
        <a-form layout="vertical">
          <a-row :gutter="16">
            <a-col v-for="f in fields" :key="f.key" :xs="12" :md="8" :lg="6">
              <a-form-item :label="f.label">
                <a-input-number v-model:value="counts[f.key]" :min="0" :max="300" style="width: 100%" />
              </a-form-item>
            </a-col>
          </a-row>
        </a-form>
        <div class="ddg-hint">
          {{ $t('Set_Demo_Docs_Hint') }}
        </div>
        <a-button type="primary" :loading="generating" :disabled="totalRequested === 0" @click="onGenerate">
          <template #icon><ThunderboltOutlined /></template>
          {{ $t('Set_Generate_Demo_Data') }}
        </a-button>
      </a-card>

      <!-- ============================== Reset ============================== -->
      <a-card size="small">
        <template #title>
          {{ $t('Set_Current_Demo_Data') }}
          <a-tag v-if="totalDemo" color="purple" style="margin-inline-start: 8px">{{ $t('Set_N_Records', { n: totalDemo }) }}</a-tag>
        </template>
        <template v-if="totalDemo">
          <a-row :gutter="[16, 16]" style="margin-bottom: 16px">
            <a-col v-for="f in fields" :key="f.key" :xs="12" :md="8" :lg="6">
              <a-statistic :title="f.label" :value="demo[f.key] || 0" />
            </a-col>
          </a-row>
          <a-button danger :loading="resetting" @click="onReset">
            <template #icon><DeleteOutlined /></template>
            {{ $t('Set_Reset_Demo_Data') }}
          </a-button>
        </template>
        <a-empty v-else :description="$t('Set_No_Demo_Data')" />
      </a-card>
    </template>
  </div>
</template>

<script setup>
/**
 * Demo Data generator — the "demo" section of System Settings.
 *
 * GET  demo_data/status    → { demo: {entity: n}, real: {entity: n} }
 * POST demo_data/generate  → { created: {entity: n} } (counts capped at 300 each)
 * DELETE demo_data         → { deleted: {entity: n} } (removes ONLY tagged rows)
 *
 * Documents (sales/purchases/quotations) reference demo entities exclusively,
 * so the backend may create a few more products/customers/suppliers than
 * requested — the created summary reflects what actually happened.
 */
import { ref, reactive, computed, onMounted, createVNode } from 'vue';
import { message, Modal } from 'ant-design-vue';
import { ThunderboltOutlined, DeleteOutlined, ExclamationCircleOutlined } from '@ant-design/icons-vue';
import { useI18n } from 'vue-i18n';
import http from '../../lib/http';

const { t } = useI18n();

// countKey: '{n} products'-style phrase used in the created/removed summaries.
const FIELD_DEFS = [
  { key: 'products', labelKey: 'Products', countKey: 'Promo_N_Products' },
  { key: 'clients', labelKey: 'Customers', countKey: 'Set_Demo_N_Customers' },
  { key: 'providers', labelKey: 'Suppliers', countKey: 'Set_Demo_N_Suppliers' },
  { key: 'sales', labelKey: 'Sales', countKey: 'Set_Demo_N_Sales' },
  { key: 'purchases', labelKey: 'Purchases', countKey: 'Set_Demo_N_Purchases' },
  { key: 'quotations', labelKey: 'Quotations', countKey: 'Set_Demo_N_Quotations' },
  { key: 'expenses', labelKey: 'Expenses', countKey: 'Set_Demo_N_Expenses' },
];
const fields = computed(() => FIELD_DEFS.map(f => ({ ...f, label: t(f.labelKey) })));

const loading = ref(true);
const generating = ref(false);
const resetting = ref(false);
const demo = ref({});
const real = ref({});

const counts = reactive({
  products: 20, clients: 10, providers: 10,
  sales: 50, purchases: 25, quotations: 10, expenses: 15,
});

const totalRequested = computed(() => FIELD_DEFS.reduce((n, f) => n + (Number(counts[f.key]) || 0), 0));
const totalDemo = computed(() => FIELD_DEFS.reduce((n, f) => n + (Number(demo.value[f.key]) || 0), 0));
const hasRealData = computed(() => FIELD_DEFS.some(f => Number(real.value[f.key]) > 0));

const realSummary = computed(() => summary(real.value));

async function loadStatus() {
  try {
    const data = await http.get('demo_data/status');
    demo.value = data.demo || {};
    real.value = data.real || {};
  } catch (e) {
    message.error(e?.data?.message || e?.message || t('Set_Demo_Status_Failed'));
  }
}

function summary(map) {
  return FIELD_DEFS
    .filter(f => Number(map?.[f.key]) > 0)
    .map(f => t(f.countKey, { n: map[f.key] }))
    .join(', ');
}

async function generate() {
  generating.value = true;
  try {
    const payload = {};
    FIELD_DEFS.forEach(f => { payload[f.key] = Number(counts[f.key]) || 0; });
    const data = await http.post('demo_data/generate', payload);
    message.success(t('Set_Demo_Created', { summary: summary(data.created) || t('Set_Demo_Nothing') }), 6);
    await loadStatus();
  } catch (e) {
    message.error(e?.data?.message || e?.message || t('Set_Demo_Generation_Failed'));
  } finally {
    generating.value = false;
  }
}

function onGenerate() {
  if (!hasRealData.value) return generate();
  Modal.confirm({
    title: t('Set_Demo_Confirm_Generate_Title'),
    icon: createVNode(ExclamationCircleOutlined),
    content: t('Set_Demo_Confirm_Generate_Content', { summary: realSummary.value }),
    okText: t('Generate'),
    onOk: generate,
  });
}

function onReset() {
  Modal.confirm({
    title: t('Set_Demo_Confirm_Reset_Title'),
    icon: createVNode(ExclamationCircleOutlined),
    content: t('Set_Demo_Confirm_Reset_Content', { n: totalDemo.value }),
    okText: t('Set_Reset_Demo_Data'),
    okType: 'danger',
    async onOk() {
      resetting.value = true;
      try {
        const data = await http.delete('demo_data');
        message.success(t('Set_Demo_Removed', { summary: summary(data.deleted) || t('Set_Demo_Nothing_To_Remove') }), 6);
        await loadStatus();
      } catch (e) {
        message.error(e?.data?.message || e?.message || t('Set_Demo_Reset_Failed'));
      } finally {
        resetting.value = false;
      }
    },
  });
}

onMounted(async () => {
  await loadStatus();
  loading.value = false;
});
</script>

<style scoped>
.ddg-hint {
  font-size: 12px;
  color: #8c8c8c;
  margin: -4px 0 16px;
  max-width: 640px;
}
</style>
