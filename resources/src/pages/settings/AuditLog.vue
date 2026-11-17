<template>
  <div>
    <ReportPage
      :title="$t('Set_Audit_Log')"
      :breadcrumb="[$t('Settings'), $t('Set_Audit_Log')]"
      :crud="crud"
      :columns="columns"
      row-key="id"
      export-endpoint="audit-logs"
      :export-params="filterParams"
      export-rows-key="logs"
    >
      <template #actions>
        <a-button @click="openSettings">
          <template #icon><SettingOutlined /></template>
          {{ $t('Settings') }}
        </a-button>
      </template>

      <template #chart>
        <a-alert
          v-if="settings.loaded && !settings.enabled"
          type="warning"
          show-icon
          style="margin-bottom: 16px"
          :message="tf('Audit_Disabled_Banner', 'Recording is turned off — changes are not being logged.')"
        />

        <a-row :gutter="[16, 16]" style="margin-bottom: 16px">
          <a-col v-for="tile in tiles" :key="tile.key" :xs="12" :lg="6">
            <a-card size="small" class="stat-card">
              <a-statistic :title="tile.label" :value="tile.value" :value-style="tile.style" />
            </a-card>
          </a-col>
        </a-row>

        <!-- Narrowed-by-link filters (record history / one request) -->
        <div v-if="recordId || batchId" class="scope-tags">
          <a-tag v-if="recordId" closable color="purple" @close="clearRecord">
            {{ tf('Audit_Record_History', 'Record history') }}:
            {{ moduleFilter ? humanize(shortName(moduleFilter)) : '' }} #{{ recordId }}
          </a-tag>
          <a-tag v-if="batchId" closable color="blue" @close="clearBatch">
            {{ tf('Audit_Same_Request', 'Everything from one request') }}
          </a-tag>
        </div>
      </template>

      <template #filters>
        <DateRangePicker v-model:value="range" allow-clear @change="crud.reload()" />
        <a-select
          v-model:value="userFilter"
          allow-clear
          show-search
          option-filter-prop="label"
          :placeholder="$t('User')"
          :options="userOptions"
          @change="crud.reload()"
        />
        <a-select
          v-model:value="eventFilter"
          allow-clear
          :placeholder="$t('Action')"
          :options="eventOptions"
          @change="crud.reload()"
        />
        <a-select
          v-model:value="moduleFilter"
          allow-clear
          show-search
          option-filter-prop="label"
          :placeholder="tf('Audit_Module', 'Module')"
          :options="moduleOptions"
          @change="crud.reload()"
        />
      </template>

      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'created_at'">{{ dateTime(record.created_at) }}</template>
        <template v-else-if="column.key === 'user_name'">
          <span v-if="record.user_name">{{ record.user_name }}</span>
          <a-typography-text v-else type="secondary">{{ tf('Audit_System', 'System') }}</a-typography-text>
        </template>
        <template v-else-if="column.key === 'event'">
          <a-tag :color="eventColor(record.event)" style="margin: 0">{{ eventLabel(record.event) }}</a-tag>
        </template>
        <template v-else-if="column.key === 'record'">
          <template v-if="record.module">
            <a-typography-text type="secondary">{{ humanize(record.module) }}</a-typography-text>
            <a v-if="record.auditable_id" class="record-link" @click="showRecordHistory(record)">
              #{{ record.auditable_id }}
            </a>
            <div v-if="record.auditable_label" class="record-label">{{ record.auditable_label }}</div>
          </template>
          <span v-else-if="record.auditable_label">{{ record.auditable_label }}</span>
          <span v-else>—</span>
        </template>
        <template v-else-if="column.key === 'changes'">
          <span v-if="!record.changed_fields.length">—</span>
          <span v-else class="field-list">
            {{ record.changed_fields.slice(0, 3).map(humanize).join(', ') }}
            <a-typography-text v-if="record.changed_fields.length > 3" type="secondary">
              {{ $t('Audit_More_Fields', { n: record.changed_fields.length - 3 }) }}
            </a-typography-text>
          </span>
        </template>
        <template v-else-if="column.key === 'actions'">
          <a-button size="small" @click="openEntry(record)">
            <template #icon><EyeOutlined /></template>
            {{ $t('View') }}
          </a-button>
        </template>
      </template>
    </ReportPage>

    <!-- Entry details: before / after per field + request context -->
    <a-drawer
      v-model:open="entryOpen"
      :title="tf('Audit_Entry_Details', 'Change details')"
      placement="right"
      :width="drawerWidth"
    >
      <template v-if="entry">
        <a-descriptions :column="1" size="small" bordered>
          <a-descriptions-item :label="$t('Action')">
            <a-tag :color="eventColor(entry.event)" style="margin: 0">{{ eventLabel(entry.event) }}</a-tag>
          </a-descriptions-item>
          <a-descriptions-item v-if="entry.module" :label="tf('Audit_Record', 'Record')">
            {{ humanize(entry.module) }}<template v-if="entry.auditable_id"> #{{ entry.auditable_id }}</template>
            <template v-if="entry.auditable_label"> — {{ entry.auditable_label }}</template>
          </a-descriptions-item>
          <a-descriptions-item :label="$t('User')">{{ entry.user_name || tf('Audit_System', 'System') }}</a-descriptions-item>
          <a-descriptions-item :label="$t('Date')">{{ dateTime(entry.created_at) }}</a-descriptions-item>
          <a-descriptions-item v-if="entry.ip_address" :label="$t('IP_Address')">{{ entry.ip_address }}</a-descriptions-item>
          <a-descriptions-item v-if="entry.url" :label="tf('Audit_Request', 'Request')">
            <code>{{ entry.method }} /{{ entry.url }}</code>
          </a-descriptions-item>
          <a-descriptions-item v-if="entry.user_agent" :label="tf('Audit_Device', 'Device')">
            <span class="ua">{{ entry.user_agent }}</span>
          </a-descriptions-item>
        </a-descriptions>

        <h4 class="diff-title">{{ tf('Audit_Changes', 'Changes') }}</h4>
        <a-table
          v-if="diffRows.length"
          :columns="diffColumns"
          :data-source="diffRows"
          :pagination="false"
          row-key="field"
          size="small"
          bordered
          :scroll="{ x: 'max-content' }"
        >
          <template #bodyCell="{ column, record: row }">
            <template v-if="column.key === 'field'"><strong>{{ humanize(row.field) }}</strong></template>
            <template v-else-if="column.key === 'old'">
              <span v-if="row.hasOld" class="val val-old">{{ display(row.old) }}</span>
              <a-typography-text v-else type="secondary">—</a-typography-text>
            </template>
            <template v-else-if="column.key === 'new'">
              <span v-if="row.hasNew" class="val val-new">{{ display(row.new) }}</span>
              <a-typography-text v-else type="secondary">—</a-typography-text>
            </template>
          </template>
        </a-table>
        <a-empty v-else :description="tf('Audit_No_Field_Changes', 'No field values recorded for this event.')" />

        <a-button v-if="entry.batch_id" block style="margin-top: 16px" @click="showBatch(entry)">
          <template #icon><ApartmentOutlined /></template>
          {{ tf('Audit_Same_Request', 'Everything from one request') }}
        </a-button>
      </template>
    </a-drawer>

    <!-- Settings -->
    <a-modal
      v-model:open="settingsOpen"
      :title="tf('Audit_Settings_Title', 'Audit log settings')"
      :confirm-loading="savingSettings"
      :ok-text="$t('Save')"
      :cancel-text="$t('Cancel')"
      @ok="saveSettings"
    >
      <a-form layout="vertical">
        <a-form-item :label="tf('Audit_Record_Changes', 'Record changes')" :extra="tf('Audit_Record_Changes_Help', 'Track who created, changed or deleted records, permission changes and sign-ins.')">
          <a-switch v-model:checked="form.enabled" />
        </a-form-item>
        <a-form-item :label="tf('Audit_Retention_Days', 'Keep entries for (days)')" :extra="tf('Audit_Retention_Help', 'Leave empty to keep entries forever. Older entries are removed automatically once a day.')">
          <a-input-number v-model:value="form.retention_days" :min="1" :max="3650" style="width: 100%" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
