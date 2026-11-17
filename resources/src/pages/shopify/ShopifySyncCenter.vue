<template>
  <div class="page">
    <PageHeader
      :title="$t('Sync Centre')"
      :subtitle="$t('Shopify_Sync_Subtitle')"
      :breadcrumb="['Shopify', $t('Sync Centre')]"
    >
      <template #actions>
        <a-button @click="$router.push('/shopify/logs')">
          <template #icon><FileTextOutlined /></template>
          {{ $t('Logs') }}
        </a-button>
      </template>
    </PageHeader>

    <a-card size="small" style="margin-bottom: 16px">
      <div class="toolbar">
        <a-select
          v-model:value="storeId" class="tb-store" show-search option-filter-prop="label"
          :options="storeOptions" :placeholder="$t('Shopify_Select_A_Store')" @change="onStoreChange"
        />
        <a-checkbox v-model:checked="dryRun" class="tb-check">
          {{ $t('Shopify_Dry_Run') }}
        </a-checkbox>
        <span class="tb-hint">{{ $t('Shopify_Dry_Run_Hint') }}</span>
      </div>
    </a-card>

    <a-alert
      v-if="!storeOptions.length"
      type="info" show-icon
      :message="$t('Shopify_No_Store_To_Sync')"
      :description="$t('Shopify_Connect_First')"
    >
      <template #action>
        <a-button size="small" type="primary" @click="$router.push('/shopify/stores')">{{ $t('Shopify_Connect_A_Store') }}</a-button>
      </template>
    </a-alert>

    <template v-else-if="store">
      <a-alert
        v-if="blockers.length"
        type="warning" show-icon style="margin-bottom: 16px"
        :message="$t('Shopify_Not_Ready')"
      >
        <template #description>
          <ul class="blockers">
            <li v-for="b in blockers" :key="b">{{ b }}</li>
          </ul>
          <a-button size="small" @click="$router.push(`/shopify/stores/${store.id}`)">{{ $t('Shopify_Open_Store_Settings') }}</a-button>
        </template>
      </a-alert>

      <div class="entity-grid">
        <a-card
          v-for="entity in entities" :key="entity.value"
          size="small" class="entity-card" :class="{ 'entity-card--off': !store[`sync_${entity.value}`] }"
        >
          <div class="ec-head">
            <span class="ec-ic"><component :is="entity.icon" :size="18" /></span>
            <span class="ec-title">
              <strong>{{ entity.label }}</strong>
              <span class="ec-hint">{{ entity.hint }}</span>
            </span>
            <a-tag v-if="!store[`sync_${entity.value}`]" class="ec-off">{{ $t('Shopify_Off') }}</a-tag>
          </div>

          <!-- live progress, or the last result -->
          <div class="ec-status">
            <template v-if="running[entity.value]">
              <a-progress
                :percent="running[entity.value].percentage || 0"
                :status="running[entity.value].status === 'failed' ? 'exception' : 'active'"
                size="small"
              />
              <div class="ec-stage">
                {{ stageLabel(running[entity.value].stage) }} ·
                {{ running[entity.value].processed }}/{{ running[entity.value].total || '?' }}
              </div>
            </template>
            <template v-else-if="latest[entity.value]">
              <div class="ec-last">
                <a-tag :color="optionOf(RUN_STATUSES, latest[entity.value].status).color">
                  {{ labelOf(RUN_STATUSES, latest[entity.value].status) }}
                </a-tag>
                <a-tag v-if="latest[entity.value].dry_run">{{ $t('Shopify_Dry_Run') }}</a-tag>
                <span class="ec-counts">
                  {{ $t('Shopify_Counts_Long', {
                    created: latest[entity.value].created,
                    updated: latest[entity.value].updated,
                    skipped: latest[entity.value].skipped,
                  }) }}<template v-if="latest[entity.value].failed">
                    · <span class="bad">{{ $t('Shopify_N_Failed', { n: latest[entity.value].failed }) }}</span>
                  </template>
                </span>
              </div>
              <div v-if="latest[entity.value].last_error" class="ec-error">
                {{ latest[entity.value].last_error }}
              </div>
              <div v-else-if="latest[entity.value].finished_at" class="ec-when">
                {{ dateTime(latest[entity.value].finished_at) }}
              </div>
            </template>
            <div v-else class="ec-never">{{ $t('Shopify_Never_Run') }}</div>
          </div>

          <div class="ec-actions">
            <template v-if="running[entity.value]">
              <a-button size="small" danger :loading="cancelling === entity.value" @click="cancel(entity.value)">
                {{ $t('Shopify_Stop') }}
              </a-button>
            </template>
            <template v-else>
              <a-button
                v-for="dir in entity.directions" :key="dir"
                size="small"
                :type="dir === 'pull' ? 'primary' : 'default'"
                :disabled="!canRun(entity)"
                :loading="starting === `${entity.value}:${dir}`"
                @click="start(entity.value, dir)"
              >
                <template #icon>
                  <component :is="dir === 'push' ? UploadOutlined : DownloadOutlined" />
                </template>
                {{ directionShort(dir) }}
              </a-button>
            </template>
          </div>

          <div v-if="entity.value === 'orders'" class="ec-extra">
            <a-date-picker
              v-model:value="since" size="small" value-format="YYYY-MM-DD"
              :placeholder="$t('Shopify_Orders_Since')" style="width: 100%"
            />
          </div>
        </a-card>
      </div>

      <a-card size="small" :title="$t('Shopify_Recent_Runs')" style="margin-top: 16px">
        <a-table
          size="small" :columns="runColumns" :data-source="runs" row-key="id"
          :loading="loadingRuns" :pagination="{ pageSize: 10 }" :scroll="{ x: 900 }"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'entity'">
              {{ entityOf(record.entity).label }}
            </template>
            <template v-else-if="column.key === 'direction'">
              <a-tag :color="optionOf(DIRECTIONS, record.direction).color">
                {{ labelOf(DIRECTIONS, record.direction) }}
              </a-tag>
            </template>
            <template v-else-if="column.key === 'status'">
              <a-space :size="4">
                <a-tag :color="optionOf(RUN_STATUSES, record.status).color">
                  {{ labelOf(RUN_STATUSES, record.status) }}
                </a-tag>
                <a-tag v-if="record.dry_run">{{ $t('Shopify_Dry') }}</a-tag>
              </a-space>
            </template>
            <template v-else-if="column.key === 'result'">
              <span class="ec-counts">
                {{ $t('Shopify_Counts_Short', { created: record.created, updated: record.updated, skipped: record.skipped }) }}
                <template v-if="record.failed"> · <span class="bad">{{ $t('Shopify_N_Fail', { n: record.failed }) }}</span></template>
              </span>
            </template>
            <template v-else-if="column.key === 'duration'">
              {{ record.duration !== null ? `${record.duration}s` : '—' }}
            </template>
            <template v-else-if="column.key === 'started_at'">
              {{ record.started_at ? dateTime(record.started_at) : '—' }}
            </template>
            <template v-else-if="column.key === 'actions'">
              <a-button type="link" size="small" @click="$router.push(`/shopify/logs?run_id=${record.id}`)">
                {{ $t('Logs') }}
              </a-button>
            </template>
          </template>
        </a-table>
      </a-card>
    </template>
  </div>
