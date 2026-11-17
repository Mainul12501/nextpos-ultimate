<template>
  <div class="guide">
    <p class="lead">{{ $t('Woo_Guide_Lead') }}</p>

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
    <i18n-t keypath="Woo_Guide_Ways_Note" tag="p" class="note ways-note">
      <template #in><em>{{ $t('Woo_In') }}</em></template>
      <template #out><em>{{ $t('Woo_Out') }}</em></template>
    </i18n-t>

    <!-- ---------------------------------------------- common ground -->
    <h3 class="heading">{{ $t('Woo_Guide_Both_Ways_Title') }}</h3>

    <section class="step">
      <h4><span class="num">1</span> {{ $t('Woo_Api_Keys') }}</h4>
      <i18n-t keypath="Woo_Guide_Api_Keys_Text" tag="p">
        <template #path><em>WooCommerce → Settings → Advanced → REST API → Add key</em></template>
        <template #perm><strong>Read/Write</strong></template>
        <template #settings><strong>{{ $t('Settings') }}</strong></template>
        <template #example><code>https://yoursite.com</code></template>
      </i18n-t>
      <p v-if="settings" class="state">
        <a-tag :color="connected ? 'success' : 'default'">
          {{ connected ? $t('Woo_Credentials_Saved') : $t('Woo_Not_Configured_Yet') }}
        </a-tag>
        <span v-if="settings.store_url" class="muted">{{ settings.store_url }}</span>
      </p>
    </section>

    <section class="step">
      <h4><span class="num">2</span> {{ $t('Woo_Guide_Wp_App_Password_Title') }}</h4>
      <p>{{ $t('Woo_Guide_Wp_App_Password_Text') }}</p>
      <i18n-t keypath="Woo_Guide_Wp_App_Password_Note" tag="p" class="note">
        <template #path><em>Users → Profile → Application Passwords</em></template>
      </i18n-t>
    </section>

    <!-- ---------------------------------------------- the two ways -->
    <h3 class="heading">{{ $t('Woo_Guide_Pick_Way_Title') }}</h3>

    <a-tabs v-model:activeKey="way" type="card" class="way-tabs">
      <!-- ------------------------------------------- real time -->
      <a-tab-pane key="realtime">
        <template #tab>
          <span>
            {{ $t('Woo_Right_Away') }}
            <a-tag v-if="settings" :color="settings.instant_stock_push ? 'success' : 'default'" class="tab-tag">
              {{ settings.instant_stock_push ? $t('Woo_Stock_Live') : $t('Woo_Stock_Not_Live') }}
            </a-tag>
          </span>
        </template>

        <p class="pane-lead">{{ $t('Woo_Guide_Realtime_Lead') }}</p>

        <section class="step">
          <h4><span class="num">A</span> {{ $t('Woo_Push_Stock_On_Change') }}</h4>
          <a-alert type="warning" show-icon class="callout" :message="$t('Woo_Setting_People_Miss')">
            <template #description>
              <i18n-t keypath="Woo_Guide_Pos_Sale_Not_Reach" tag="p">
                <template #not><strong>{{ $t('Woo_Does_Not') }}</strong></template>
              </i18n-t>
              <i18n-t keypath="Woo_Guide_Tick_Instant" tag="p">
                <template #option><strong>{{ $t('Woo_Push_Stock_On_Change') }}</strong></template>
                <template #settings><a class="link" @click="$emit('open-settings')">{{ $t('Settings') }}</a></template>
              </i18n-t>
              <p v-if="settings" class="callout-state">
                {{ $t('Woo_Currently') }}
                <a-tag :color="settings.instant_stock_push ? 'success' : 'warning'">
                  {{ settings.instant_stock_push ? $t('Woo_Instant_On_Text') : $t('Woo_Instant_Off_Text') }}
                </a-tag>
                <a class="link" @click="$emit('open-settings')">{{ $t('Change') }}</a>
              </p>
            </template>
          </a-alert>
          <p class="note">{{ $t('Woo_Guide_Instant_Note') }}</p>
        </section>

        <section class="step">
          <h4><span class="num">B</span> {{ $t('Woo_Push_Products_On_Save') }}</h4>
          <p class="note">
            <i18n-t keypath="Woo_Guide_Tick_Auto_Products" tag="span">
              <template #option><strong>{{ $t('Woo_Push_Products_On_Save') }}</strong></template>
              <template #settings><a class="link" @click="$emit('open-settings')">{{ $t('Settings') }}</a></template>
            </i18n-t>
            <template v-if="settings">
              {{ $t('Woo_Currently') }}
              <a-tag :color="settings.auto_sync_products ? 'success' : 'default'">
                {{ settings.auto_sync_products ? $t('Woo_On') : $t('Woo_Off') }}
              </a-tag>
            </template>
          </p>
          <p class="note">{{ $t('Woo_Guide_Bulk_Imports_Note') }}</p>
        </section>

        <section class="step">
          <h4><span class="num">C</span> {{ $t('Woo_Nothing_Else_To_Install') }}</h4>
          <i18n-t keypath="Woo_Guide_Same_Process" tag="p" class="note">
            <template #after><em>{{ $t('Woo_After') }}</em></template>
          </i18n-t>
          <p class="note">{{ $t('Woo_Guide_Shop_Stops_Answering') }}</p>
          <i18n-t keypath="Woo_Guide_Artisan_Exception" tag="p" class="note">
            <template #queue><code>webhooks</code></template>
          </i18n-t>
          <div class="cmd">
            <code>{{ cron.queue }}</code>
            <a-button size="small" @click="copy(cron.queue, t('Woo_Cron_Line_Copied'))">{{ $t('Copy') }}</a-button>
          </div>
          <i18n-t keypath="Woo_Guide_Not_Woo_Queue" tag="p" class="note">
            <template #queue><code>woocommerce</code></template>
          </i18n-t>
        </section>
      </a-tab-pane>

      <!-- ------------------------------------------- scheduled -->
      <a-tab-pane key="scheduled">
        <template #tab>
          <span>{{ $t('Woo_On_A_Schedule') }}</span>
        </template>

        <p class="pane-lead">{{ $t('Woo_Guide_Scheduled_Lead') }}</p>

        <section class="step">
          <h4><span class="num">A</span> WooCommerce → Stocky</h4>
          <div v-for="item in inbound" :key="item.cron" class="cmd cmd--row">
            <div class="cmd-body">
              <code>{{ item.cron }}</code>
              <span class="cmd-what">{{ item.what }}</span>
            </div>
            <a-button size="small" @click="copy(item.cron, t('Woo_Cron_Line_Copied'))">{{ $t('Copy') }}</a-button>
          </div>
        </section>

        <section class="step">
          <h4><span class="num">B</span> Stocky → WooCommerce</h4>
          <div v-for="item in outbound" :key="item.cron" class="cmd cmd--row">
            <div class="cmd-body">
              <code>{{ item.cron }}</code>
              <span class="cmd-what">{{ item.what }}</span>
            </div>
            <a-button size="small" @click="copy(item.cron, t('Woo_Cron_Line_Copied'))">{{ $t('Copy') }}</a-button>
          </div>
          <i18n-t keypath="Woo_Guide_Sync_Drains_Queue" tag="p" class="note">
            <template #command><code>woocommerce:sync</code></template>
            <template #program><code>php</code></template>
            <template #path><code>{{ cron.base_path }}</code></template>
          </i18n-t>
        </section>

        <section class="step">
          <h4><span class="num">C</span> {{ $t('Woo_Or_No_Cron') }}</h4>
          <p class="note">{{ $t('Woo_Guide_No_Cron_Text') }}</p>
        </section>
      </a-tab-pane>
    </a-tabs>

    <!-- ---------------------------------------------- reference -->
    <h3 class="heading">{{ $t('Reference') }}</h3>

    <section class="step">
      <h4><span class="num">⌘</span> {{ $t('Woo_Commands_Worth_Knowing') }}</h4>
      <div v-for="item in commands" :key="item.cmd" class="cmd cmd--row">
        <div class="cmd-body">
          <code>{{ item.cmd }}</code>
          <span class="cmd-what">{{ item.what }}</span>
        </div>
        <a-button size="small" @click="copy(item.cmd, t('Woo_Command_Copied'))">{{ $t('Copy') }}</a-button>
      </div>
    </section>

    <section class="step">
      <h4><span class="num">!</span> {{ $t('Woo_Worth_Knowing') }}</h4>
      <ul class="plain">
        <li>{{ $t('Woo_Guide_Changing_Url_Clears') }}</li>
        <li>{{ $t('Woo_Guide_Keep_Skus') }}</li>
        <i18n-t keypath="Woo_Guide_Stock_Total" tag="li">
          <template #total><strong>{{ $t('Woo_Total_Across_Warehouses') }}</strong></template>
        </i18n-t>
      </ul>
    </section>

    <i18n-t keypath="Woo_Guide_Full_Reference" tag="p" class="note foot">
      <template #file><code>docs/woocommerce-sync.md</code></template>
    </i18n-t>
  </div>
