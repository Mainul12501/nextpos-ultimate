<template>
  <div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px">
      <a-select
        v-model:value="filterAction" style="width: 140px"
        :options="[
          { label: $t('All'), value: 'all' },
          { label: $t('Product'), value: 'products' },
          { label: $t('Stock'), value: 'stock' },
          { label: $t('Order'), value: 'orders' },
        ]"
      />
      <a-select
        v-model:value="filterStatus" style="width: 140px"
        :options="[
          { label: $t('All'), value: 'all' },
          { label: $t('Success'), value: 'info' },
          { label: $t('Warning'), value: 'warning' },
          { label: $t('Failed'), value: 'error' },
        ]"
      />
      <a-date-picker v-model:value="filterFrom" value-format="YYYY-MM-DD" :placeholder="$t('From')" />
      <a-date-picker v-model:value="filterTo" value-format="YYYY-MM-DD" :placeholder="$t('To')" />
      <a-popconfirm :title="$t('AreYouSure')" :ok-text="$t('Yes')" :cancel-text="$t('No')" @confirm="clearLogs">
        <a-button danger :loading="processing" style="margin-left: auto">
          <DeleteOutlined /> {{ $t('Clear') }}
        </a-button>
      </a-popconfirm>
    </div>

    <a-table
      :columns="columns" :data-source="filteredLogs" :loading="loading"
      size="small" :row-key="(_r, i) => i"
      :pagination="{ pageSize: 10, showSizeChanger: false }"
      :locale="{ emptyText: $t('NodataAvailable') }"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'date'">{{ formatLogDate(record.created_at) }}</template>
        <template v-else-if="column.key === 'action'">{{ formatAction(record.action, $t) }}</template>
        <template v-else-if="column.key === 'direction'">{{ formatDirection(record.action, $t) }}</template>
        <template v-else-if="column.key === 'status'">
          <a-tag :color="levelColor(record.level)">{{ formatStatus(record.level, $t) }}</a-tag>
        </template>
        <template v-else-if="column.key === 'message'">
          <a-tooltip :title="record.message">
            <span v-if="kindOf(record) !== 'Ok'">{{ $t(`Woo_Log_${kindOf(record)}_Title`) }}</span>
            <span v-else>{{ record.message }}</span>
            <span v-if="record.product" class="woo-log-muted"> — {{ record.product.name }}</span>
          </a-tooltip>
        </template>
        <template v-else-if="column.key === 'details'">
          <a-button type="link" size="small" @click="openDetails(record)">{{ $t('Details') }}</a-button>
        </template>
      </template>
    </a-table>

    <a-modal
      v-model:open="detailsOpen" :title="$t('Woo_Log_Details_Title')" :footer="null" width="680px" destroy-on-close
    >
      <template v-if="selected">
        <a-alert
          :type="alertType(selected.level)" show-icon style="margin-bottom: 16px"
          :message="$t(`Woo_Log_${selectedKind}_Title`)"
          :description="$t(`Woo_Log_${selectedKind}_Explain`, { count: selected.context?.consecutive_failures ?? '' })"
        />

        <template v-if="causeKind">
          <h4 style="margin-bottom: 4px">{{ $t('Woo_Log_Cause') }}</h4>
          <p style="margin-bottom: 4px"><strong>{{ $t(`Woo_Log_${causeKind}_Title`) }}</strong></p>
          <p>{{ $t(`Woo_Log_${causeKind}_Explain`) }}</p>
        </template>

        <h4 style="margin-bottom: 4px">{{ $t('Woo_Log_How_To_Fix') }}</h4>
        <ol style="padding-left: 20px; margin-bottom: 16px">
          <li v-for="(step, i) in fixSteps" :key="i">{{ step }}</li>
        </ol>

        <a-descriptions bordered size="small" :column="1" style="margin-bottom: 16px">
          <a-descriptions-item :label="$t('date')">{{ formatLogDate(selected.created_at) }}</a-descriptions-item>
          <a-descriptions-item :label="$t('Direction')">
            {{ formatAction(selected.action, $t) }} · {{ formatDirection(selected.action, $t) }}
          </a-descriptions-item>
          <a-descriptions-item v-if="selected.product" :label="$t('Woo_Log_Stocky_Product')">
            <router-link :to="{ name: 'product-details', params: { id: selected.product.id } }">
              {{ selected.product.name }}
            </router-link>
            <span class="woo-log-muted"> (#{{ selected.product.id }})</span>
          </a-descriptions-item>
          <a-descriptions-item v-else-if="selected.context?.product_id" :label="$t('Woo_Log_Stocky_Product')">
            #{{ selected.context.product_id }}
          </a-descriptions-item>
          <a-descriptions-item v-if="selected.context?.sku || selected.product?.code" :label="$t('Woo_Log_Sku')">
            {{ selected.context?.sku || selected.product?.code }}
          </a-descriptions-item>
          <a-descriptions-item v-if="barcode" :label="$t('Woo_Log_Barcode')">{{ barcode }}</a-descriptions-item>
          <a-descriptions-item v-if="ownWooId" :label="$t('Woo_Log_Linked_Woo_Product')">
            <a v-if="storeUrl" :href="wooEditUrl(ownWooId)" target="_blank" rel="noopener">#{{ ownWooId }}</a>
            <span v-else>#{{ ownWooId }}</span>
          </a-descriptions-item>
          <a-descriptions-item
            v-if="wooErr.resourceId && ['Gtin', 'Sku'].includes(effectiveKind)"
            :label="$t('Woo_Log_Conflicting_Woo_Product')"
          >
            <a v-if="storeUrl" :href="wooEditUrl(wooErr.resourceId)" target="_blank" rel="noopener">#{{ wooErr.resourceId }}</a>
            <span v-else>#{{ wooErr.resourceId }}</span>
            <span v-if="String(wooErr.resourceId) === String(ownWooId)" class="woo-log-muted">
              — {{ $t('Woo_Log_Same_As_Linked') }}
            </span>
          </a-descriptions-item>
          <a-descriptions-item v-if="selected.gtin_used_by?.length" :label="$t('Woo_Log_Barcode_Also_On')">
            <div v-for="(row, i) in selected.gtin_used_by" :key="i">
              <router-link :to="{ name: 'product-details', params: { id: row.product_id } }">{{ row.name }}</router-link>
              <span v-if="row.variant"> / {{ row.variant }}</span>
              <span class="woo-log-muted"> ({{ row.code }})</span>
            </div>
          </a-descriptions-item>
          <a-descriptions-item v-if="selected.context?.woocommerce_order_id" :label="$t('Woo_Log_Woo_Order')">
            #{{ selected.context.woocommerce_order_id }}
          </a-descriptions-item>
          <a-descriptions-item v-if="wooErr.message" :label="$t('Woo_Log_Woo_Said')">{{ wooErr.message }}</a-descriptions-item>
          <a-descriptions-item v-if="wooErr.status" :label="$t('Woo_Log_Http_Status')">
            {{ wooErr.status }}<span v-if="wooErr.code"> · {{ wooErr.code }}</span>
          </a-descriptions-item>
        </a-descriptions>

        <a-collapse ghost>
          <a-collapse-panel key="raw" :header="$t('Woo_Log_Technical')">
            <p style="margin-bottom: 8px"><code>{{ selected.action }}</code> — {{ selected.message }}</p>
            <pre class="woo-log-raw">{{ prettyContext }}</pre>
          </a-collapse-panel>
        </a-collapse>
      </template>
    </a-modal>
  </div>
</template>

<script setup>
/**
 * WooCommerce sync logs — GET woocommerce/logs → {data}; DELETE
 * woocommerce/logs clears. Filters are CLIENT-SIDE like legacy: action
 * prefix (products/stock/orders), level, from/to date range; sorted desc.
 */
import { ref, computed, onMounted } from 'vue';
import { message } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import { DeleteOutlined } from '@ant-design/icons-vue';
import http from '../../../lib/http';
import {
  formatLogDate, formatAction, formatDirection, formatStatus, levelColor,
  explainKind, parseWooError,
} from './wooLog';

const { t } = useI18n();
const emit = defineEmits(['ready']);

const loading = ref(true);
const processing = ref(false);
const logs = ref([]);
const filterAction = ref('all');
const filterStatus = ref('all');
const filterFrom = ref(null);
const filterTo = ref(null);

const filteredLogs = computed(() => {
  let out = logs.value.slice();
  if (filterAction.value !== 'all') out = out.filter(l => (l.action || '').startsWith(filterAction.value));
  if (filterStatus.value !== 'all') out = out.filter(l => (l.level || '') === filterStatus.value);
  if (filterFrom.value) {
    const from = new Date(filterFrom.value + 'T00:00:00');
    out = out.filter(l => new Date(l.created_at) >= from);
  }
  if (filterTo.value) {
    const to = new Date(filterTo.value + 'T23:59:59');
    out = out.filter(l => new Date(l.created_at) <= to);
  }
  out.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
  return out;
});

const columns = computed(() => [
  { title: t('date'), key: 'date', width: 150 },
  { title: t('Action'), key: 'action', width: 110 },
  { title: t('Direction'), key: 'direction', width: 180 },
  { title: t('Status'), key: 'status', width: 100 },
  { title: t('Message'), dataIndex: 'message', key: 'message', ellipsis: true },
  { title: '', key: 'details', width: 90, align: 'right' },
]);

// ---- Details modal: plain-language explanation of one log row ----
const storeUrl = ref('');
const detailsOpen = ref(false);
const selected = ref(null);

const kindOf = (record) => explainKind(record);
const selectedKind = computed(() => (selected.value ? explainKind(selected.value) : 'Ok'));
const wooErr = computed(() => parseWooError(selected.value?.context));

// An abort row only says "too many failures"; the real reason is the last Woo response it carries.
const causeKind = computed(() => {
  if (selectedKind.value !== 'Abort') return null;
  const k = explainKind({ level: 'error', message: '', context: selected.value?.context });
  return k === 'GenericError' ? null : k;
});
// The kind whose fix steps and conflict links apply.
const effectiveKind = computed(() => causeKind.value || selectedKind.value);

// Fix texts hold one step per line.
const fixSteps = computed(() => {
  const kinds = causeKind.value ? [causeKind.value, 'Abort'] : [selectedKind.value];
  return kinds.flatMap(k => t(`Woo_Log_${k}_Fix`).split('\n')).map(s => s.trim()).filter(Boolean);
});

const barcode = computed(() => selected.value?.context?.gtin
  || (effectiveKind.value === 'Gtin' ? selected.value?.product?.gtin : '') || '');
const ownWooId = computed(() => selected.value?.context?.woocommerce_id
  || selected.value?.context?.woocommerce_id_local
  || selected.value?.product?.woocommerce_id || null);

const prettyContext = computed(() => {
  const ctx = { ...(selected.value?.context || {}) };
  for (const k of ['body', 'last_body']) {
    if (typeof ctx[k] === 'string') {
      try { ctx[k] = JSON.parse(ctx[k]); } catch (e) { /* keep as text */ }
    }
  }
  return JSON.stringify(ctx, null, 2);
});

function wooEditUrl(id) {
  return `${storeUrl.value}/wp-admin/post.php?post=${encodeURIComponent(id)}&action=edit`;
}

function alertType(level) {
  if (level === 'error') return 'error';
  if (level === 'warning') return 'warning';
  return 'success';
}

function openDetails(record) {
  selected.value = record;
  detailsOpen.value = true;
}

async function load() {
  try {
    const data = await http.get('woocommerce/logs');
    logs.value = data.data || [];
    storeUrl.value = data.store_url || '';
  } catch (e) {
    logs.value = [];
  }
}

async function clearLogs() {
  processing.value = true;
  try {
    await http.delete('woocommerce/logs');
    message.success(t('Successfully_Updated'));
    await load();
  } catch (e) {
    message.error(t('Not_Available'));
  } finally {
    processing.value = false;
  }
}

onMounted(async () => {
  await load();
  loading.value = false;
  emit('ready');
});
</script>

<style scoped>
.woo-log-muted {
  opacity: 0.6;
}
.woo-log-raw {
  white-space: pre-wrap;
  word-break: break-all;
  max-height: 260px;
  overflow: auto;
  font-size: 12px;
  margin: 0;
}
</style>