</template>

<script setup>
/**
 * Where syncs are started and watched.
 *
 * Starting a sync answers at once: the run is queued and a background worker
 * works it in slices. A poll refreshes the run row every couple of seconds —
 * that shows progress, lets Stop work, and reports the result when it ends.
 * When no worker claims the run within a few seconds (`waiting`), this page
 * carries it on itself, one short request per slice.
 */
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';
import { message } from 'ant-design-vue';
import {
  FileTextOutlined, UploadOutlined, DownloadOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import { useFormat } from '../../composables/useFormat';
import {
  ENTITIES, RUN_STATUSES, DIRECTIONS, entityOf, labelOf, optionOf, isLive,
  tr, tp, directionShort, stageText,
} from './shopifyOptions';
import http from '../../lib/http';
import { t } from '../../i18n';

const route = useRoute();
const { dateTime } = useFormat();

const stores = ref([]);
const storeId = ref(route.query.store_id ? Number(route.query.store_id) : undefined);
const dryRun = ref(false);
const since = ref(null);

const store = computed(() => stores.value.find(s => s.id === storeId.value));
const entities = computed(() => tr(ENTITIES));
const storeOptions = computed(() => stores.value.map(s => ({
  value: s.id,
  label: `${s.name} — ${s.shop_domain}`,
})));

/** Everything that must be true before a sync can do anything useful. */
const blockers = computed(() => {
  const s = store.value;
  if (!s) return [];
  const out = [];
  if (s.status !== 'connected') out.push(t('Shopify_Blocker_Not_Verified', 'The connection has not been verified — test it from the store page.'));
  if (!s.warehouse_id) out.push(t('Shopify_Blocker_No_Warehouse', 'No ERP warehouse is set, so stock and orders have nowhere to go.'));
  if (!s.location_id) out.push(t('Shopify_Blocker_No_Location', 'No Shopify location is set, so inventory cannot be matched.'));
  return out;
});

function canRun(entity) {
  const s = store.value;
  if (!s || !s[`sync_${entity.value}`]) return false;
  if (s.status !== 'connected') return false;
  // Inventory and orders are the two that genuinely cannot work without both
  // ends configured; products and customers are fine without them.
  if (['inventory', 'orders', 'fulfillments'].includes(entity.value)) {
    return !!s.warehouse_id && (entity.value !== 'inventory' || !!s.location_id);
  }
  return true;
}

// ---------------- runs ----------------

const latest = reactive({});
const running = reactive({});
const runs = ref([]);
const loadingRuns = ref(false);
const starting = ref(null);
const cancelling = ref(null);
let poller = null;

const runColumns = computed(() => [
  { title: t('Shopify_Entity', 'Entity'), key: 'entity', dataIndex: 'entity', width: 130 },
  { title: t('Direction', 'Direction'), key: 'direction', dataIndex: 'direction', width: 150 },
  { title: t('Status', 'Status'), key: 'status', dataIndex: 'status', width: 150 },
  { title: t('Zatca_Result', 'Result'), key: 'result', width: 260 },
  { title: t('Shopify_Took', 'Took'), key: 'duration', dataIndex: 'duration', width: 90, align: 'right' },
  { title: t('Rental_Started', 'Started'), key: 'started_at', dataIndex: 'started_at', width: 160 },
  { title: '', key: 'actions', width: 80, align: 'center' },
]);

async function loadLatest() {
  if (!storeId.value) return;
  try {
    const res = await http.get('shopify/sync/latest', { store_id: storeId.value });
    Object.keys(latest).forEach(k => delete latest[k]);
    Object.keys(running).forEach(k => delete running[k]);

    Object.entries(res?.latest || {}).forEach(([entity, run]) => {
      if (!run) return;
      latest[entity] = run;
      if (isLive(run.status)) running[entity] = run;
    });

    syncPoller();
  } catch (e) { /* leave the cards showing whatever they had */ }
}

async function loadRuns() {
  if (!storeId.value) return;
  loadingRuns.value = true;
  try {
    const res = await http.get('shopify/sync/runs', { store_id: storeId.value, limit: 25 });
    runs.value = res?.runs || [];
  } catch (e) {
    runs.value = [];
  } finally {
    loadingRuns.value = false;
  }
}

/** Poll only while something is actually live. */
function syncPoller() {
  const live = Object.keys(running).length > 0;
  if (live && !poller) {
    poller = setInterval(refreshRunning, 2000);
  } else if (!live && poller) {
    clearInterval(poller);
    poller = null;
  }
}

/**
 * A run we have just asked for has no id yet: the start request only answers
 * once the run is over. Pick its row up as soon as the server has created it.
 */
async function adoptStarted() {
  try {
    const res = await http.get('shopify/sync/latest', { store_id: storeId.value });
    Object.entries(res?.latest || {}).forEach(([entity, run]) => {
      // Only a card still waiting — the start request may have answered meanwhile.
      if (running[entity] && !running[entity].id && run && isLive(run.status)) running[entity] = run;
    });
  } catch (e) { /* keep the placeholder; the next tick tries again */ }
}

async function refreshRunning() {
  if (!Object.keys(running).length) {
    syncPoller();
    return;
  }

  if (Object.values(running).some(run => !run.id)) await adoptStarted();

  const entries = Object.entries(running).filter(([, run]) => run.id);

  await Promise.all(entries.map(async ([entity, run]) => {
    try {
      const res = await http.get(`shopify/sync/status/${run.id}`);
      const fresh = res?.run;
      if (!fresh) return;
      settle(entity, fresh);
      // Paused with nobody carrying it on (the page that started it was
      // closed): this page takes over.
      if (fresh.waiting && !driving[entity]) carryOn(entity, fresh);
    } catch (e) {
      delete running[entity];
    }
  }));

  syncPoller();
  if (!Object.keys(running).length) loadRuns();
}

/** The moment between two slices of a run is not worth a label of its own. */
function stageLabel(stage) {
  return stageText(!stage || stage === 'paused' || stage === 'continuing' ? 'working' : stage);
}

function settle(entity, run) {
  if (!run) return;
  const wasLive = running[entity]?.id === run.id;
  latest[entity] = run;
  if (isLive(run.status)) {
    running[entity] = run;
  } else {
    delete running[entity];
    if (wasLive) announce(run);
  }
}

/** Run ids already reported, so a run ends in one message, not two. */
const announced = new Set();

function announce(run) {
  if (!run?.id || announced.has(run.id)) return;
  announced.add(run.id);

  const label = entityOf(run.entity)?.label || run.entity;
  if (run.status === 'completed') {
    const params = { label, created: run.created, updated: run.updated, failed: run.failed };
    message.success(run.failed
      ? tp('Shopify_Run_Done_Failed', '{label}: {created} created, {updated} updated, {failed} failed', params)
      : tp('Shopify_Run_Done', '{label}: {created} created, {updated} updated', params));
  } else if (run.status === 'failed' || run.status === 'stale') {
    message.error(run.last_error || t('Shopify_Sync_Failed_Check_Logs', 'That sync failed — check the logs'));
  } else if (run.status === 'cancelled') {
    message.warning(t('Shopify_Sync_Stopped', 'Sync stopped'));
  }
}

/** Entities whose run this page is carrying on, slice by slice. */
const driving = {};

/**
 * See a run through. A long one comes back in slices — each request works for
 * a few seconds and answers `continue`, which keeps every request short enough
 * for hosting that ends long ones — so keep calling until it is over.
 */
async function drive(entity, payload) {
  driving[entity] = true;
  try {
    let res = await http.post('shopify/sync/start', payload);
    while (res?.continue && res.run) {
      settle(entity, res.run);
      res = await http.post('shopify/sync/start', { ...payload, resume: res.run.id });
    }
    return res?.run;
  } finally {
    delete driving[entity];
  }
}

async function carryOn(entity, run) {
  try {
    settle(entity, await drive(entity, {
      store_id: storeId.value, entity, direction: run.direction, resume: run.id,
    }));
  } catch (e) {
    // Another page took it, or the request failed: the poll shows which.
  } finally {
    syncPoller();
    loadRuns();
  }
}

async function start(entity, direction) {
  starting.value = `${entity}:${direction}`;
  // Show progress from the first tick rather than after the request returns.
  running[entity] = { id: null, percentage: 0, processed: 0, total: 0, stage: 'starting', status: 'running' };
  syncPoller();

  try {
    const payload = {
      store_id: storeId.value,
      entity,
      direction,
      dry_run: dryRun.value,
      since: entity === 'orders' ? since.value : null,
    };

    // Normally queued: the answer is the new run, and the poll follows it.
    // On a server without a queue the first slice runs in this request and
    // the page drives the rest, as it always did.
    const res = await http.post('shopify/sync/start', payload);
    if (res?.queued) {
      settle(entity, res.run);
      message.info(tp('Shopify_Sync_Started_Bg', '{label} sync started — it carries on in the background', { label: entityOf(entity).label }));
    } else {
      let last = res;
      if (last?.continue && last.run) {
        settle(entity, last.run);
        last = { run: await drive(entity, { ...payload, resume: last.run.id }) };
      }
      const run = last?.run;
      settle(entity, run);
      if (run && !isLive(run.status)) announce(run);
    }
  } catch (e) {
    delete running[entity];
    message.error(e?.data?.message || t('Shopify_Could_Not_Start', 'Could not start that sync'));
    // Re-read so the card shows what is really going on: on a 409 the run
    // that is in the way, and on a gateway timeout our own run, which carries
    // on working on the server after the browser has given up waiting.
    loadLatest();
  } finally {
    starting.value = null;
    syncPoller();
    loadRuns();
  }
}

async function cancel(entity) {
  const run = running[entity];
  if (!run?.id) return;

  cancelling.value = entity;
  try {
    await http.post(`shopify/sync/cancel/${run.id}`);
    message.info(t('Shopify_Stopping_After_Batch', 'Stopping after the current batch'));
  } catch (e) {
    message.error(e?.data?.message || t('Shopify_Could_Not_Stop', 'Could not stop that run'));
  } finally {
    cancelling.value = null;
  }
}

function onStoreChange() {
  loadLatest();
  loadRuns();
}

onMounted(async () => {
  try {
    const meta = await http.get('shopify/meta');
    stores.value = meta?.stores || [];
    if (!storeId.value && stores.value.length) storeId.value = stores.value[0].id;
  } catch (e) { /* the select stays empty */ }

  if (storeId.value) {
    loadLatest();
    loadRuns();
  }
});

onBeforeUnmount(() => {
  if (poller) clearInterval(poller);
});
</script>

<style scoped>
.bad {
  color: #dc2626;
}
.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}
.tb-store {
  flex: 1 1 280px;
  min-width: 220px;
  max-width: 420px;
}
.tb-check {
  white-space: nowrap;
}
.tb-hint {
  font-size: 12px;
  opacity: 0.55;
}
.blockers {
  margin: 0 0 10px;
  padding-inline-start: 18px;
}
.entity-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 12px;
}
.entity-card {
  display: flex;
  flex-direction: column;
}
.entity-card--off {
  opacity: 0.55;
}
.ec-head {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.ec-ic {
  width: 36px;
  height: 36px;
  flex: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 11px;
  color: #5f9e3f;
  background: rgba(95, 158, 63, 0.13);
}
.ec-title {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}
.ec-hint {
  font-size: 11.5px;
  opacity: 0.6;
}
.ec-off {
  flex: none;
}
.ec-status {
  margin-top: 12px;
  min-height: 46px;
}
.ec-stage {
  font-size: 11.5px;
  opacity: 0.6;
}
.ec-last {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
}
.ec-counts {
  font-size: 11.5px;
  opacity: 0.7;
}
.ec-error {
  margin-top: 4px;
  font-size: 11.5px;
  color: #dc2626;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
.ec-when,
.ec-never {
  font-size: 11.5px;
  opacity: 0.5;
  margin-top: 4px;
}
.ec-actions {
  display: flex;
  gap: 6px;
  margin-top: 10px;
}
.ec-extra {
  margin-top: 8px;
}
@media (max-width: 767px) {
  .tb-store {
    max-width: none;
  }
}
</style>
