<template>
  <div>
    <a-alert
      :type="connectionOk === true ? 'success' : connectionOk === false ? 'error' : 'info'"
      show-icon style="margin-bottom: 16px"
      :message="connectionOk === true ? $t('Connected') : connectionOk === false ? $t('Disconnected') : $t('Unknown')"
      :description="lastSyncText || undefined"
    />

    <a-form ref="formRef" :model="form" :rules="rules" layout="vertical" style="max-width: 720px">
      <a-form-item :label="$t('Store_URL')" name="store_url">
        <a-input v-model:value="form.store_url" placeholder="https://shop.example.com" />
      </a-form-item>
      <a-row :gutter="16">
        <a-col :xs="24" :md="12">
          <a-form-item :label="$t('Consumer_Key')" name="consumer_key">
            <a-input v-model:value="form.consumer_key" />
          </a-form-item>
        </a-col>
        <a-col :xs="24" :md="12">
          <a-form-item :label="$t('Consumer_Secret')" name="consumer_secret">
            <a-input-password v-model:value="form.consumer_secret" />
          </a-form-item>
        </a-col>
        <a-col :xs="24" :md="12">
          <a-form-item :label="$t('Woo_Wp_Username')">
            <a-input v-model:value="form.wp_username" />
          </a-form-item>
        </a-col>
        <a-col :xs="24" :md="12">
          <a-form-item :label="$t('Woo_Wp_App_Password')">
            <a-input-password v-model:value="form.wp_app_password" />
          </a-form-item>
        </a-col>
      </a-row>
      <a-divider style="margin: 4px 0 16px" />

      <a-form-item style="margin-bottom: 10px">
        <a-checkbox v-model:checked="form.instant_stock_push">
          {{ $t('Woo_Push_Stock_On_Change') }}
        </a-checkbox>
        <i18n-t keypath="Woo_Settings_Instant_Hint" tag="div" class="hint">
          <template #guide><a class="link" @click="$emit('open-guide')">{{ $t('Guide') }}</a></template>
        </i18n-t>
        <!-- Spelled out, because the consequence of leaving this off is the
             one people meet later as a mystery: a product sold at the till
             that the shop keeps selling. -->
        <a-alert
          v-if="!form.instant_stock_push"
          type="info" show-icon style="margin-top: 8px"
          :message="$t('Woo_Settings_Instant_Off_Alert')"
        />
      </a-form-item>

      <a-form-item style="margin-bottom: 10px">
        <a-checkbox v-model:checked="form.auto_sync_products">
          {{ $t('Woo_Push_Products_On_Save') }}
        </a-checkbox>
        <div class="hint">{{ $t('Woo_Settings_Auto_Products_Hint') }}</div>
      </a-form-item>

      <a-space>
        <a-button type="primary" @click="onSubmit">{{ $t('Save') }}</a-button>
        <a-button :loading="connecting" @click="testConnection">{{ $t('Connection') }}</a-button>
      </a-space>
    </a-form>

    <a-card class="guide-card" :bordered="true" style="margin-top: 24px">
      <template #title>
        <span class="guide-card-title">
          <ControlOutlined :style="{ color: token.colorInfo }" />
          {{ $t('Woo_Sync_Tuning') }}
        </span>
      </template>
      <template #extra>
        <a-button size="small" :disabled="!tuningFields.length" @click="resetTuning">{{ $t('Reset') }}</a-button>
      </template>

      <p class="guide-intro">{{ $t('Woo_Sync_Tuning_Intro') }}</p>

      <a-collapse v-model:activeKey="tuningOpen" ghost>
        <a-collapse-panel v-for="g in tuningGroups" :key="g.key" :header="g.title">
          <a-row :gutter="16">
            <a-col v-for="f in fieldsIn(g.key)" :key="f.key" :xs="24" :md="12">
              <a-form-item :label="f.label" :help="f.hint">
                <a-input-number
                  v-if="f.type === 'integer'"
                  v-model:value="syncOptions[f.key]"
                  :min="f.min" :max="f.max" :placeholder="String(f.default)"
                  style="width: 100%"
                />
                <a-switch v-else-if="f.type === 'boolean'" v-model:checked="syncOptions[f.key]" />
                <a-input v-else v-model:value="syncOptions[f.key]" allow-clear placeholder="—" />
              </a-form-item>
            </a-col>
          </a-row>
        </a-collapse-panel>
      </a-collapse>

      <a-button type="primary" style="margin-top: 8px" @click="onSubmit">{{ $t('Save') }}</a-button>
    </a-card>

    <a-card class="guide-card" :bordered="true" style="margin-top: 24px">
      <template #title>
        <span class="guide-card-title">
          <BookOutlined :style="{ color: token.colorInfo }" />
          {{ $t('Woo_Setting_This_Up') }}
        </span>
      </template>
      <p class="guide-intro" style="margin-bottom: 10px">{{ $t('Woo_Setting_This_Up_Text') }}</p>
      <a-button type="primary" ghost @click="$emit('open-guide')">{{ $t('Woo_Open_The_Guide') }}</a-button>
    </a-card>
  </div>
</template>