/**
 * GET audit-logs → { logs, totalRows, summary, users, modules }. Filters:
 * from/to, user_id, event, module (full class or short name), record_id
 * (+ related=1 for child records), batch_id. Deep link a record's history with
 * /settings/audit-log?module=Product&record_id=5.
 * Settings: GET/PUT audit-logs/settings → { enabled, retention_days }.
 */
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { message } from 'ant-design-vue';
import { SettingOutlined, EyeOutlined, ApartmentOutlined } from '@ant-design/icons-vue';
import ReportPage from '../../components/ReportPage.vue';
import DateRangePicker from '../../components/DateRangePicker.vue';
import { useCrudTable } from '../../composables/useCrudTable';
import { useFormat } from '../../composables/useFormat';
import { t as tf } from '../../i18n';
import http from '../../lib/http';

const { t } = useI18n();
const { dateTime } = useFormat();
const route = useRoute();

/* ---------------------------------------------------------------- filters */
const range = ref(null);
const userFilter = ref(undefined);
const eventFilter = ref(undefined);
const moduleFilter = ref(route.query.module ? String(route.query.module) : undefined);
const recordId = ref(route.query.record_id ? String(route.query.record_id) : '');
const batchId = ref('');

const filterParams = () => ({
  ...(range.value?.[0] ? { from: range.value[0].format('YYYY-MM-DD') } : {}),
  ...(range.value?.[1] ? { to: range.value[1].format('YYYY-MM-DD') } : {}),
  ...(userFilter.value ? { user_id: userFilter.value } : {}),
  ...(eventFilter.value ? { event: eventFilter.value } : {}),
  ...(moduleFilter.value ? { module: moduleFilter.value } : {}),
  // A record's history includes its children (a sale's payments…).
  ...(recordId.value ? { record_id: recordId.value, related: 1 } : {}),
  ...(batchId.value ? { batch_id: batchId.value } : {}),
});