</template>

<script setup>
/**
 * The WooCommerce setup guide, shared by the Guide tab in the hub and the
 * Guide page in the sidebar. Same shape as the Shopify one — what both ways
 * need, then a tab per way — because the two integrations pose the operator
 * the same question, even though Woo answers half of it differently: it has no
 * webhook receiver, so only outgoing stock can be immediate.
 */
import { ref, computed } from 'vue';
import { message } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
  /** The saved WooCommerce settings row, when the caller has it. */
  settings: { type: Object, default: null },
  cron: { type: Object, default: () => ({}) },
});

defineEmits(['open-settings']);

// Real time first: it is the half people do not know is there.
const way = ref('realtime');

const connected = computed(() => !!(props.settings?.store_url && props.settings?.consumer_key));

const wayColumns = computed(() => [
  { title: '', key: 'what', dataIndex: 'what', width: 130 },
  { title: t('Woo_Right_Away'), dataIndex: 'realtime', key: 'realtime' },
  { title: t('Woo_On_A_Schedule'), dataIndex: 'scheduled', key: 'scheduled' },
]);

const wayRows = computed(() => [
  {
    what: t('Woo_Way_Covers'),
    realtime: t('Woo_Way_Covers_Realtime'),
    scheduled: t('Woo_Way_Covers_Scheduled'),
  },
  { what: t('Woo_Way_Speed'), realtime: t('Woo_Way_Seconds'), scheduled: t('Woo_Way_Speed_Scheduled') },
  { what: t('Woo_Way_Needs'), realtime: t('Woo_Way_Needs_Realtime'), scheduled: t('Woo_Way_Needs_Scheduled') },
  {
    what: t('Woo_Way_Switched_On_At'),
    realtime: t('Settings') + ' → ' + t('Woo_Push_Stock_On_Change'),
    scheduled: t('Woo_Way_Cron_Entries_Below'),
  },
]);

