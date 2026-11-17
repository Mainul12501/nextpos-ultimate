<template>
  <div class="page">
    <PageHeader title="Google Sheets" :breadcrumb="[$t('Integrations'), 'Google Sheets']">
      <template #actions>
        <a-tag v-if="!isLoading" :color="state.connected ? 'success' : 'default'" style="font-size: 13px; padding: 4px 12px">
          {{ state.connected ? $t('Connected') : $t('Not_connected') }}
        </a-tag>
      </template>
    </PageHeader>

    <div v-if="isLoading" style="display: flex; justify-content: center; padding: 96px 0">
      <a-spin size="large" />
    </div>

    <a-card v-else size="small">
      <div class="hero">
        <a-avatar shape="square" :size="48" :style="{ background: 'rgba(24,128,56,0.14)', color: '#188038' }">
          <template #icon><TableOutlined /></template>
        </a-avatar>
        <div>
          <div class="hero-title">{{ $t('GSheets_Hero_Title') }}</div>
          <div class="muted">{{ $t('GSheets_Hero_Desc') }}</div>
        </div>
      </div>

      <a-tabs v-model:activeKey="tab">
        <!-- ============================ Connection ============================ -->
        <a-tab-pane key="connection" :tab="$t('Connection')">
          <a-row :gutter="[16, 16]">
            <a-col :xs="24" :lg="12">
              <a-card size="small" :title="$t('GSheets_OAuth_Credentials')" style="height: 100%">
                <a-form layout="vertical">
                  <a-form-item :label="$t('Client_ID')">
                    <a-input v-model:value="form.client_id" placeholder="…apps.googleusercontent.com" />
                  </a-form-item>
                  <a-form-item :label="$t('Client_Secret')">
                    <a-input-password
                      v-model:value="form.client_secret"
                      :placeholder="state.has_client_secret ? $t('Salla_Saved_Paste_New_Value') : $t('GSheets_Client_Secret_Ph')"
                      autocomplete="off"
                    />
                  </a-form-item>
                  <a-space direction="vertical" size="small" style="margin-bottom: 16px">
                    <a-space align="center">
                      <a-switch v-model:checked="form.auto_export" />
                      <span>{{ $t('GSheets_Auto_Export') }} <span class="muted">{{ $t('GSheets_Auto_Export_Hint') }}</span></span>
                    </a-space>
                    <a-space align="center">
                      <a-switch v-model:checked="form.enabled" />
                      <span>{{ $t('GSheets_Enable') }}</span>
                    </a-space>
                  </a-space>
                  <div>
                    <a-button type="primary" :loading="saving" @click="save">{{ $t('Save') }}</a-button>
                  </div>
                </a-form>
              </a-card>
            </a-col>

            <a-col :xs="24" :lg="12">
              <a-card size="small" :title="$t('GSheets_Account_Spreadsheet')" style="margin-bottom: 16px">
                <a-space wrap style="margin-bottom: 16px">
                  <a-button type="primary" :disabled="!state.client_id || !state.has_client_secret" @click="connect">
                    <template #icon><LinkOutlined /></template>
                    {{ state.connected ? $t('GSheets_Reconnect_Account') : $t('GSheets_Connect_Account') }}
                  </a-button>
                  <a-button :disabled="!state.connected" :loading="testing" @click="testConnection">{{ $t('Test_Connection') }}</a-button>
                  <a-popconfirm
                    v-if="state.connected"
                    :title="$t('GSheets_Disconnect_Confirm')"
                    :ok-text="$t('Yes')" :cancel-text="$t('No')" @confirm="disconnect"
                  >
                    <a-button danger>{{ $t('Disconnect') }}</a-button>
                  </a-popconfirm>
                </a-space>

                <a-form layout="vertical">
                  <a-form-item :label="$t('GSheets_Target_Spreadsheet')">
                    <a-input-group compact style="display: flex">
                      <a-input v-model:value="form.spreadsheet" :placeholder="$t('GSheets_Spreadsheet_Ph')" style="flex: 1" />
                      <a-button :disabled="!state.connected" :loading="creating" @click="createSpreadsheet">{{ $t('GSheets_Create_New') }}</a-button>
                    </a-input-group>
                    <div v-if="state.spreadsheet_url" style="margin-top: 6px">
                      <a :href="state.spreadsheet_url" target="_blank" rel="noopener">{{ $t('GSheets_Open_Spreadsheet') }} ↗</a>
                      <span class="muted" style="margin-left: 8px" v-if="state.last_sync_at">{{ $t('GSheets_Last_Export', { date: date(state.last_sync_at) }) }}</span>
                    </div>
                  </a-form-item>
                </a-form>
              </a-card>

              <a-card size="small" :title="$t('GSheets_Redirect_Uri_Title')">
                <a-input-group compact style="display: flex">
                  <a-input :value="state.callback_url" readonly style="flex: 1" />
                  <a-button @click="copy(state.callback_url)"><CopyOutlined /></a-button>
                </a-input-group>
              </a-card>
            </a-col>
          </a-row>
        </a-tab-pane>

        <!-- ============================ Export ============================ -->
        <a-tab-pane key="export" :tab="$t('Export')" :disabled="!state.connected || !state.spreadsheet_id">
          <StatsRow :stats="stats" :items="[['sales_total', $t('Sales')], ['products_total', $t('Products')], ['customers_total', $t('Customers')]]" />
          <p class="muted" style="max-width: 760px">{{ $t('GSheets_Export_Desc') }}</p>
          <a-row :gutter="[16, 16]" style="margin-bottom: 16px">
            <a-col v-for="job in exportJobs" :key="job.key" :xs="24" :md="8">
              <a-card size="small" :title="job.title" style="height: 100%">
                <p class="muted" style="min-height: 40px">{{ job.desc }}</p>
                <a-button type="primary" :disabled="sync.running.value" @click="runSync(job.url, {}, 'push')">
                  <template #icon><UploadOutlined /></template>{{ job.button }}
                </a-button>
              </a-card>
            </a-col>
          </a-row>
          <a-button v-if="sync.running.value" danger style="margin-bottom: 12px" @click="sync.cancel()">{{ $t('Cancel') }}</a-button>
          <SyncProgress :sync="sync" />
        </a-tab-pane>

        <!-- ============================ Logs ============================ -->
        <a-tab-pane key="logs" :tab="$t('Logs')">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 8px; flex-wrap: wrap">
            <a-select
              v-model:value="logLevel" style="min-width: 160px" :options="logLevelOptions" @change="loadLogs(1)"
            />
            <a-popconfirm :title="$t('Salla_Clear_Logs_Confirm', { name: 'Google Sheets' })" :ok-text="$t('Yes')" :cancel-text="$t('No')" @confirm="clearLogs">
              <a-button danger size="small">{{ $t('Salla_Clear_Logs') }}</a-button>
            </a-popconfirm>
          </div>
          <a-table
            size="small" row-key="id" :data-source="logs.data" :columns="logColumns"
            :pagination="{ current: logs.current_page, pageSize: logs.per_page, total: logs.total, showSizeChanger: false }"
            :scroll="{ x: 900 }" @change="p => loadLogs(p.current)"
          >
            <template #bodyCell="{ column, record }">
              <template v-if="column.key === 'level'">
                <a-tag :color="record.level === 'error' ? 'error' : record.level === 'warning' ? 'warning' : 'default'">{{ levelLabel(record.level) }}</a-tag>
              </template>
              <template v-else-if="column.key === 'created_at'">{{ date(record.created_at) }}</template>
            </template>
          </a-table>
        </a-tab-pane>

        <!-- ============================ Guide ============================ -->
        <a-tab-pane key="guide" :tab="$t('Guide')">
          <a-card size="small" style="max-width: 860px">
            <ol class="guide">
              <i18n-t keypath="GSheets_Guide_1" tag="li">
                <template #link><a href="https://console.cloud.google.com" target="_blank" rel="noopener">console.cloud.google.com</a></template>
                <template #api><strong>Google Sheets API</strong></template>
                <template #menu>APIs &amp; Services → Library</template>
              </i18n-t>
              <i18n-t keypath="GSheets_Guide_2" tag="li">
                <template #consent><strong>OAuth consent screen</strong></template>
                <template #publish><strong>{{ $t('GSheets_Publish') }}</strong></template>
              </i18n-t>
              <i18n-t keypath="GSheets_Guide_3" tag="li">
                <template #clientId><strong>OAuth client ID</strong></template>
              </i18n-t>
              <i18n-t keypath="GSheets_Guide_4" tag="li">
                <template #connect><strong>{{ $t('GSheets_Connect_Account') }}</strong></template>
              </i18n-t>
              <li>{{ $t('GSheets_Guide_5') }}</li>
            </ol>
            <a-alert
              type="info" show-icon style="margin-top: 8px"
              :message="$t('GSheets_Snapshots_Title')"
              :description="$t('GSheets_Snapshots_Desc')"
            />
          </a-card>
        </a-tab-pane>
      </a-tabs>
    </a-card>
  </div>
