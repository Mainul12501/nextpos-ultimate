<template>
  <div class="page">
    <PageHeader
      :title="$t('Manufacturing')"
      :subtitle="$t('Mfg_Dashboard_Subtitle')"
      :breadcrumb="[$t('Manufacturing'), $t('Dashboard')]"
    >
      <template #actions>
        <a-button @click="$router.push('/mrp/planning')">
          <template #icon><CalculatorOutlined /></template>
          {{ $t('Planning') }}
        </a-button>
        <a-button type="primary" @click="$router.push('/mrp/production-orders')">
          <template #icon><PlusOutlined /></template>
          {{ $t('Mfg_Production_Orders') }}
        </a-button>
      </template>
    </PageHeader>

    <a-spin :spinning="loading">
      <div class="kpis">
        <button type="button" class="kpi" @click="$router.push('/mrp/production-orders?status=open')">
          <span class="kpi-ic kpi-ic--brand"><Factory :size="18" /></span>
          <span class="kpi-text">
            <span class="kpi-value">{{ data.orders_open || 0 }}</span>
            <span class="kpi-label">{{ $t('Mfg_Open_Orders') }}</span>
            <span v-if="data.orders_late" class="kpi-sub kpi-sub--bad">{{ $t('Mfg_N_Past_Due', { n: data.orders_late }) }}</span>
          </span>
        </button>
        <button type="button" class="kpi" @click="$router.push('/mrp/work-orders')">
          <span class="kpi-ic kpi-ic--work"><Hammer :size="18" /></span>
          <span class="kpi-text">
            <span class="kpi-value">{{ data.open_work_orders || 0 }}</span>
            <span class="kpi-label">{{ $t('Mfg_Operations_Queued') }}</span>
          </span>
        </button>
        <div class="kpi kpi--static">
          <span class="kpi-ic kpi-ic--ok"><CheckCircleOutlined /></span>
          <span class="kpi-text">
            <span class="kpi-value">{{ number(data.produced_month || 0, 0) }}</span>
            <span class="kpi-label">{{ $t('Mfg_Produced_This_Month') }}</span>
            <span v-if="data.scrap_rate_month !== null" class="kpi-sub">
              {{ $t('Mfg_Pct_Scrap', { n: data.scrap_rate_month }) }}
            </span>
          </span>
        </div>
        <div class="kpi kpi--static">
          <span class="kpi-ic kpi-ic--cost"><DollarOutlined /></span>
          <span class="kpi-text">
            <span class="kpi-value">{{ money(data.cost_month || 0) }}</span>
            <span class="kpi-label">{{ $t('Mfg_Production_Cost_Month') }}</span>
          </span>
        </div>
        <button type="button" class="kpi" @click="$router.push('/mrp/quality')">
          <span class="kpi-ic" :class="qcIconClass"><ShieldCheck :size="18" /></span>
          <span class="kpi-text">
            <span class="kpi-value">
              {{ data.qc_pass_rate_30d !== null && data.qc_pass_rate_30d !== undefined
                ? data.qc_pass_rate_30d + '%' : '—' }}
            </span>
            <span class="kpi-label">{{ $t('Mfg_Qc_Pass_Rate_30d') }}</span>
            <span v-if="data.qc_rejected_30d" class="kpi-sub kpi-sub--bad">
              {{ $t('Mfg_N_Rejected', { n: number(data.qc_rejected_30d, 0) }) }}
            </span>
          </span>
        </button>
        <button type="button" class="kpi" @click="$router.push('/mrp/planning')">
          <span class="kpi-ic kpi-ic--plan"><Calculator :size="18" /></span>
          <span class="kpi-text">
            <span class="kpi-value">{{ data.pending_suggestions || 0 }}</span>
            <span class="kpi-label">{{ $t('Mfg_Planning_Suggestions') }}</span>
            <span v-if="data.latest_run" class="kpi-sub">{{ data.latest_run.reference }}</span>
          </span>
        </button>
      </div>

      <a-row :gutter="16">
        <a-col :xs="24" :xl="15">
          <ReportChart
            :data="data.output_trend || []"
            :fields="[
              { key: 'produced', label: $t('Produced') },
              { key: 'scrapped', label: $t('Scrapped') },
            ]"
            :title="$t('Mfg_Output_Last_14_Days')"
            type="bar"
            x-key="d"
            :height="300"
          />
        </a-col>
        <a-col :xs="24" :xl="9">
          <a-card size="small" :title="$t('Mfg_Load_By_Work_Centre')" class="panel">
            <a-empty v-if="!data.load_by_work_center?.length" :image="simpleImage" :description="$t('Mfg_Nothing_Queued')" />
            <ul v-else class="bars">
              <li v-for="row in data.load_by_work_center" :key="row.label">
                <div class="bar-head">
                  <span class="bar-label">{{ row.label }}</span>
                  <span class="bar-value">{{ row.hours }}h</span>
                </div>
                <div class="bar-track">
                  <div class="bar-fill" :style="{ width: pct(row.hours, maxLoad) }" />
                </div>
                <span class="bar-sub">{{ row.count === 1 ? $t('Mfg_One_Operation') : $t('Mfg_N_Operations', { n: row.count }) }}</span>
              </li>
            </ul>
          </a-card>
        </a-col>
      </a-row>

      <a-row :gutter="16" style="margin-top: 16px">
        <a-col :xs="24" :xl="12">
          <a-card size="small" :title="$t('Mfg_Waiting_On_Material')" class="panel">
            <template #extra>
              <a class="link" @click="$router.push('/mrp/production-orders?status=draft')">{{ $t('Mfg_All_Drafts') }}</a>
            </template>
            <a-empty v-if="!data.blocked_orders?.length" :image="simpleImage" :description="$t('Mfg_Nothing_Is_Short')" />
            <a-list v-else size="small" :data-source="data.blocked_orders">
              <template #renderItem="{ item }">
                <a-list-item class="row" @click="$router.push(`/mrp/production-orders/${item.id}`)">
                  <a-list-item-meta :title="item.reference" :description="item.product_name" />
                  <a-tag color="error">
                    {{ $t('Mfg_N_Short', { n: item.short_count }) }}<template v-if="item.first_short"> · {{ item.first_short }}</template>
                  </a-tag>
                </a-list-item>
              </template>
            </a-list>
          </a-card>
        </a-col>
        <a-col :xs="24" :xl="12">
          <a-card size="small" :title="$t('Mfg_Recent_Orders')" class="panel">
            <template #extra>
              <a class="link" @click="$router.push('/mrp/production-orders')">{{ $t('Mfg_All_Orders') }}</a>
            </template>
            <a-empty v-if="!data.recent_orders?.length" :image="simpleImage" :description="$t('Mfg_No_Orders_Yet')" />
            <a-list v-else size="small" :data-source="data.recent_orders">
              <template #renderItem="{ item }">
                <a-list-item class="row" @click="$router.push(`/mrp/production-orders/${item.id}`)">
                  <a-list-item-meta
                    :title="item.reference"
                    :description="`${item.product_name || ''} · ${number(item.qty_produced, 0)}/${number(item.qty_planned, 0)}`"
                  />
                  <a-space :size="4">
                    <a-tag v-if="item.priority !== 'normal'" :color="optionOf(PRIORITIES, item.priority).color">
                      {{ labelOf(PRIORITIES, item.priority) }}
                    </a-tag>
                    <a-tag :color="optionOf(ORDER_STATUSES, item.status).color">
                      {{ labelOf(ORDER_STATUSES, item.status) }}
                    </a-tag>
                  </a-space>
                </a-list-item>
              </template>
            </a-list>
          </a-card>
        </a-col>
      </a-row>

      <a-row :gutter="16" style="margin-top: 16px">
        <a-col :span="24">
          <a-card size="small" :title="$t('Mfg_Where_Orders_Stand')" class="panel">
            <div class="pipeline">
              <button
                v-for="stage in pipeline" :key="stage.value"
                type="button" class="stage"
                @click="$router.push(`/mrp/production-orders?status=${stage.value}`)"
              >
                <span class="stage-count">{{ stage.count }}</span>
                <span class="stage-label">{{ stage.label }}</span>
                <span class="stage-bar" :style="{ background: stage.colour }" />
              </button>
            </div>
          </a-card>
        </a-col>
      </a-row>
    </a-spin>
  </div>
