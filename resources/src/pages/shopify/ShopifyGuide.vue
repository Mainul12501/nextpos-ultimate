<template>
  <div class="guide">
    <p class="lead">
      <template v-if="store?.id">
        {{ $t('Shopify_G_Lead_Store') }}
      </template>
      <template v-else>
        {{ $t('Shopify_G_Lead_Generic') }}
      </template>
    </p>

    <!-- The choice, before either tab is opened: a tab nobody clicks is a
         feature nobody finds. -->
    <a-table
      size="small" :columns="wayColumns" :data-source="wayRows" row-key="what"
      :pagination="false" :scroll="{ x: 640 }" class="ways"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'what'"><strong>{{ record.what }}</strong></template>
      </template>
    </a-table>
    <p class="note ways-note">
      {{ $t('Shopify_G_Ways_Note') }}
    </p>

    <!-- ---------------------------------------------- common ground -->
    <h3 class="heading">{{ $t('Shopify_G_Both_Need') }}</h3>

    <section class="step">
      <h4><span class="num">1</span> {{ $t('Shopify_G_Credentials') }}</h4>
      <p>
        {{ $t('Shopify_G_Cred_Intro') }}
      </p>
      <ul class="plain">
        <i18n-t keypath="Shopify_G_Cred_DevDash" tag="li">
          <template #app><strong>{{ $t('Shopify_G_Dev_Dashboard_App') }}</strong></template>
          <template #path><em>dev.shopify.com → Apps → Create app</em></template>
          <template #clientId><strong>{{ $t('Client_ID') }}</strong></template>
          <template #clientSecret><strong>{{ $t('Shopify_Client_Secret') }}</strong></template>
          <template #where><strong>{{ $t('Shopify_Edit_Store') }} → {{ $t('Connection') }}</strong></template>
        </i18n-t>
        <i18n-t keypath="Shopify_G_Cred_Legacy" tag="li">
          <template #app><strong>{{ $t('Shopify_G_Legacy_App') }}</strong></template>
          <template #token><strong>{{ $t('Shopify_Admin_Token') }}</strong></template>
          <template #prefix><code>shpat_…</code></template>
          <template #secret><strong>{{ $t('Shopify_G_API_Secret_Key') }}</strong></template>
        </i18n-t>
        <li v-if="store?.id">
          {{ $t('Shopify_Webhook_Secret') }}:
          <a-tag v-if="store.has_webhook_secret" color="success">{{ $t('Shopify_G_Stored') }}</a-tag>
          <a-tag v-else color="warning">{{ $t('Shopify_G_Missing_Refused') }}</a-tag>
        </li>
      </ul>
      <a-table
        size="small" :columns="scopeColumns" :data-source="scopeRows" row-key="entity"
        :pagination="false" :scroll="{ x: 520 }" class="scope-table"
      >
        <template #bodyCell="{ column, record }">
          <template v-if="column.key === 'entity'">
            {{ record.label }}
            <a-tag v-if="store?.id" :color="record.on ? 'success' : 'default'">
              {{ record.on ? $t('Shopify_On') : $t('Shopify_Off') }}
            </a-tag>
          </template>
          <template v-else-if="column.key === 'scopes'">
            <code class="scopes">{{ record.scopes }}</code>
          </template>
        </template>
      </a-table>
    </section>

    <section class="step">
      <h4><span class="num">2</span> {{ $t('Shopify_Tab_Where_Data') }}</h4>
      <i18n-t keypath="Shopify_G_Where_Data_Desc" tag="p">
        <template #path><em>{{ $t('Shopify_Edit_Store') }} → {{ $t('Shopify_Tab_Where_Data') }}.</em></template>
      </i18n-t>
      <p v-if="store?.id">
        <i18n-t keypath="Shopify_G_Currently" tag="span">
          <template #warehouse><strong>{{ store.warehouse_name || $t('Shopify_G_Not_Set_Lower') }}</strong></template>
          <template #location><strong>{{ store.location_id || $t('Shopify_G_Not_Set_Lower') }}</strong></template>
        </i18n-t>
        <a-tag v-if="store.warehouse_id && store.location_id" color="success">{{ $t('Shopify_G_Ready') }}</a-tag>
        <a-tag v-else color="error">{{ $t('Shopify_G_Incomplete') }}</a-tag>
      </p>
    </section>

    <section class="step">
      <h4><span class="num">3</span> {{ $t('Shopify_G_Manual_Syncs') }}</h4>
      <i18n-t keypath="Shopify_G_Manual_Desc" tag="p">
        <template #centre><strong>{{ $t('Sync Centre') }}</strong></template>
      </i18n-t>
      <div v-if="cron.manual" class="cmd">
        <code>{{ cron.manual }}</code>
        <a-button size="small" @click="copy(cron.manual, $t('Shopify_G_Cron_Copied'))">{{ $t('Copy') }}</a-button>
      </div>
      <i18n-t keypath="Shopify_G_Manual_Note" tag="p" class="note">
        <template #program><code>php</code></template>
        <template #args><code>artisan queue:work database --queue=shopify --stop-when-empty
        --max-time=55 --timeout=600</code></template>
        <template #dir><code>{{ cron.base_path }}</code></template>
      </i18n-t>
    </section>

    <!-- ---------------------------------------------- the two ways -->
    <h3 class="heading">{{ $t('Shopify_G_Then_Pick') }}</h3>

    <a-tabs v-model:activeKey="way" type="card" class="way-tabs">
      <!-- ------------------------------------------- real time -->
      <a-tab-pane key="realtime">
        <template #tab>
          <span>
            {{ $t('Shopify_G_Right_Away') }}
            <a-tag v-if="store?.id" :color="store.instant_stock_push ? 'success' : 'default'" class="tab-tag">
              {{ store.instant_stock_push ? $t('Shopify_G_Stock_Live') : $t('Shopify_G_Stock_Not_Live') }}
            </a-tag>
          </span>
        </template>

        <i18n-t keypath="Shopify_G_Realtime_Lead" tag="p" class="pane-lead">
          <template #webhooks><strong>{{ $t('Shopify_G_Webhooks_Lower') }}</strong></template>
          <template #push><strong>{{ $t('Shopify_G_Instant_Push_Lower') }}</strong></template>
        </i18n-t>

        <section class="step">
          <h4><span class="num">A</span> {{ $t('Shopify_G_Webhooks_In') }}</h4>
          <i18n-t keypath="Shopify_G_Webhooks_Desc" tag="p">
            <template #button><strong>{{ $t('Shopify_Register_In_Shopify') }}</strong></template>
            <template #tab>
              <a v-if="store?.id" class="link" @click="$emit('goto', 'webhooks')">{{ $t('Shopify_G_Webhooks_Tab') }}</a>
              <span v-else>{{ $t('Shopify_G_Webhooks_Tab') }}</span>
            </template>
          </i18n-t>
          <div class="cmd">
            <code>{{ webhookUrl }}</code>
            <a-button size="small" @click="copy(webhookUrl, $t('Shopify_URL_Copied'))">{{ $t('Copy') }}</a-button>
          </div>
          <i18n-t keypath="Shopify_G_Webhooks_Note" tag="p" class="note">
            <template #localhost><code>localhost</code></template>
            <template #test><code>.test</code></template>
          </i18n-t>
        </section>

        <section class="step">
          <h4><span class="num">B</span> {{ $t('Shopify_G_Instant_Out') }}</h4>
          <a-alert type="warning" show-icon class="callout" :message="$t('Shopify_G_Setting_Missed')">
            <template #description>
              <i18n-t keypath="Shopify_G_Pos_Not_Auto" tag="p">
                <template #not><strong>{{ $t('Shopify_G_Not') }}</strong></template>
              </i18n-t>
              <i18n-t keypath="Shopify_G_Tick_Push" tag="p">
                <template #setting><strong>{{ $t('Shopify_Push_Stock_On_Change') }}</strong></template>
                <template #where><em>{{ $t('Shopify_Edit_Store') }} → {{ $t('Shopify_Tab_Automatic_Sync') }}</em></template>
              </i18n-t>
              <p v-if="store?.id" class="callout-state">
                {{ $t('Shopify_G_This_Store') }}
                <a-tag :color="store.instant_stock_push ? 'success' : 'warning'">
                  {{ store.instant_stock_push ? $t('Shopify_G_Push_On') : $t('Shopify_G_Push_Off') }}
                </a-tag>
                <a v-if="canEdit && !store.instant_stock_push" class="link" @click="$emit('edit', 'auto')">{{ $t('Shopify_Turn_It_On') }}</a>
              </p>
            </template>
          </a-alert>
          <p class="note">
            {{ $t('Shopify_G_Push_Note') }}
          </p>
        </section>

        <section class="step">
          <h4><span class="num">C</span> {{ $t('Shopify_G_Nothing_Install') }}</h4>
          <i18n-t keypath="Shopify_G_No_Worker_Note" tag="p" class="note">
            <template #after><em>{{ $t('Shopify_G_After') }}</em></template>
          </i18n-t>
          <i18n-t keypath="Shopify_G_Artisan_Note" tag="p" class="note">
            <template #queue><code>webhooks</code></template>
          </i18n-t>
          <div class="cmd">
            <code>{{ cron.queue }}</code>
            <a-button size="small" @click="copy(cron.queue, $t('Shopify_G_Cron_Copied'))">{{ $t('Copy') }}</a-button>
          </div>
        </section>
      </a-tab-pane>

      <!-- ------------------------------------------- scheduled -->
      <a-tab-pane key="scheduled">
        <template #tab>
          <span>
            {{ $t('Shopify_G_On_Schedule') }}
            <a-tag v-if="store?.id" :color="store.auto_sync ? 'success' : 'default'" class="tab-tag">
              {{ store.auto_sync ? $t('Shopify_G_On_Lower') : $t('Shopify_G_Off_Lower') }}
            </a-tag>
          </span>
        </template>

        <p class="pane-lead">
          {{ $t('Shopify_G_Scheduled_Lead') }}
        </p>

        <section class="step">
          <h4><span class="num">A</span> {{ $t('Shopify_G_Cron_Entry') }}</h4>
          <p>
            {{ $t('Shopify_G_Cron_Entry_Desc') }}
          </p>
          <div class="cmd">
            <code>{{ cron.sync }}</code>
            <a-button size="small" @click="copy(cron.sync, $t('Shopify_G_Cron_Copied'))">{{ $t('Copy') }}</a-button>
          </div>
          <i18n-t keypath="Shopify_G_Cron_Note" tag="p" class="note">
            <template #example><code>*/15</code></template>
            <template #program><code>php</code></template>
            <template #args><code>artisan shopify:sync</code></template>
            <template #dir><code>{{ cron.base_path }}</code></template>
          </i18n-t>
        </section>

        <section class="step">
          <h4><span class="num">B</span> {{ $t('Shopify_G_Switch_On_Plan') }}</h4>
          <i18n-t keypath="Shopify_G_Plan_Desc" tag="p">
            <template #where><em>{{ $t('Shopify_Edit_Store') }} → {{ $t('Shopify_Tab_Automatic_Sync') }}.</em></template>
            <template #step><strong>{{ $t('Shopify_G_Step_Lower') }}</strong></template>
            <template #first><em>{{ exampleStep('orders', 'pull', 15) }}</em></template>
            <template #second><em>{{ exampleStep('inventory', 'push', 30) }}</em></template>
          </i18n-t>
          <p class="note">
            {{ $t('Shopify_G_Plan_Note') }}
          </p>
          <p v-if="store?.id">
            {{ $t('Shopify_G_This_Store') }}
            <a-tag :color="store.auto_sync ? 'success' : 'default'">
              {{ store.auto_sync ? $t('Shopify_G_Schedule_On') : $t('Shopify_G_Schedule_Off') }}
            </a-tag>
            <a v-if="canEdit" class="link" @click="$emit('edit', 'auto')">{{ $t('Shopify_Change') }}</a>
          </p>
        </section>

        <section class="step">
          <h4><span class="num">C</span> {{ $t('Shopify_G_When_Step_Runs') }}</h4>
          <i18n-t keypath="Shopify_G_When_Note" tag="p" class="note">
            <template #started><em>{{ $t('Shopify_G_Started_Lower') }}</em></template>
            <template #dryRun><code>--dry-run</code></template>
          </i18n-t>
        </section>
      </a-tab-pane>
    </a-tabs>

    <!-- ---------------------------------------------- reference -->
    <h3 class="heading">{{ $t('Reference') }}</h3>

    <section class="step">
      <h4><span class="num">⚙</span> {{ $t('Shopify_G_Where_Setting') }}</h4>
      <i18n-t keypath="Shopify_G_Where_Setting_Note" tag="p" class="note">
        <template #path><strong>Shopify → {{ $t('Stores') }}</strong></template>
        <template #edit><strong>{{ $t('Shopify_Edit_Store') }}</strong></template>
      </i18n-t>
      <a-table
        size="small" :columns="settingColumns" :data-source="settingRows" row-key="setting"
        :pagination="false" :scroll="{ x: 560 }"
      >
        <template #bodyCell="{ column, record }">
          <template v-if="column.key === 'where'"><strong>{{ record.where }}</strong></template>
        </template>
      </a-table>
    </section>

    <section class="step">
      <h4><span class="num">!</span> {{ $t('Shopify_G_Product_Images') }}</h4>
      <ul class="plain">
        <i18n-t keypath="Shopify_G_Img_1" tag="li">
          <template #push><strong>{{ $t('Shopify_G_Products_Push') }}</strong></template>
        </i18n-t>
        <i18n-t keypath="Shopify_G_Img_2" tag="li">
          <template #assigned><strong>{{ $t('Shopify_G_Assigned') }}</strong></template>
        </i18n-t>
        <i18n-t keypath="Shopify_G_Img_3" tag="li">
          <template #uploaded><strong>{{ $t('Shopify_G_Uploaded') }}</strong></template>
        </i18n-t>
        <li>
          {{ $t('Shopify_G_Img_4') }}
        </li>
        <i18n-t keypath="Shopify_G_Img_5" tag="li">
          <template #not><em>{{ $t('Shopify_G_Not') }}</em></template>
        </i18n-t>
      </ul>
    </section>

    <section class="step">
      <h4><span class="num">⌘</span> {{ $t('Shopify_G_Commands') }}</h4>
      <div v-for="item in commands" :key="item.cmd" class="cmd cmd--row">
        <div class="cmd-body">
          <code>{{ item.cmd }}</code>
          <span class="cmd-what">{{ item.what }}</span>
        </div>
        <a-button size="small" @click="copy(item.cmd, $t('Shopify_G_Command_Copied'))">{{ $t('Copy') }}</a-button>
      </div>
    </section>

    <i18n-t keypath="Shopify_G_Foot" tag="p" class="note foot">
      <template #logs><strong>Shopify → {{ $t('Logs') }}</strong></template>
    </i18n-t>
  </div>