const crud = useCrudTable('audit-logs', {
  rowsKey: 'logs',
  sortField: 'id',
  sortType: 'desc',
  params: filterParams,
});

const payload = computed(() => crud.payload.value || {});
const userOptions = computed(() => (payload.value.users || []).map(u => ({ value: u.id, label: u.name || `#${u.id}` })));
const moduleOptions = computed(() => (payload.value.modules || []).map(m => ({ value: m.value, label: humanize(m.label) })));

const EVENTS = {
  created: { color: 'success', fallback: 'Created' },
  updated: { color: 'processing', fallback: 'Updated' },
  deleted: { color: 'error', fallback: 'Deleted' },
  restored: { color: 'cyan', fallback: 'Restored' },
  bulk_updated: { color: 'orange', fallback: 'Bulk update' },
  bulk_deleted: { color: 'volcano', fallback: 'Bulk delete' },
  permissions_changed: { color: 'purple', fallback: 'Permissions changed' },
  login: { color: 'default', fallback: 'Signed in' },
  logout: { color: 'default', fallback: 'Signed out' },
  login_failed: { color: 'warning', fallback: 'Failed sign-in' },
};
const eventOptions = computed(() => Object.keys(EVENTS).map(k => ({ value: k, label: eventLabel(k) })));
const eventLabel = e => tf(`Audit_Event_${e}`, EVENTS[e]?.fallback || humanize(e));
const eventColor = e => EVENTS[e]?.color || 'default';

/* ---------------------------------------------------------------- summary */
const tiles = computed(() => {
  const s = payload.value.summary || {};
  return [
    { key: 'total', label: tf('Audit_Total_Events', 'Events'), value: Number(s.total) || 0 },
    { key: 'today', label: tf('Audit_Today', 'Today'), value: Number(s.today) || 0 },
    { key: 'deletions', label: tf('Audit_Deletions', 'Deletions'), value: Number(s.deletions) || 0,
      style: Number(s.deletions) ? { color: '#f43f5e' } : undefined },
    { key: 'users', label: tf('Audit_Active_Users', 'Users'), value: Number(s.users) || 0 },
  ];
});

/* ---------------------------------------------------------------- table */
const columns = computed(() => [
  { title: t('Date'), key: 'created_at', dataIndex: 'created_at', sorter: true, exportValue: r => dateTime(r.created_at) },
  { title: t('User'), key: 'user_name', dataIndex: 'user_name', sorter: true, exportValue: r => r.user_name || tf('Audit_System', 'System') },
  { title: t('Action'), key: 'event', dataIndex: 'event', sorter: true, exportValue: r => eventLabel(r.event) },
  {
    title: tf('Audit_Record', 'Record'), key: 'record', dataIndex: 'auditable_label', sorter: false,
    exportValue: r => [r.module ? humanize(r.module) : '', r.auditable_id ? `#${r.auditable_id}` : '', r.auditable_label || ''].filter(Boolean).join(' '),
  },
  { title: tf('Audit_Changes', 'Changes'), key: 'changes', dataIndex: 'changed_fields', sorter: false, exportValue: r => r.changed_fields.map(humanize).join(', ') },
  { title: t('IP_Address'), key: 'ip_address', dataIndex: 'ip_address', sorter: false, defaultHidden: true, exportValue: r => r.ip_address || '' },
  { title: '', key: 'actions', width: 90 },
]);