</template>

<script setup>
/**
 * Google Sheets integration page. Endpoints (all under /api, feature-gated by
 * tenant.feature:google_sheets): GET/POST google-sheets/settings,
 * POST google-sheets/{disconnect|test-connection|create-spreadsheet},
 * GET google-sheets/stats, POST google-sheets/export/{sales|products|customers},
 * GET/DELETE google-sheets/logs. OAuth over web routes:
 * /google-sheets/connect -> Google -> /google-sheets/callback -> ?connected=1 | ?gs_error=...
 */
import { ref, computed, h, defineComponent, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { message, Tag as ATagC, Spin as ASpinC } from 'ant-design-vue';
import {
  TableOutlined, UploadOutlined, LinkOutlined, CopyOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import { useFormat } from '../../composables/useFormat';
import { useBatchSync, COUNTER_LABELS } from '../../composables/useBatchSync';
import http from '../../lib/http';
import { i18n, t } from '../../i18n';

/** Translate with params; English fallback (params filled in) while the key is missing. */
function tp(key, fallback, params = {}) {
  const out = i18n.global.t(key, params);
  if (out !== key) return out;
  return Object.entries(params).reduce((s, [k, v]) => s.split(`{${k}}`).join(v), fallback);
}

const { date } = useFormat();
const route = useRoute();

const isLoading = ref(true);
const saving = ref(false);
const testing = ref(false);
const creating = ref(false);
const tab = ref('connection');
const state = ref({ connected: false, enabled: false });
const form = ref({ enabled: false, client_id: '', client_secret: '', spreadsheet: '', auto_export: false });
const stats = ref({});
const logs = ref({ data: [], current_page: 1, per_page: 20, total: 0 });
const logLevel = ref('');

const sync = useBatchSync();

const exportJobs = computed(() => [
  {
    key: 'sales', url: 'google-sheets/export/sales',
    title: t('Sales', 'Sales'),
    button: t('GSheets_Export_Sales', 'Export sales'),
    desc: t('GSheets_Sales_Desc', 'Every sale with customer, totals, payment status and warehouse.'),
  },
  {
    key: 'products', url: 'google-sheets/export/products',
    title: t('Products', 'Products'),
    button: t('GSheets_Export_Products', 'Export products'),
    desc: t('GSheets_Products_Desc', 'The full catalog with prices, costs and current stock quantities.'),
  },
  {
    key: 'customers', url: 'google-sheets/export/customers',
    title: t('Customers', 'Customers'),
    button: t('GSheets_Export_Customers', 'Export customers'),
    desc: t('GSheets_Customers_Desc', 'All customers with their contact details.'),
  },
]);

const logLevelOptions = computed(() => [
  { value: '', label: t('Salla_All_Levels', 'All levels') },
  { value: 'info', label: t('Info', 'Info') },
  { value: 'warning', label: t('Warning', 'Warning') },
  { value: 'error', label: t('Salla_Error', 'Error') },
]);

const levelLabel = v => (logLevelOptions.value.find(o => o.value && o.value === v) || {}).label || v;

const logColumns = computed(() => [
  { title: t('ID', 'ID'), dataIndex: 'id', width: 70 },
  { title: t('Action', 'Action'), dataIndex: 'action', width: 180 },
  { title: t('Level', 'Level'), key: 'level', width: 100 },
  { title: t('Message', 'Message'), dataIndex: 'message', ellipsis: true },
  { title: t('Date', 'Date'), key: 'created_at', width: 170 },
]);

async function load() {
  isLoading.value = true;
  try {
    state.value = await http.get('google-sheets/settings');
    form.value = {
      enabled: !!state.value.enabled,
      client_id: state.value.client_id || '',
      client_secret: '',
      spreadsheet: state.value.spreadsheet_id || '',
      auto_export: !!state.value.auto_export,
    };
    if (state.value.connected) loadStats();
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    isLoading.value = false;
  }
}

async function loadStats() {
  try {
    stats.value = await http.get('google-sheets/stats');
  } catch (e) { /* tiles show em-dashes */ }
}

async function save() {
  saving.value = true;
  try {
    const payload = {
      enabled: !!form.value.enabled,
      client_id: (form.value.client_id || '').trim() || null,
      spreadsheet: (form.value.spreadsheet || '').trim() || null,
      auto_export: !!form.value.auto_export,
    };
    // The secret is write-only: send it only when the admin typed a new value.
    if (form.value.client_secret) payload.client_secret = form.value.client_secret.trim();
    await http.post('google-sheets/settings', payload);
    message.success(t('Successfully_Updated'));
    await load();
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    saving.value = false;
  }
}

function connect() {
  window.location.href = state.value.connect_url || '/google-sheets/connect';
}

async function testConnection() {
  testing.value = true;
  try {
    const res = await http.post('google-sheets/test-connection');
    message.success(res?.spreadsheet_title
      ? tp('GSheets_Connected_Spreadsheet', 'Connected — spreadsheet: {title}', { title: res.spreadsheet_title })
      : tp('Salla_Connected_To', 'Connected to {name}', { name: 'Google' }));
  } catch (e) {
    message.error(e?.data?.error || e?.data?.message || t('Salla_Connection_Test_Failed', 'Connection test failed'));
  } finally {
    testing.value = false;
  }
}

async function disconnect() {
  try {
    await http.post('google-sheets/disconnect');
    message.success(t('Disconnected', 'Disconnected'));
    await load();
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  }
}

async function createSpreadsheet() {
  creating.value = true;
  try {
    const res = await http.post('google-sheets/create-spreadsheet');
    message.success(t('GSheets_Spreadsheet_Created', 'Spreadsheet created.'));
    form.value.spreadsheet = res.spreadsheet_id;
    await load();
  } catch (e) {
    message.error(e?.data?.error || e?.data?.message || t('InvalidData'));
  } finally {
    creating.value = false;
  }
}

async function runSync(url, params, kind) {
  await sync.run(url, params, kind);
  load();
}

async function loadLogs(page = 1) {
  try {
    const query = 'google-sheets/logs?per_page=20&page=' + page + (logLevel.value ? '&level=' + logLevel.value : '');
    logs.value = await http.get(query);
  } catch (e) { /* table stays empty */ }
}

async function clearLogs() {
  try {
    await http.delete('google-sheets/logs');
    loadLogs(1);
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  }
}

async function copy(text) {
  try {
    await navigator.clipboard.writeText(text || '');
    message.success(t('Copied'));
  } catch (e) {
    message.error(t('InvalidData'));
  }
}

onMounted(() => {
  load();
  loadLogs(1);
  if (route.query.connected === '1') message.success(t('GSheets_Account_Connected', 'Google account connected.'));
  if (route.query.gs_error) message.error(String(route.query.gs_error));
});

// ---- small local render components (same pattern as SallaSettings.vue) ----

const tileStyle = {
  border: '1px solid rgba(128,128,128,0.2)', borderRadius: '10px',
  padding: '10px 18px', minWidth: '130px', textAlign: 'center',
};

const StatsRow = defineComponent({
  props: { stats: Object, items: Array },
  setup(p) {
    return () => h(
      'div',
      { style: { display: 'flex', gap: '12px', flexWrap: 'wrap', marginBottom: '16px' } },
      (p.items || []).map(([key, label]) => h('div', { style: tileStyle, key }, [
        h('div', { style: { fontSize: '20px', fontWeight: 600 } }, p.stats?.[key] ?? '—'),
        h('div', { style: { fontSize: '12px', opacity: 0.6 } }, label),
      ]))
    );
  },
});

const SyncProgress = defineComponent({
  props: { sync: Object },
  setup(p) {
    return () => {
      const s = p.sync;
      if (!s || (!s.hasCounters.value && !s.running.value)) return null;
      const pills = Object.entries(s.counters.value).map(([k, v]) =>
        h(ATagC, { key: k, color: k === 'failed' && v ? 'error' : 'default' }, () => `${COUNTER_LABELS[k] || k}: ${v}`));
      const rows = [h('div', { style: { display: 'flex', gap: '6px', flexWrap: 'wrap', alignItems: 'center' } }, [
        ...(s.running.value ? [h(ASpinC, { size: 'small', style: { marginRight: '6px' } })] : []),
        ...pills,
      ])];
      if (s.batchErrors.value.length) {
        rows.push(h('div', { style: { marginTop: '10px', fontSize: '12px', maxHeight: '160px', overflow: 'auto' } },
          s.batchErrors.value.map((err, i) => h('div', { key: i, style: { opacity: 0.75 } }, JSON.stringify(err)))));
      }
      return h('div', {
        style: { border: '1px dashed rgba(128,128,128,0.35)', borderRadius: '10px', padding: '12px 14px' },
      }, rows);
    };
  },
});
</script>

<style scoped>
.hero {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 16px;
  padding-bottom: 14px;
  border-bottom: 1px solid rgba(128, 128, 128, 0.15);
}
.hero-title {
  font-weight: 600;
  font-size: 16px;
}
.muted {
  opacity: 0.65;
  font-size: 13px;
}
.guide {
  padding-left: 20px;
  line-height: 2;
  margin: 0 0 12px;
}
</style>