</template>

<script setup>
/**
 * The setup guide, shared by the Shopify → Guide page and the Setup guide tab
 * on a store. One copy, so the two cannot drift; what changes between them is
 * only whether a store is in hand — with one, the guide reports that store's
 * actual state next to each step.
 *
 * Laid out as the module actually works: what both ways need, then a tab per
 * way, because their setup differs (a queue worker versus a cron entry) and
 * reading the steps for the path you are not taking is how people end up
 * configuring neither. The comparison table stays outside the tabs so the
 * choice is visible without opening either one.
 */
import { ref, computed } from 'vue';
import { message } from 'ant-design-vue';
import {
  ENTITIES, DIRECTIONS, entityOf, labelOf, tp,
} from './shopifyOptions';
import { t } from '../../i18n';

const props = defineProps({
  /** The store this guide is about, or null for the generic version. */
  store: { type: Object, default: null },
  cron: { type: Object, default: () => ({ base_path: '', sync: '', queue: '', manual: '' }) },
  webhookUrl: { type: String, default: '' },
  /** Whether to offer the "Change these" shortcut into the edit form. */
  canEdit: { type: Boolean, default: false },
});

defineEmits(['goto', 'edit']);

// Real time first: it is the half people do not know is there.
const way = ref('realtime');

const wayColumns = computed(() => [
  { title: '', key: 'what', dataIndex: 'what', width: 130 },
  { title: t('Shopify_G_Right_Away', 'Right away'), dataIndex: 'realtime', key: 'realtime' },
  { title: t('Shopify_G_On_Schedule', 'On a schedule'), dataIndex: 'scheduled', key: 'scheduled' },
]);