</template>

<script setup>
/**
 * Manufacturing landing page. Answers what a plant manager asks first: what is
 * running, what is stuck, and is quality holding.
 */
import { ref, computed, onMounted } from 'vue';
import { Empty } from 'ant-design-vue';
import { PlusOutlined, CheckCircleOutlined, DollarOutlined, CalculatorOutlined } from '@ant-design/icons-vue';
import { Factory, Hammer, ShieldCheck, Calculator } from 'lucide-vue-next';
import PageHeader from '../../components/PageHeader.vue';
import ReportChart from '../../components/ReportChart.vue';
import { useFormat } from '../../composables/useFormat';
import { ORDER_STATUSES, PRIORITIES, labelOf, optionOf } from './mrpOptions';
import http from '../../lib/http';

const { money, number } = useFormat();
const simpleImage = Empty.PRESENTED_IMAGE_SIMPLE;

const loading = ref(false);
const data = ref({});

const maxLoad = computed(
  () => Math.max(1, ...(data.value.load_by_work_center || []).map(r => r.hours || 0)),
);

const qcIconClass = computed(() => {
  const rate = data.value.qc_pass_rate_30d;
  if (rate === null || rate === undefined) return 'kpi-ic--ok';
  if (rate >= 98) return 'kpi-ic--ok';
  if (rate >= 90) return 'kpi-ic--warn';
  return 'kpi-ic--bad';
});

