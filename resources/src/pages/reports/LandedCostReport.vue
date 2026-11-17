<template>
  <div class="page">
    <PageHeader :title="tf('Landed_Cost_Report', 'Landed cost report')" :breadcrumb="[$t('Reports'), tf('Landed_Cost_Report', 'Landed cost report')]">
      <template #actions>
        <a-button :loading="loading" @click="load"><template #icon><ReloadOutlined /></template></a-button>
      </template>
    </PageHeader>

    <div class="lcr-filters">
      <DateRangePicker v-model:value="range" :allow-clear="false" @change="load" />
      <a-select v-model:value="filters.provider_id" :options="opt(providers)" allow-clear show-search option-filter-prop="label" :placeholder="$t('Supplier')" style="width: 200px" @change="load" />
      <a-select v-model:value="filters.warehouse_id" :options="opt(warehouses)" allow-clear :placeholder="$t('Warehouse')" style="width: 180px" @change="load" />
    </div>

    <a-row :gutter="[16, 16]" style="margin-bottom: 16px">
      <a-col :xs="12" :md="6"><a-card size="small"><a-statistic :title="$t('Purchases')" :value="summary.purchases" /></a-card></a-col>
      <a-col :xs="12" :md="6"><a-card size="small"><a-statistic :title="tf('Landed_Goods_Value', 'Goods value')" :value="moneyBase(summary.goods_value)" /></a-card></a-col>
      <a-col :xs="12" :md="6"><a-card size="small"><a-statistic :title="tf('Landed_Total', 'Total landed cost')" :value="moneyBase(summary.landed_total)" /></a-card></a-col>
      <a-col :xs="12" :md="6"><a-card size="small"><a-statistic :title="tf('Landed_Share', 'Landed cost / goods')" :value="`${number(summary.pct)}%`" /></a-card></a-col>
    </a-row>

    <a-row :gutter="[16, 16]">
      <a-col :xs="24" :lg="16">
        <a-card size="small" :title="$t('Purchases')">
          <a-table
            :columns="columns" :data-source="rows" :loading="loading" size="small" row-key="id"
            :scroll="{ x: 'max-content' }" :pagination="{ pageSize: 25, size: 'small' }"
          >
            <template #bodyCell="{ column, record }">
              <template v-if="column.key === 'Ref'">
                <a @click.prevent="$router.push(`/purchases/${record.id}`)">{{ record.Ref }}</a>
              </template>
              <template v-else-if="column.key === 'date'">{{ date(record.date) }}</template>
              <template v-else-if="column.key === 'goods_value'">{{ moneyBase(record.goods_value) }}</template>
              <template v-else-if="column.key === 'landed_total'">{{ moneyBase(record.landed_total) }}</template>
              <template v-else-if="column.key === 'pct'">{{ record.pct === null ? '—' : `${number(record.pct)}%` }}</template>
            </template>
            <template #emptyText>
              <a-empty :description="tf('Landed_Report_Empty', 'No purchases with landed costs in this period.')" style="padding: 24px 0" />
            </template>
          </a-table>
        </a-card>
      </a-col>
      <a-col :xs="24" :lg="8">
        <a-card v-for="b in breakdowns" :key="b.key" size="small" :title="b.title" style="margin-bottom: 16px">
          <div v-if="!b.items.length" class="lcr-muted">—</div>
          <div v-for="i in b.items" :key="i.name" class="lcr-row">
            <span>{{ b.label ? b.label(i.name) : i.name }}</span>
            <span>{{ moneyBase(i.amount) }}</span>
          </div>
        </a-card>
      </a-col>
    </a-row>
  </div>
</template>

<script setup>
/**
 * Reports → Landed cost report. GET report/landed-costs {from, to,
 * provider_id, warehouse_id} → {rows, summary, by_label, by_paid_to,
 * by_method, warehouses, providers} (LandedCostController::report), base
 * currency. Same permission as the purchases report.
 */
import { ref, reactive, computed, onMounted } from 'vue';
import dayjs from 'dayjs';
import { message } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import { ReloadOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import DateRangePicker from '../../components/DateRangePicker.vue';
import { useFormat } from '../../composables/useFormat';
import { t as tf } from '../../i18n';
import http from '../../lib/http';

const { t } = useI18n();
const { date, number, moneyBase } = useFormat();

const loading = ref(false);
const range = ref([dayjs().subtract(29, 'day'), dayjs()]);
const filters = reactive({ provider_id: undefined, warehouse_id: undefined });
const rows = ref([]);
const summary = ref({ purchases: 0, goods_value: 0, landed_total: 0, pct: 0 });
const byLabel = ref([]);
const byPaidTo = ref([]);
const byMethod = ref([]);
const providers = ref([]);
const warehouses = ref([]);

const opt = list => list.map(x => ({ value: x.id, label: x.name }));
const METHOD = { value: () => tf('Landed_By_Value', 'By value'), quantity: () => tf('Landed_By_Quantity', 'By quantity'), weight: () => tf('Landed_By_Weight', 'By weight') };

const columns = computed(() => [
  { title: t('Reference'), key: 'Ref' },
  { title: t('date'), key: 'date' },
  { title: t('Supplier'), dataIndex: 'supplier', key: 'supplier' },
  { title: t('Warehouse'), dataIndex: 'warehouse', key: 'warehouse' },
  { title: tf('Landed_Goods_Value', 'Goods value'), key: 'goods_value', align: 'right' },
  { title: tf('Landed_Total', 'Total landed cost'), key: 'landed_total', align: 'right' },
  { title: '%', key: 'pct', align: 'right' },
]);
const breakdowns = computed(() => [
  { key: 'label', title: tf('Landed_By_Type', 'By type'), items: byLabel.value },
  { key: 'paid', title: tf('Landed_By_Paid_To', 'By who was paid'), items: byPaidTo.value },
  { key: 'method', title: tf('Landed_By_Method', 'By spreading method'), items: byMethod.value, label: n => (METHOD[n] ? METHOD[n]() : n) },
]);

async function load() {
  loading.value = true;
  try {
    const d = await http.get('report/landed-costs', {
      from: range.value?.[0]?.format?.('YYYY-MM-DD') || '',
      to: range.value?.[1]?.format?.('YYYY-MM-DD') || '',
      provider_id: filters.provider_id || '',
      warehouse_id: filters.warehouse_id || '',
    });
    rows.value = d.rows || [];
    summary.value = d.summary || summary.value;
    byLabel.value = d.by_label || [];
    byPaidTo.value = d.by_paid_to || [];
    byMethod.value = d.by_method || [];
    providers.value = d.providers || [];
    warehouses.value = d.warehouses || [];
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.lcr-filters {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
.lcr-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 4px 0;
  border-bottom: 1px solid rgba(128, 128, 128, 0.12);
  font-size: 13px;
}
.lcr-row:last-child {
  border-bottom: none;
}
.lcr-muted {
  opacity: 0.5;
}
</style>