const wayRows = computed(() => {
  const autoTab = t('Shopify_Tab_Automatic_Sync', 'Automatic sync');

  return [
    {
      what: t('Shopify_G_Covers', 'Covers'),
      realtime: t('Shopify_G_Covers_Rt', 'Orders and edits made in Shopify; stock leaving the ERP (a POS sale, a return, an adjustment)'),
      scheduled: t('Shopify_G_Covers_Sch', 'Everything, both ways — including catalogue pushes and anything the fast path missed'),
    },
    {
      what: t('Shopify_G_Speed', 'Speed'),
      realtime: t('Shopify_G_Speed_Rt', 'Seconds'),
      scheduled: t('Shopify_G_Speed_Sch', "The step's interval (5 minutes to 7 days)"),
    },
    {
      what: t('Shopify_G_Needs', 'Needs'),
      realtime: t('Shopify_G_Needs_Rt', 'A public HTTPS address and the signing secret for incoming webhooks. Outgoing stock needs nothing at all'),
      scheduled: tp('Shopify_G_Needs_Sch', 'A cron entry running {cmd}', { cmd: 'php artisan shopify:sync' }),
    },
    {
      what: t('Shopify_G_Switched_On_At', 'Switched on at'),
      realtime: `${t('Shopify_G_Webhooks_Tab', 'Webhooks tab')} → ${t('Shopify_Register_In_Shopify', 'Register in Shopify')} · `
        + `${autoTab} → ${t('Shopify_Push_Stock_On_Change', 'Push stock the moment it changes')}`,
      scheduled: tp('Shopify_G_On_Sch', '{path}, plus the plan', {
        path: `${autoTab} → ${t('Shopify_Auto_Schedule', 'Sync automatically on a schedule')}`,
      }),
    },
  ];
});