const pipeline = computed(() => [
  { value: 'draft', label: labelOf(ORDER_STATUSES, 'draft'), count: data.value.orders_draft || 0, colour: 'rgba(128,128,128,0.5)' },
  { value: 'planned', label: labelOf(ORDER_STATUSES, 'planned'), count: data.value.orders_planned || 0, colour: '#2563eb' },
  { value: 'released', label: labelOf(ORDER_STATUSES, 'released'), count: data.value.orders_released || 0, colour: '#6d28d9' },
  { value: 'in_progress', label: labelOf(ORDER_STATUSES, 'in_progress'), count: data.value.orders_in_progress || 0, colour: '#d97706' },
  { value: 'completed', label: labelOf(ORDER_STATUSES, 'completed'), count: data.value.orders_completed || 0, colour: '#16a34a' },
]);

function pct(value, max) {
  return `${Math.max(2, Math.round(((value || 0) / max) * 100))}%`;
}

async function load() {
  loading.value = true;
  try {
    data.value = await http.get('mrp/dashboard');
  } catch (e) {
    data.value = {};
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.kpi {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid rgba(128, 128, 128, 0.2);
  border-radius: 14px;
  background: transparent;
  cursor: pointer;
  text-align: left;
  color: inherit;
  font: inherit;
  transition: border-color 0.15s ease, transform 0.12s ease;
}
.kpi:hover {
  border-color: rgba(var(--app-primary-rgb, 109, 40, 217), 0.5);
  transform: translateY(-1px);
}
.kpi--static,
.kpi--static:hover {
  cursor: default;
  border-color: rgba(128, 128, 128, 0.2);
  transform: none;
}
.kpi-ic {
  width: 40px;
  height: 40px;
  flex: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  font-size: 18px;
}
.kpi-ic--brand {
  color: var(--app-primary, #6d28d9);
  background: rgba(var(--app-primary-rgb, 109, 40, 217), 0.12);
}
.kpi-ic--work {
  color: #d97706;
  background: rgba(217, 119, 6, 0.14);
}
.kpi-ic--ok {
  color: #16a34a;
  background: rgba(22, 163, 74, 0.12);
}
.kpi-ic--warn {
  color: #d97706;
  background: rgba(217, 119, 6, 0.14);
}
.kpi-ic--bad {
  color: #dc2626;
  background: rgba(220, 38, 38, 0.12);
}
.kpi-ic--cost {
  color: #4f46e5;
  background: rgba(79, 70, 229, 0.12);
}
.kpi-ic--plan {
  color: #0891b2;
  background: rgba(8, 145, 178, 0.12);
}
.kpi-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.kpi-value {
  font-size: 20px;
  font-weight: 600;
  line-height: 1.2;
}
.kpi-label {
  font-size: 12.5px;
  opacity: 0.65;
}
.kpi-sub {
  font-size: 11.5px;
  opacity: 0.55;
}
.kpi-sub--bad {
  color: #dc2626;
  opacity: 0.9;
}
.panel {
  height: 100%;
}
.row {
  cursor: pointer;
}
.row:hover {
  background: rgba(128, 128, 128, 0.06);
}
.link {
  color: var(--app-primary, #6d28d9);
  cursor: pointer;
  font-size: 12.5px;
}
.bars {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.bar-head {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 13px;
}
.bar-label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.bar-value {
  flex: none;
  font-weight: 600;
}
.bar-track {
  height: 6px;
  border-radius: 999px;
  background: rgba(128, 128, 128, 0.15);
  margin: 5px 0 3px;
  overflow: hidden;
}
.bar-fill {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, var(--app-primary, #6d28d9), #a855f7);
}
.bar-sub {
  font-size: 11.5px;
  opacity: 0.55;
}
.pipeline {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 12px;
}
.stage {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 12px 14px 0;
  border: 1px solid rgba(128, 128, 128, 0.2);
  border-radius: 12px;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
  overflow: hidden;
}
.stage:hover {
  border-color: rgba(var(--app-primary-rgb, 109, 40, 217), 0.5);
}
.stage-count {
  font-size: 22px;
  font-weight: 600;
  line-height: 1.1;
}
.stage-label {
  font-size: 12px;
  opacity: 0.65;
  margin-bottom: 10px;
}
.stage-bar {
  height: 3px;
  margin: 0 -14px;
}
</style>
