<template>
  <div class="page">
    <PageHeader
      :title="$t('Quality Control')"
      :subtitle="$t('Mfg_Quality_Subtitle')"
      :breadcrumb="[$t('Manufacturing'), $t('Quality Control')]"
    >
      <template #actions>
        <a-button type="primary" @click="qcOpen = true">
          <template #icon><PlusOutlined /></template>
          {{ $t('Mfg_Record_Inspection') }}
        </a-button>
      </template>
    </PageHeader>

    <div class="kpis">
      <div class="kpi">
        <span class="kpi-label">{{ $t('Units_Inspected') }}</span>
        <span class="kpi-value">{{ number(totals.inspected || 0, 0) }}</span>
      </div>
      <div class="kpi">
        <span class="kpi-label">{{ $t('Rejected') }}</span>
        <span class="kpi-value kpi-value--bad">{{ number(totals.rejected || 0, 0) }}</span>
      </div>
      <div class="kpi kpi--accent">
        <span class="kpi-label">{{ $t('Pass_Rate') }}</span>
        <span class="kpi-value">
          {{ totals.pass_rate !== null && totals.pass_rate !== undefined ? totals.pass_rate + '%' : '—' }}
        </span>
      </div>
    </div>

    <a-card size="small" style="margin-bottom: 16px">
      <div class="toolbar">
        <a-input-search
          v-model:value="crud.search.value" :placeholder="$t('Mfg_Search_Reference_Order_Product')"
          allow-clear class="tb-search" @search="crud.reload"
        />
        <a-select
          v-model:value="filters.status" class="tb-item" allow-clear
          :placeholder="$t('Mfg_All_Outcomes')" :options="qcStatusOptions" @change="crud.reload"
        />
        <a-select
          v-model:value="filters.type" class="tb-item" allow-clear
          :placeholder="$t('Mfg_All_Stages')" :options="qcTypeOptions" @change="crud.reload"
        />
      </div>
    </a-card>

    <DataTable :crud="crud" :columns="columns" :searchable="false">
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'reference'">
          <strong>{{ record.reference }}</strong>
          <div class="sub">{{ labelOf(QC_TYPES, record.type) }}</div>
        </template>
        <template v-else-if="column.key === 'order'">
          <a class="link" @click="$router.push(`/mrp/production-orders/${record.production_order_id}`)">
            {{ record.order_reference }}
          </a>
          <div class="sub">{{ record.product_name }}</div>
        </template>
        <template v-else-if="column.key === 'status'">
          <a-tag :color="optionOf(QC_STATUSES, record.status).color">
            {{ labelOf(QC_STATUSES, record.status) }}
          </a-tag>
        </template>
        <template v-else-if="column.key === 'result'">
          {{ number(record.qty_passed, 0) }} / {{ number(record.qty_inspected, 0) }}
          <a-tag v-if="record.pass_rate !== null" class="mini" :color="passRateColor(record.pass_rate)">
            {{ record.pass_rate }}%
          </a-tag>
        </template>
        <template v-else-if="column.key === 'checked_at'">
          {{ record.checked_at ? dateTime(record.checked_at) : '—' }}
        </template>
        <template v-else-if="column.key === 'actions'">
          <a-tooltip :title="$t('Del')">
            <a-button type="text" size="small" danger @click="crud.remove(record, { label: record.reference })">
              <template #icon><DeleteOutlined /></template>
            </a-button>
          </a-tooltip>
        </template>
      </template>
    </DataTable>

    <QualityCheckModal v-model:open="qcOpen" @saved="onSaved" />
  </div>
</template>

<script setup>
/** The inspection register across every order. */
import { ref, reactive, computed, onMounted } from 'vue';
import { PlusOutlined, DeleteOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import DataTable from '../../components/DataTable.vue';
import QualityCheckModal from './QualityCheckModal.vue';
import { useCrudTable } from '../../composables/useCrudTable';
import { useFormat } from '../../composables/useFormat';
import {
  QC_STATUSES, QC_TYPES, labelOf, optionOf, passRateColor, tr,
} from './mrpOptions';
import { t } from '../../i18n';

const { number, dateTime } = useFormat();

const filters = reactive({ status: undefined, type: undefined });
const qcStatusOptions = computed(() => tr(QC_STATUSES));
const qcTypeOptions = computed(() => tr(QC_TYPES));

const crud = useCrudTable('mrp/quality-checks', {
  rowsKey: 'checks',
  params: () => ({ status: filters.status || '', type: filters.type || '' }),
});

const totals = computed(() => crud.payload.value?.totals || {});

const columns = computed(() => [
  { title: t('Rental_Mt_Inspection', 'Inspection'), key: 'reference', dataIndex: 'reference', width: 180 },
  { title: t('Order', 'Order'), key: 'order', dataIndex: 'order_reference' },
  { title: t('Crm_Outcome', 'Outcome'), key: 'status', dataIndex: 'status', width: 120 },
  { title: t('Passed', 'Passed'), key: 'result', width: 180 },
  { title: t('Mfg_Inspector', 'Inspector'), dataIndex: 'inspector_name', key: 'inspector_name', width: 150 },
  { title: t('When', 'When'), key: 'checked_at', dataIndex: 'checked_at', width: 160 },
  { title: '', key: 'actions', width: 70, align: 'center' },
]);

const qcOpen = ref(false);
function onSaved() {
  qcOpen.value = false;
  crud.fetchRows();
}

onMounted(crud.fetchRows);
</script>

<style scoped>
.sub {
  font-size: 11.5px;
  opacity: 0.55;
}
.mini {
  font-size: 10.5px;
  line-height: 16px;
  margin-inline-start: 4px;
}
.link {
  color: var(--app-primary, #6d28d9);
  cursor: pointer;
  font-weight: 500;
}
.kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.kpi {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 13px 16px;
  border: 1px solid rgba(128, 128, 128, 0.2);
  border-radius: 14px;
}
.kpi--accent {
  border-color: rgba(var(--app-primary-rgb, 109, 40, 217), 0.45);
  background: rgba(var(--app-primary-rgb, 109, 40, 217), 0.05);
}
.kpi-label {
  font-size: 12.5px;
  opacity: 0.65;
}
.kpi-value {
  font-size: 20px;
  font-weight: 600;
}
.kpi-value--bad {
  color: #dc2626;
}
.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}
.tb-search {
  flex: 1 1 220px;
  min-width: 180px;
}
.tb-item {
  width: 170px;
}
@media (max-width: 767px) {
  .tb-item {
    width: 100%;
  }
}
</style>