/** "Orders · Shopify → ERP every 15 min" — an example step, in the UI's own words. */
function exampleStep(entity, direction, every) {
  return `${entityOf(entity).label} · ${labelOf(DIRECTIONS, direction)} `
    + tp('Shopify_Every_N_Min', 'every {n} min', { n: every });
}

/**
 * The Admin API scopes each entity needs, next to whether this store actually
 * syncs it — so the list to grant in Shopify is the one on screen, not a
 * generic table to filter mentally.
 */
const SCOPES = {
  products: 'read_products, write_products',
  inventory: 'read_inventory, write_inventory, read_locations',
  customers: 'read_customers, write_customers',
  collections: 'read_products, write_products',
  fulfillments: 'read/write_merchant_managed_fulfillment_orders, read/write_assigned_fulfillment_orders',
};

function scopesFor(entity) {
  if (entity === 'orders') {
    return tp('Shopify_G_Scope_Orders', '{scope} ({extra} for orders over 60 days old)', {
      scope: 'read_orders', extra: 'read_all_orders',
    });
  }

  return SCOPES[entity] || '';
}

const scopeColumns = computed(() => [
  { title: t('Shopify_Entity', 'Entity'), key: 'entity', width: 150 },
  { title: t('Shopify_G_Admin_Scopes', 'Admin API scopes'), key: 'scopes' },
]);

