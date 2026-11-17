<template>
  <div class="page">
    <PageHeader :title="tr('Reorder_Suggestions', 'Reorder suggestions')" :breadcrumb="[$t('Purchases'), tr('Reorder_Suggestions', 'Reorder suggestions')]">
      <template #actions>
        <a-space>
          <a-button @click="settingsOpen = true"><template #icon><SettingOutlined /></template>{{ tr('Reorder_Settings', 'Settings') }}</a-button>
          <a-button :loading="loading" @click="load"><template #icon><ReloadOutlined /></template></a-button>
        </a-space>
      </template>
    </PageHeader>

    <a-alert
      v-if="!migrated" type="warning" show-icon style="margin-bottom: 16px"
      :message="tr('Sec_Migration_Required', 'Database update required')"
      :description="tr('Reorder_Migration_Help', 'Run the database migrations (php artisan migrate) to enable reorder settings.')"
    />

    <!-- Summary -->
    <a-row :gutter="[16, 16]" style="margin-bottom: 16px">
      <a-col :xs="12" :md="6">
        <a-card size="small"><a-statistic :title="tr('Reorder_To_Order', 'Products to order')" :value="summary.total" /></a-card>
      </a-col>
      <a-col :xs="12" :md="6">
        <a-card size="small"><a-statistic :title="tr('Reorder_Out', 'Out of stock')" :value="summary.out" :value-style="{ color: summary.out ? '#cf1322' : undefined }" /></a-card>
      </a-col>
      <a-col :xs="12" :md="6">
        <a-card size="small"><a-statistic :title="tr('Reorder_Critical', 'Critical')" :value="summary.critical" :value-style="{ color: summary.critical ? '#d46b08' : undefined }" /></a-card>
      </a-col>
      <a-col :xs="12" :md="6">
        <a-card size="small"><a-statistic :title="tr('Reorder_Est_Value', 'Estimated cost')" :value="moneyBase(summary.value)" /></a-card>
      </a-col>
    </a-row>

    <a-card size="small">
      <!-- Filters -->
      <div class="ro-filters">
        <a-select v-model:value="filters.warehouse_id" :options="opt(warehouses)" allow-clear :placeholder="$t('Warehouse')" style="width: 180px" @change="load" />
        <a-select v-model:value="filters.provider_id" :options="opt(providers)" allow-clear show-search option-filter-prop="label" :placeholder="$t('Supplier')" style="width: 200px" @change="load" />
        <a-select v-model:value="filters.category_id" :options="opt(categories)" allow-clear show-search option-filter-prop="label" :placeholder="$t('Category')" style="width: 180px" @change="load" />
        <a-input-search v-model:value="filters.search" :placeholder="tr('Reorder_Search', 'Product name or code')" allow-clear style="width: 220px" @search="load" />
        <a-checkbox v-model:checked="filters.all" @change="load">{{ tr('Reorder_Show_All', 'Show all products') }}</a-checkbox>
      </div>

      <a-table
        :columns="columns" :data-source="rows" :loading="loading" size="small" row-key="key"
        :row-selection="rowSelection" :scroll="{ x: 'max-content' }"
        :pagination="{ pageSize: 25, showSizeChanger: true, pageSizeOptions: ['25', '50', '100', '500'], size: 'small' }"
      >
        <template #bodyCell="{ column, record }">
          <template v-if="column.key === 'name'">
            <div class="ro-name">{{ record.name }}</div>
            <div class="ro-sub">{{ record.code }} · {{ record.warehouse }}</div>
          </template>
          <template v-else-if="column.key === 'status'">
            <a-tag :color="STATUS[record.status].color" style="margin: 0">{{ STATUS[record.status].label }}</a-tag>
            <a-tooltip v-if="record.manufactured" :title="tr('Reorder_Manufactured_Help', 'Has an active bill of materials: make it in Manufacturing instead of buying it.')">
              <a-tag color="purple" style="margin: 0 0 0 4px">{{ tr('Reorder_Manufactured', 'Manufactured') }}</a-tag>
            </a-tooltip>
          </template>
          <template v-else-if="column.key === 'stock'">
            {{ number(record.on_hand) }}
            <span v-if="record.on_order" class="ro-sub"> + {{ number(record.on_order) }} {{ tr('Reorder_On_Order', 'on order') }}</span>
            <span class="ro-sub"> {{ record.base_unit }}</span>
          </template>
          <template v-else-if="column.key === 'daily'">{{ number(record.daily_sales) }}</template>
          <template v-else-if="column.key === 'days_left'">
            <span :class="{ 'ro-danger': record.days_left !== null && record.days_left < record.lead_time_days }">
              {{ record.days_left === null ? '—' : number(record.days_left) }}
            </span>
            <span class="ro-sub"> / {{ record.lead_time_days }}{{ tr('Reorder_Days_Short', 'd') }}</span>
          </template>
          <template v-else-if="column.key === 'provider'">
            <a-select
              v-model:value="record.provider_id" :options="opt(providers)" show-search option-filter-prop="label" size="small"
              :placeholder="tr('Reorder_Pick_Supplier', 'Pick a supplier')" style="width: 170px" :status="isSelected(record) && !record.provider_id ? 'error' : ''"
            />
          </template>
          <template v-else-if="column.key === 'unit_cost'">
            <a-input-number v-model:value="record.unit_cost" :min="0" size="small" style="width: 100px" />
          </template>
          <template v-else-if="column.key === 'qty'">
            <a-input-number v-model:value="record.suggested_qty" :min="0" size="small" style="width: 90px" />
            <span class="ro-sub"> {{ record.purchase_unit }}</span>
          </template>
          <template v-else-if="column.key === 'total'">{{ moneyBase((Number(record.suggested_qty) || 0) * (Number(record.unit_cost) || 0)) }}</template>
        </template>
        <template #emptyText>
          <a-empty :description="filters.all ? tr('Reorder_None_All', 'No stocked products match these filters.') : tr('Reorder_None', 'Nothing needs reordering right now.')" style="padding: 24px 0" />
        </template>
      </a-table>

      <!-- Selection bar -->
      <div class="ro-footer">
        <span>
          {{ tr('Reorder_Selected', ':count selected').replace(':count', selectedRows.length) }}
          <template v-if="selectedRows.length">
            · {{ tr('Reorder_Orders_Count', ':count purchase order(s)').replace(':count', orderGroups) }}
            · <strong>{{ moneyBase(selectedTotal) }}</strong>
          </template>
        </span>
        <a-button type="primary" :disabled="!selectedRows.length" :loading="creating" @click="createOrders">
          <template #icon><ShoppingCartOutlined /></template>{{ tr('Reorder_Create_Orders', 'Create purchase orders') }}
        </a-button>
      </div>
    </a-card>

    <!-- Defaults -->
    <a-drawer v-model:open="settingsOpen" placement="right" :width="drawerWidth" :title="tr('Reorder_Settings_Title', 'Reorder defaults')">
      <p class="ro-help">{{ tr('Reorder_Settings_Help', 'Used for every product unless its Reordering card (product form) sets its own lead time or safety stock.') }}</p>
      <a-form layout="vertical">
        <a-form-item :label="tr('Reorder_Window', 'Average sales over (days)')" :extra="tr('Reorder_Window_Help', 'Recent sales used to work out how much sells per day.')">
          <a-input-number v-model:value="defaults.sales_window_days" :min="7" :max="365" style="width: 160px" />
        </a-form-item>
        <a-form-item :label="tr('Reorder_Lead_Time', 'Lead time (days)')" :extra="tr('Reorder_Lead_Help', 'Days between ordering and receiving the goods.')">
          <a-input-number v-model:value="defaults.lead_time_days" :min="0" :max="365" style="width: 160px" />
        </a-form-item>
        <a-form-item :label="tr('Reorder_Safety_Days', 'Safety stock (days)')" :extra="tr('Reorder_Safety_Help', 'Extra days of stock kept in case of delays or a busy week.')">
          <a-input-number v-model:value="defaults.safety_days" :min="0" :max="365" style="width: 160px" />
        </a-form-item>
        <a-form-item :label="tr('Reorder_Coverage', 'Order enough for (days)')" :extra="tr('Reorder_Coverage_Help', 'How many days of sales each order should last once it arrives.')">
          <a-input-number v-model:value="defaults.coverage_days" :min="1" :max="365" style="width: 160px" />
        </a-form-item>
      </a-form>
      <div class="ro-how">
        <div class="ro-how-title">{{ tr('Reorder_How', 'How suggestions work') }}</div>
        <ul>
          <li>{{ tr('Reorder_How_1', 'Reorder when stock + open orders fall to: daily sales × (lead time + safety days), and never below the product’s stock alert.') }}</li>
          <li>{{ tr('Reorder_How_2', 'Order enough to cover lead time + safety days + the days above. Products without recent sales are topped up to twice their stock alert.') }}</li>
          <li>{{ tr('Reorder_How_3', 'Quantities are in the purchase unit, rounded up to the product’s minimum order and multiple.') }}</li>
        </ul>
      </div>
      <!-- Daily email digest (ReorderDigest) -->
      <div class="ro-digest">
        <div class="ro-how-title"><MailOutlined /> {{ tr('Reorder_Digest', 'Daily email digest') }}</div>
        <p class="ro-help" style="margin-bottom: 10px">{{ tr('Reorder_Digest_Help', 'Once a day, email the products that need reordering to the people below. Nothing is sent on days with nothing to reorder.') }}</p>
        <a-form layout="vertical">
          <a-form-item style="margin-bottom: 10px">
            <a-switch v-model:checked="defaults.digest_enabled" /> <span style="margin-inline-start: 8px">{{ tr('Reorder_Digest_Enable', 'Send the daily digest') }}</span>
          </a-form-item>
          <template v-if="defaults.digest_enabled">
            <a-form-item :label="tr('Reorder_Digest_Recipients', 'Send to')" :extra="tr('Reorder_Digest_Recipients_Help', 'Each person only sees the warehouses they have access to.')">
              <a-select
                v-model:value="defaults.digest_user_ids" mode="multiple" show-search option-filter-prop="label"
                :options="staff.map(u => ({ value: u.id, label: `${u.username} (${u.email})` }))"
                :placeholder="tr('Reorder_Digest_Pick', 'Pick staff members')"
              />
            </a-form-item>
            <a-form-item :label="tr('Reorder_Digest_Hour', 'Send at')">
              <a-select v-model:value="defaults.digest_hour" :options="hourOptions" style="width: 140px" />
            </a-form-item>
          </template>
        </a-form>
      </div>

      <a-space>
        <a-button type="primary" :loading="savingSettings" :disabled="!migrated" @click="saveSettings">{{ $t('submit') }}</a-button>
        <a-tooltip :title="tr('Reorder_Digest_Send_Help', 'Sends the digest to the saved recipients now, to check it arrives.')">
          <a-button :loading="sendingDigest" :disabled="!migrated || !defaults.digest_user_ids?.length" @click="sendDigest">
            <SendOutlined /> {{ tr('Reorder_Digest_Send_Now', 'Send now') }}
          </a-button>
        </a-tooltip>
      </a-space>
    </a-drawer>
  </div>