const inbound = computed(() => [
  { cron: props.cron.orders, what: t('Woo_Cron_Orders') },
  { cron: props.cron.products_pull, what: t('Woo_Cron_Products_Pull') },
  { cron: props.cron.customers, what: t('Woo_Cron_Customers') },
].filter(i => i.cron));

const outbound = computed(() => [
  { cron: props.cron.stock, what: t('Woo_Cron_Stock') },
  { cron: props.cron.products_push, what: t('Woo_Cron_Products_Push') },
].filter(i => i.cron));

const commands = computed(() => [
  { cmd: 'php artisan woocommerce:sync --scope=stock', what: t('Woo_Cmd_Stock') },
  { cmd: 'php artisan woocommerce:sync --scope=products --only-unsynced', what: t('Woo_Cmd_Products_Unsynced') },
  { cmd: 'php artisan woocommerce:sync --scope=orders', what: t('Woo_Cmd_Orders') },
  { cmd: 'php artisan woocommerce:sync --scope=orders --full', what: t('Woo_Cmd_Orders_Full') },
  { cmd: 'php artisan woocommerce:sync --scope=pull', what: t('Woo_Cmd_Pull') },
]);

function copy(text, label) {
  if (!text) return;
  navigator.clipboard?.writeText(text)
    .then(() => message.success(label || t('Copied')))
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
  margin: 0;
  padding-inline-start: 18px;
  font-size: 13px;
  line-height: 1.75;
}
.state .muted {
  margin-inline-start: 6px;
  opacity: 0.6;
  font-size: 12px;
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