const scopeRows = computed(() => ENTITIES.map(e => ({
  entity: e.value,
  label: entityOf(e.value).label,
  on: !!props.store?.[`sync_${e.value}`],
  scopes: scopesFor(e.value),
})));

const settingColumns = computed(() => [
  { title: t('Shopify_G_Setting', 'Setting'), dataIndex: 'setting', key: 'setting', width: 230 },
  { title: t('Shopify_G_Tab_In_Edit', 'Tab in Edit store'), key: 'where', width: 170 },
  { title: '', dataIndex: 'what', key: 'what' },
]);

const settingRows = computed(() => {
  const connection = t('Connection', 'Connection');
  const lands = t('Shopify_Tab_Where_Data', 'Where data lands');
  const toSync = t('Shopify_Tab_What_To_Sync', 'What to sync');
  const auto = t('Shopify_Tab_Automatic_Sync', 'Automatic sync');

  return [
    { setting: t('Shopify_G_Set_Credentials', 'Client ID + secret or access token, signing secret, API version'), where: connection, what: t('Shopify_G_Set_Credentials_What', 'The credentials from the Shopify app (Dev Dashboard or legacy custom app).') },
    { setting: t('Shopify_G_Set_Targets', 'ERP warehouse, Shopify location'), where: lands, what: t('Shopify_G_Set_Targets_What', 'Which stock this shop sells, and where orders are filed.') },
    { setting: t('Shopify_Price_Sent', 'Price sent to Shopify'), where: lands, what: t('Shopify_G_Set_Price_What', 'Retail, wholesale or minimum price.') },
    { setting: t('Shopify_G_Set_Create_Missing', 'Create missing products / customers'), where: lands, what: t('Shopify_G_Set_Create_Missing_What', 'Whether a pull may create ERP records it cannot match.') },
    { setting: t('Shopify_G_Set_Entities', 'Which entities sync at all'), where: toSync, what: t('Shopify_G_Set_Entities_What', 'Off here means off everywhere — manual, scheduled and webhook.') },
    { setting: t('Shopify_Auto_Schedule', 'Sync automatically on a schedule'), where: auto, what: t('Shopify_G_Set_Scheduler_What', 'The scheduler switch.') },
    { setting: t('Shopify_Push_Stock_On_Change', 'Push stock the moment it changes'), where: auto, what: t('Shopify_G_Set_Push_What', 'Instant stock push, queued on a sale or adjustment.') },
    { setting: t('Shopify_G_Set_Plan', 'Default interval, and the plan of steps'), where: auto, what: t('Shopify_G_Set_Plan_What', 'What runs by itself, which way, and how often.') },
  ];
});