<script setup>
/**
 * WooCommerce connection settings — GET/POST woocommerce/settings
 * {store_url, consumer_key, consumer_secret, wp_username, wp_app_password};
 * POST woocommerce/test-connection → {ok}. Legacy validation: store_url
 * required + must start with http(s)://; keys required.
 *
 * The same endpoint also carries `sync_options` (batch sizes, timeouts, retries,
 * lookup caps — formerly WOO_* env vars) plus `sync_options_meta`, which
 * describes every field so the tuning form below renders itself. Sending a key
 * as null/blank clears it, and the server falls back to env then to its default.
 */
import { ref, computed, onMounted } from 'vue';
import { message, theme } from 'ant-design-vue';
import {
  BookOutlined, KeyOutlined, UserOutlined, SettingOutlined, SyncOutlined,
  InfoCircleOutlined, GlobalOutlined, PlusOutlined, SelectOutlined,
  PictureOutlined, CheckCircleOutlined, CloudSyncOutlined, ClockCircleOutlined,
  SwapOutlined, MenuOutlined, ExclamationCircleOutlined, FieldTimeOutlined,
  CodeOutlined, ControlOutlined,
} from '@ant-design/icons-vue';
import { useI18n } from 'vue-i18n';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import http from '../../../lib/http';

dayjs.extend(relativeTime);

const { t } = useI18n();
const { token } = theme.useToken();
const emit = defineEmits(['ready', 'connection', 'updated', 'open-guide']);

const connecting = ref(false);
const connectionOk = ref(null);
const lastSyncAt = ref(null);
const formRef = ref();
const form = ref({
  store_url: '',
  consumer_key: '',
  consumer_secret: '',
  wp_username: '',
  wp_app_password: '',
});

const syncOptions = ref({});
const tuningMeta = ref({ groups: [], fields: [] });
const tuningOpen = ref([]);

const tuningGroups = computed(() => tuningMeta.value.groups || []);
const tuningFields = computed(() => tuningMeta.value.fields || []);
function fieldsIn(group) {
  return tuningFields.value.filter(f => f.group === group);
}
function resetTuning() {
  const next = {};
  for (const f of tuningFields.value) {
    next[f.key] = f.default ?? (f.type === 'boolean' ? false : null);
  }
  syncOptions.value = next;
}

const rules = computed(() => ({
  store_url: [
    { required: true, message: t('Field_is_required') },
    { pattern: /^https?:\/\//, message: 'https://…' },
  ],
  consumer_key: [{ required: true, message: t('Field_is_required') }],
  consumer_secret: [{ required: true, message: t('Field_is_required') }],
}));

const lastSyncText = computed(() => (lastSyncAt.value ? dayjs(lastSyncAt.value).fromNow() : null));

async function loadSettings() {
  try {
    const data = await http.get('woocommerce/settings');
    if (data.settings) {
      // sync_options is edited through its own form, not the credentials one.
      const { sync_options: _stored, ...credentials } = data.settings;
      form.value = { ...form.value, ...credentials };
      lastSyncAt.value = data.settings.last_sync_at;
    }
    if (data.sync_options_meta) tuningMeta.value = data.sync_options_meta;
    if (data.sync_options) syncOptions.value = { ...data.sync_options };
  } catch (e) { /* first-run: nothing saved yet */ }
}

async function onSubmit() {
  try {
    await formRef.value.validate();
  } catch (e) {
    message.error(t('Please_fill_the_form_correctly'));
    return;
  }
  try {
    await http.post('woocommerce/settings', { ...form.value, sync_options: syncOptions.value });
    message.success(t('Successfully_Updated'));
    emit('updated');
    testConnection();
  } catch (e) {
    message.error(t('InvalidData'));
  }
}

async function testConnection() {
  connecting.value = true;
  try {
    const data = await http.post('woocommerce/test-connection');
    connectionOk.value = !!data.ok;
    emit('connection', connectionOk.value);
    if (data.ok) message.success(t('Connection_successful'));
    else message.error(t('Connection_failed'));
  } catch (e) {
    connectionOk.value = false;
    emit('connection', false);
    message.error(t('Connection_failed'));
  } finally {
    connecting.value = false;
  }
}

onMounted(async () => {
  await loadSettings();
  // Silent probe on load (no toast) — mirror legacy's created() order but
  // avoid the noisy toast: probe directly.
  try {
    const data = await http.post('woocommerce/test-connection');
    connectionOk.value = !!data.ok;
    emit('connection', connectionOk.value);
  } catch (e) {
    connectionOk.value = false;
    emit('connection', false);
  }
  emit('ready');
});
</script>

<style scoped>
.guide-card {
  max-width: 920px;
}

.guide-card-title,
.guide-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.guide-title {
  display: flex;
  font-weight: 600;
  font-size: 15px;
  margin-bottom: 10px;
}

.guide-intro {
  font-size: 13px;
  line-height: 1.6;
  margin: 0 0 8px;
  opacity: 0.85;
}

.guide-divider {
  margin: 14px 0;
}

.guide-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.guide-list li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 5px 0;
  line-height: 1.6;
  font-size: 13px;
}

.guide-list li .anticon {
  margin-top: 4px;
  flex-shrink: 0;
}

.guide-code {
  border: 1px solid;
  border-radius: 8px;
  padding: 10px 14px;
  margin: 8px 0;
  font-size: 12px;
  line-height: 1.6;
  white-space: pre-wrap;
  word-break: break-word;
  overflow-x: auto;
}
</style>
