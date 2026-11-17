<template>
  <!-- One element, so a parent a-space gets one item, not an empty gap for the drawer. -->
  <span v-if="auth.can('audit_log')">
    <a-button @click="open">
      <template #icon><HistoryOutlined /></template>
      {{ tf('Audit_History', 'History') }}
    </a-button>

    <a-drawer
      v-model:open="visible"
      :title="tf('Audit_History', 'History')"
      placement="right"
      :width="drawerWidth"
    >
      <template #extra>
        <a-button type="link" @click="openFullLog">{{ tf('Audit_Open_Full_Log', 'Open in Audit Log') }}</a-button>
      </template>

      <a-spin :spinning="loading">
        <a-empty v-if="!loading && !entries.length" :description="tf('Audit_No_History', 'No recorded changes yet.')" />

        <a-timeline v-else>
          <a-timeline-item v-for="e in entries" :key="e.id" :color="dotColor(e.event)">
            <div class="h-head">
              <a-tag :color="eventColor(e.event)" style="margin: 0">{{ eventLabel(e.event) }}</a-tag>
              <span v-if="e.module !== mainModule" class="h-child">
                {{ humanize(e.module) }}<template v-if="e.auditable_label"> · {{ e.auditable_label }}</template>
              </span>
            </div>
            <div class="h-meta">
              {{ e.user_name || tf('Audit_System', 'System') }} · {{ dateTime(e.created_at) }}
            </div>

            <!-- Created/deleted snapshots are long — the full log has them. -->
            <ul v-if="showDiff(e)" class="h-diff">
              <li v-for="f in visibleFields(e)" :key="f">
                <span class="h-field">{{ humanize(f) }}:</span>
                <template v-if="has(e.old_values, f)">
                  <span class="val val-old">{{ display(e.old_values[f]) }}</span>
                  <span v-if="has(e.new_values, f)" class="h-arrow">→</span>
                </template>
                <span v-if="has(e.new_values, f)" class="val val-new">{{ display(e.new_values[f]) }}</span>
              </li>
              <li v-if="e.changed_fields.length > visibleFields(e).length">
                <a @click="expanded[e.id] = true">{{ $t('Audit_More_Fields', { n: e.changed_fields.length - visibleFields(e).length }) }}</a>
              </li>
            </ul>
          </a-timeline-item>
        </a-timeline>

        <a-button v-if="entries.length < total" block :loading="loadingMore" @click="loadMore">
          {{ tf('Audit_Load_More', 'Load more') }}
        </a-button>
      </a-spin>
    </a-drawer>
  </span>
</template>

<script setup>
/**
 * "History" button + drawer for a record detail page: the record's audit
 * trail (and its children — a sale's payments, a product's variants) from
 * GET audit-logs?module=&record_id=&related=1. Renders nothing without the
 * audit_log permission.
 *
 *   <AuditHistoryButton module="Sale" />            (record id = route :id)
 *   <AuditHistoryButton module="Client" :record-id="client.id" />
 */
import { ref, reactive, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { HistoryOutlined } from '@ant-design/icons-vue';
import { useAuthStore } from '../stores/auth';
import { useFormat } from '../composables/useFormat';
import { t as tf } from '../i18n';
import http from '../lib/http';

const props = defineProps({
  module: { type: String, required: true },
  recordId: { type: [Number, String], default: null },
});

const PAGE = 25;
const FIELDS_SHOWN = 6;

const { t } = useI18n();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const { dateTime } = useFormat();

const visible = ref(false);
const loading = ref(false);
const loadingMore = ref(false);
const entries = ref([]);
const total = ref(0);
const page = ref(1);
const expanded = reactive({});

const mainModule = computed(() => props.module.split('\\').pop());
const id = computed(() => props.recordId ?? route.params.id);
const drawerWidth = computed(() => Math.min(520, window.innerWidth));

async function fetchPage(p) {
  const data = await http.get('audit-logs', {
    module: props.module,
    record_id: id.value,
    related: 1,
    page: p,
    limit: PAGE,
    SortField: 'id',
    SortType: 'desc',
  });
  total.value = Number(data?.totalRows) || 0;
  return data?.logs || [];
}

async function open() {
  visible.value = true;
  loading.value = true;
  page.value = 1;
  try {
    entries.value = await fetchPage(1);
  } catch (e) {
    entries.value = [];
  } finally {
    loading.value = false;
  }
}

async function loadMore() {
  loadingMore.value = true;
  try {
    const next = await fetchPage(page.value + 1);
    page.value += 1;
    entries.value = entries.value.concat(next);
  } finally {
    loadingMore.value = false;
  }
}

function openFullLog() {
  visible.value = false;
  router.push({ path: '/settings/audit-log', query: { module: mainModule.value, record_id: id.value } });
}

const EVENTS = {
  created: { color: 'success', fallback: 'Created' },
  updated: { color: 'processing', fallback: 'Updated' },
  deleted: { color: 'error', fallback: 'Deleted' },
  restored: { color: 'cyan', fallback: 'Restored' },
  bulk_updated: { color: 'orange', fallback: 'Bulk update' },
  bulk_deleted: { color: 'volcano', fallback: 'Bulk delete' },
  permissions_changed: { color: 'purple', fallback: 'Permissions changed' },
};
const eventLabel = e => tf(`Audit_Event_${e}`, EVENTS[e]?.fallback || humanize(e));
const eventColor = e => EVENTS[e]?.color || 'default';
const dotColor = e => ({ created: 'green', deleted: 'red', restored: 'cyan' }[e] || 'blue');

const showDiff = e => !['created', 'deleted'].includes(e.event) && e.changed_fields.length > 0;
const visibleFields = e => (expanded[e.id] ? e.changed_fields : e.changed_fields.slice(0, FIELDS_SHOWN));
const has = (obj, key) => !!obj && Object.prototype.hasOwnProperty.call(obj, key);

function display(v) {
  if (v === null || v === undefined || v === '') return '∅';
  if (v === true) return t('Yes');
  if (v === false) return t('No');
  return String(v);
}

function humanize(key) {
  const s = String(key ?? '')
    .replace(/_/g, ' ')
    .replace(/([a-z\d])([A-Z])/g, '$1 $2')
    .trim();
  return s.charAt(0).toUpperCase() + s.slice(1);
}
</script>

<style scoped>
.h-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.h-child { font-weight: 500; }
.h-meta {
  margin-top: 4px;
  font-size: 12px;
  opacity: 0.65;
}
.h-diff {
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
  font-size: 13px;
}
.h-diff li + li { margin-top: 4px; }
.h-field { margin-inline-end: 6px; opacity: 0.75; }
.h-arrow { margin: 0 6px; opacity: 0.6; }
.val {
  display: inline-block;
  max-width: 100%;
  white-space: pre-wrap;
  word-break: break-word;
  padding: 0 5px;
  border-radius: 4px;
}
.val-old {
  background: rgba(244, 63, 94, 0.1);
  text-decoration: line-through;
  text-decoration-color: rgba(244, 63, 94, 0.6);
}
.val-new { background: rgba(16, 185, 129, 0.12); }
</style>