const commands = computed(() => {
  const id = props.store?.id;

  return [
    { cmd: 'php artisan shopify:sync --list', what: t('Shopify_G_Cmd_List', 'Every store: what would run, and when. Changes nothing.') },
    { cmd: `php artisan shopify:sync --store=${id || '<id>'} --force`, what: t('Shopify_G_Cmd_Force', "One store's whole plan now, ignoring the clock.") },
    { cmd: `php artisan shopify:sync --store=${id || '<id>'} --entity=products --direction=push --only-unsynced`, what: t('Shopify_G_Cmd_First_Push', 'First catalogue push: only products not yet linked.') },
    { cmd: `php artisan shopify:sync --store=${id || '<id>'} --entity=orders --direction=pull --since=2026-01-01`, what: t('Shopify_G_Cmd_Backfill', 'Backfill orders from a date.') },
    { cmd: `php artisan shopify:sync --store=${id || '<id>'} --entity=inventory --direction=push --dry-run`, what: t('Shopify_G_Cmd_Dry', 'Walk it and report, writing nothing anywhere.') },
  ];
});

function copy(text, label = t('Copied', 'Copied')) {
  if (!text) return;
  navigator.clipboard?.writeText(text)
    .then(() => message.success(label))
    // No clipboard (insecure origin, locked-down browser): show the text so it
    // can still be selected by hand rather than failing silently.
    .catch(() => message.info(text));
}
</script>

<style scoped>
.guide {
  max-width: 900px;
}
.lead {
  font-size: 13px;
  opacity: 0.7;
  margin-bottom: 14px;
}
.heading {
  margin: 22px 0 12px;
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.55;
}
.ways {
  margin-bottom: 8px;
}
.ways-note {
  margin-bottom: 4px;
}
.way-tabs {
  margin-top: -4px;
}
.tab-tag {
  margin-inline-start: 6px;
  font-size: 10.5px;
  line-height: 16px;
}
.pane-lead {
  margin: 4px 0 16px;
  font-size: 13px;
  line-height: 1.6;
  opacity: 0.8;
}
.step {
  padding: 0 0 18px;
  margin-bottom: 18px;
  border-bottom: 1px solid rgba(128, 128, 128, 0.16);
}
.step:last-of-type {
  border-bottom: 0;
  margin-bottom: 0;
}
.step h4 {
  display: flex;
  align-items: center;
  gap: 9px;
  margin: 0 0 8px;
  font-size: 14px;
  font-weight: 600;
}
.num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: rgba(var(--app-primary-rgb, 109, 40, 217), 0.14);
  font-size: 12px;
  flex: none;
}
.step p {
  margin: 0 0 8px;
  font-size: 13px;
  line-height: 1.6;
}
.plain {
  margin: 0 0 10px;
  padding-inline-start: 18px;
  font-size: 13px;
  line-height: 1.75;
}
.note {
  font-size: 12px;
  opacity: 0.68;
  line-height: 1.6;
}
.note.foot {
  margin-top: 4px;
}
.step code,
.note code {
  padding: 2px 6px;
  border-radius: 5px;
  background: rgba(128, 128, 128, 0.14);
  font-size: 11.5px;
  word-break: break-all;
}
.cmd {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin: 8px 0 10px;
  padding: 8px 10px;
  border: 1px solid rgba(128, 128, 128, 0.2);
  border-radius: 10px;
}
.cmd code {
  flex: 1 1 320px;
  background: none;
  padding: 0;
  font-size: 12px;
}
.cmd--row {
  align-items: flex-start;
}
.cmd-body {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1 1 320px;
  min-width: 0;
}
.cmd-what {
  font-size: 11.5px;
  opacity: 0.6;
}
.scope-table {
  margin-top: 10px;
}
.scopes {
  font-size: 11px;
  background: none;
  padding: 0;
}
.link {
  color: #5f9e3f;
  cursor: pointer;
}
.callout {
  margin: 4px 0 12px;
}
.callout :deep(p) {
  margin: 0 0 6px;
  font-size: 12.5px;
  line-height: 1.6;
}
.callout :deep(p:last-child) {
  margin-bottom: 0;
}
.callout-state {
  margin-top: 8px;
}
</style>