function showRecordHistory(row) {
  moduleFilter.value = row.auditable_type;
  recordId.value = String(row.auditable_id);
  crud.reload();
}
function clearRecord() {
  recordId.value = '';
  crud.reload();
}
function showBatch(row) {
  batchId.value = row.batch_id;
  entryOpen.value = false;
  crud.reload();
}
function clearBatch() {
  batchId.value = '';
  crud.reload();
}

/* ---------------------------------------------------------------- details */
const entryOpen = ref(false);
const entry = ref(null);
const drawerWidth = computed(() => Math.min(640, window.innerWidth));

function openEntry(row) {
  entry.value = row;
  entryOpen.value = true;
}

const diffColumns = computed(() => [
  { title: tf('Audit_Field', 'Field'), key: 'field', width: 170 },
  { title: tf('Audit_Before', 'Before'), key: 'old' },
  { title: tf('Audit_After', 'After'), key: 'new' },
]);

const diffRows = computed(() => {
  if (!entry.value) return [];
  const oldV = entry.value.old_values || {};
  const newV = entry.value.new_values || {};
  return entry.value.changed_fields.map(field => ({
    field,
    old: oldV[field],
    new: newV[field],
    hasOld: Object.prototype.hasOwnProperty.call(oldV, field),
    hasNew: Object.prototype.hasOwnProperty.call(newV, field),
  }));
});

function display(v) {
  if (v === null || v === undefined || v === '') return '∅';
  if (v === true) return t('Yes');
  if (v === false) return t('No');
  return String(v);
}

/** GrandTotal → Grand Total, payment_statut → Payment statut, PaymentSaleReturns → Payment Sale Returns */
function humanize(key) {
  const s = String(key ?? '')
    .replace(/_/g, ' ')
    .replace(/([a-z\d])([A-Z])/g, '$1 $2')
    .trim();
  return s.charAt(0).toUpperCase() + s.slice(1);
}
const shortName = type => String(type).split('\\').pop();

/* ---------------------------------------------------------------- settings */
const settings = reactive({ loaded: false, enabled: true, retention_days: null });
const settingsOpen = ref(false);
const savingSettings = ref(false);
const form = reactive({ enabled: true, retention_days: null });

async function loadSettings() {
  try {
    const data = await http.get('audit-logs/settings');
    settings.enabled = !!data.enabled;
    settings.retention_days = data.retention_days ?? null;
    settings.loaded = true;
  } catch (e) {
    // the list still works without its settings
  }
}

function openSettings() {
  form.enabled = settings.enabled;
  form.retention_days = settings.retention_days;
  settingsOpen.value = true;
}

async function saveSettings() {
  savingSettings.value = true;
  try {
    await http.put('audit-logs/settings', {
      enabled: form.enabled,
      retention_days: form.retention_days || null,
    });
    settings.enabled = form.enabled;
    settings.retention_days = form.retention_days || null;
    settingsOpen.value = false;
    message.success(t('Successfully_Updated'));
    crud.reload();
  } catch (e) {
    message.error(t('InvalidData'));
  } finally {
    savingSettings.value = false;
  }
}

onMounted(() => {
  crud.fetchRows();
  loadSettings();
});
</script>

<style scoped>
.stat-card { border-radius: 10px; }
.scope-tags { margin-bottom: 12px; }
.record-link { margin-inline-start: 4px; }
.record-label {
  max-width: 260px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.field-list {
  display: inline-block;
  max-width: 280px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: middle;
}
.diff-title { margin: 20px 0 10px; }
.val {
  display: inline-block;
  max-width: 260px;
  white-space: pre-wrap;
  word-break: break-word;
  padding: 1px 6px;
  border-radius: 4px;
}
.val-old {
  background: rgba(244, 63, 94, 0.1);
  text-decoration: line-through;
  text-decoration-color: rgba(244, 63, 94, 0.6);
}
.val-new { background: rgba(16, 185, 129, 0.12); }
.ua { font-size: 12px; word-break: break-word; }
</style>