</template>

<script setup>
/**
 * Purchases → Reorder suggestions (smart reordering, phase 2).
 * GET reorder/suggestions {warehouse_id, provider_id, category_id, search, all}
 *   → {rows, summary, defaults, warehouses, providers, categories, migrated}
 *   (rows from App\Services\Reorder\ReorderEngine, quantities/costs in the
 *   purchase unit, base currency).
 * POST reorder/purchase-orders {lines: [{product_id, product_variant_id,
 *   warehouse_id, provider_id, quantity, unit_cost}]} → {purchases: [{id, Ref…}]}
 *   one draft (ordered) purchase per supplier + warehouse.
 * PUT reorder/settings — the global defaults.
 * Strings use Reorder_* keys with an English fallback.
 */
import { ref, reactive, computed, h, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { message, Modal } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import { SettingOutlined, ReloadOutlined, ShoppingCartOutlined, MailOutlined, SendOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import { useFormat } from '../../composables/useFormat';
import http from '../../lib/http';

const { t } = useI18n();
const tr = (key, fallback) => {
  const out = t(key);
  return out === key ? fallback : out;
};
const router = useRouter();
const { number, moneyBase } = useFormat();

const loading = ref(false);
const creating = ref(false);
const migrated = ref(true);
const rows = ref([]);
const summary = ref({ total: 0, out: 0, critical: 0, low: 0, value: 0 });
const warehouses = ref([]);
const providers = ref([]);
const categories = ref([]);
const selectedKeys = ref([]);
const filters = reactive({ warehouse_id: undefined, provider_id: undefined, category_id: undefined, search: '', all: false });

const settingsOpen = ref(false);
const savingSettings = ref(false);
const defaults = ref({
  sales_window_days: 30, lead_time_days: 7, safety_days: 3, coverage_days: 30,
  digest_enabled: false, digest_hour: 8, digest_user_ids: [],
});
const staff = ref([]);
const sendingDigest = ref(false);
const hourOptions = Array.from({ length: 24 }, (_, h) => ({ value: h, label: `${String(h).padStart(2, '0')}:00` }));
const drawerWidth = Math.min(460, window.innerWidth);

const STATUS = computed(() => ({
  out: { label: tr('Reorder_Status_Out', 'Out of stock'), color: 'error' },
  critical: { label: tr('Reorder_Status_Critical', 'Critical'), color: 'orange' },
  low: { label: tr('Reorder_Status_Low', 'Low'), color: 'gold' },
  ok: { label: tr('Reorder_Status_Ok', 'OK'), color: 'success' },
}));

const opt = list => list.map(x => ({ value: x.id, label: x.name }));

const columns = computed(() => [
  { title: t('Product'), key: 'name', fixed: 'left' },
  { title: t('Status'), key: 'status' },
  { title: tr('Reorder_Col_Stock', 'Stock'), key: 'stock' },
  { title: tr('Reorder_Col_Daily', 'Sold / day'), key: 'daily', align: 'right' },
  { title: tr('Reorder_Col_Days_Left', 'Days left / lead'), key: 'days_left', align: 'right' },
  { title: t('Supplier'), key: 'provider' },
  { title: tr('Reorder_Col_Unit_Cost', 'Unit cost'), key: 'unit_cost' },
  { title: tr('Reorder_Col_Qty', 'Order qty'), key: 'qty' },
  { title: t('Total'), key: 'total', align: 'right' },
]);

// Manufactured products are made, not bought: they can't be selected.
const rowSelection = computed(() => ({
  selectedRowKeys: selectedKeys.value,
  onChange: keys => { selectedKeys.value = keys; },
  getCheckboxProps: r => ({ disabled: r.manufactured || !(Number(r.suggested_qty) > 0) }),
}));
const selectedRows = computed(() => rows.value.filter(r => selectedKeys.value.includes(r.key)));
const isSelected = r => selectedKeys.value.includes(r.key);
const selectedTotal = computed(() => selectedRows.value.reduce((sum, r) => sum + (Number(r.suggested_qty) || 0) * (Number(r.unit_cost) || 0), 0));
const orderGroups = computed(() => new Set(selectedRows.value.map(r => `${r.provider_id || '?'}:${r.warehouse_id}`)).size);

async function load() {
  loading.value = true;
  try {
    const d = await http.get('reorder/suggestions', {
      warehouse_id: filters.warehouse_id || '',
      provider_id: filters.provider_id || '',
      category_id: filters.category_id || '',
      search: filters.search || '',
      all: filters.all ? 1 : '',
    });
    rows.value = d.rows || [];
    summary.value = d.summary || summary.value;
    if (d.defaults) defaults.value = { ...d.defaults };
    warehouses.value = d.warehouses || [];
    providers.value = d.providers || [];
    categories.value = d.categories || [];
    staff.value = d.staff || [];
    migrated.value = d.migrated !== false;
    // Pre-select everything that can be ordered.
    selectedKeys.value = rows.value.filter(r => !r.manufactured && Number(r.suggested_qty) > 0).map(r => r.key);
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    loading.value = false;
  }
}

async function createOrders() {
  const lines = selectedRows.value.filter(r => Number(r.suggested_qty) > 0);
  if (lines.some(r => !r.provider_id)) {
    message.error(tr('Reorder_Need_Supplier', 'Choose a supplier for every selected line.'));
    return;
  }
  creating.value = true;
  try {
    const d = await http.post('reorder/purchase-orders', {
      lines: lines.map(r => ({
        product_id: r.product_id,
        product_variant_id: r.product_variant_id,
        warehouse_id: r.warehouse_id,
        provider_id: r.provider_id,
        quantity: Number(r.suggested_qty),
        unit_cost: Number(r.unit_cost) || 0,
      })),
    });
    const created = d.purchases || [];
    Modal.success({
      title: tr('Reorder_Created_Title', ':count purchase order(s) created').replace(':count', created.length),
      width: 520,
      content: h('div', [
        h('p', { style: 'margin-bottom:8px' }, tr('Reorder_Created_Help', 'They are saved as “Ordered”: stock is added when you receive them.')),
        h('ul', { style: 'padding-inline-start:18px;margin:0' }, created.map(p => h('li', [
          h('a', { href: '#', onClick: ev => { ev.preventDefault(); Modal.destroyAll(); router.push(`/purchases/${p.id}`); } }, p.Ref),
          ` — ${p.provider || ''} · ${p.warehouse || ''} · ${p.lines} ${tr('Reorder_Lines', 'line(s)')} · ${moneyBase(p.total)}`,
        ]))),
      ]),
      okText: tr('Reorder_View_Purchases', 'View purchases'),
      onOk: () => router.push('/purchases'),
    });
    load();
  } catch (e) {
    const line = e?.data?.line;
    const name = Number.isInteger(line) && lines[line] ? `${lines[line].name}: ` : '';
    message.error(name + (e?.data?.message || t('InvalidData')));
  } finally {
    creating.value = false;
  }
}

async function saveSettings() {
  savingSettings.value = true;
  try {
    const d = await http.put('reorder/settings', defaults.value);
    defaults.value = d.defaults;
    settingsOpen.value = false;
    message.success(t('Successfully_Updated'));
    load();
  } catch (e) {
    message.error(e?.data?.message || Object.values(e?.data?.errors || {})[0]?.[0] || t('InvalidData'));
  } finally {
    savingSettings.value = false;
  }
}

async function sendDigest() {
  sendingDigest.value = true;
  try {
    const d = await http.post('reorder/digest/send');
    if (d.sent) message.success(tr('Reorder_Digest_Sent', 'Digest sent to :count recipient(s).').replace(':count', d.sent));
    else message.info(tr('Reorder_Digest_Nothing', 'Nothing was sent: no products need reordering, or the mail could not be sent.'));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    sendingDigest.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.ro-filters {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: center;
  margin-bottom: 12px;
}
.ro-name {
  font-weight: 500;
  white-space: nowrap;
}
.ro-sub {
  font-size: 12px;
  opacity: 0.6;
}
.ro-danger {
  color: #cf1322;
  font-weight: 600;
}
.ro-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 12px;
}
.ro-help {
  font-size: 13px;
  opacity: 0.7;
}
.ro-how {
  border: 1px solid rgba(128, 128, 128, 0.2);
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 16px;
  font-size: 12.5px;
}
.ro-how-title {
  font-weight: 600;
  margin-bottom: 4px;
}
.ro-digest {
  border-top: 1px solid rgba(128, 128, 128, 0.2);
  padding-top: 14px;
  margin-bottom: 16px;
}
.ro-how ul {
  margin: 0;
  padding-inline-start: 18px;
  line-height: 1.6;
}
</style>
