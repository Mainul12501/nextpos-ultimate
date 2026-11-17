<template>
  <a-drawer
    :open="open"
    :title="tf('AI_Settings', 'AI Assistant settings')"
    placement="right"
    :width="drawerWidth"
    @update:open="v => $emit('update:open', v)"
    @after-open-change="v => v && load()"
  >
    <a-spin :spinning="loading">
      <a-form layout="vertical">
        <a-form-item :label="tf('Ai_Enable', 'Enable the AI assistant')">
          <a-switch v-model:checked="form.enabled" />
        </a-form-item>

        <a-form-item
          :label="tf('Ai_Api_Key', 'Claude API key')"
          :extra="keyHelp"
        >
          <a-input-password
            v-model:value="form.api_key"
            :placeholder="info.masked_key || 'sk-ant-…'"
            autocomplete="off"
          />
          <a-button v-if="info.masked_key && !info.key_from_env" type="link" danger size="small" style="padding: 0" @click="clearKey">
            {{ tf('Ai_Remove_Key', 'Remove the stored key') }}
          </a-button>
        </a-form-item>

        <a-form-item :label="tf('Ai_Model', 'Model')" :extra="tf('Ai_Model_Help', 'Opus is the most capable; Sonnet and Haiku answer faster at a lower cost.')">
          <a-select v-model:value="form.model" :options="modelOptions" />
        </a-form-item>

        <a-form-item :label="tf('Ai_Effort', 'Thoroughness')" :extra="tf('Ai_Effort_Help', 'Higher takes longer and uses more tokens, but handles harder questions better.')">
          <a-segmented v-model:value="form.effort" :options="effortOptions" block />
        </a-form-item>

        <a-form-item :label="tf('Ai_Insights', 'Daily insights on the dashboard')" :extra="tf('Ai_Insights_Help', 'One short AI summary per day, written when the dashboard is first opened.')">
          <a-switch v-model:checked="form.insights_enabled" />
        </a-form-item>

        <a-form-item :label="tf('Ai_Monthly_Limit', 'Monthly token limit')" :extra="usageText">
          <a-input-number
            v-model:value="form.monthly_token_limit"
            :min="1000"
            :step="100000"
            :placeholder="tf('Ai_No_Limit', 'No limit')"
            style="width: 100%"
          />
        </a-form-item>
      </a-form>

      <a-alert
        v-if="testResult"
        :type="testResult.ok ? 'success' : 'error'"
        show-icon
        :message="testResult.text"
        style="margin-bottom: 16px"
      />
    </a-spin>

    <template #footer>
      <div class="footer">
        <a-button :loading="testing" @click="test">
          <template #icon><ApiOutlined /></template>
          {{ tf('Ai_Test_Connection', 'Test connection') }}
        </a-button>
        <a-space>
          <a-button @click="$emit('update:open', false)">{{ $t('Cancel') }}</a-button>
          <a-button type="primary" :loading="saving" @click="save">{{ $t('Save') }}</a-button>
        </a-space>
      </div>
    </template>
  </a-drawer>
</template>

<script setup>
/**
 * GET/PUT ai/settings, POST ai/settings/test. The API key is write-only:
 * the server only ever returns a masked form; leaving the field blank keeps
 * the stored key.
 */
import { ref, reactive, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { message } from 'ant-design-vue';
import { ApiOutlined } from '@ant-design/icons-vue';
import { t as tf } from '../../i18n';
import http from '../../lib/http';

defineProps({ open: { type: Boolean, default: false } });
const emit = defineEmits(['update:open', 'saved']);

const { t } = useI18n();
const drawerWidth = computed(() => Math.min(440, window.innerWidth));

const loading = ref(false);
const saving = ref(false);
const testing = ref(false);
const testResult = ref(null);
const info = reactive({ masked_key: null, key_from_env: false, month_tokens: 0 });
const form = reactive({
  enabled: false,
  api_key: '',
  clear_key: false,
  model: 'claude-opus-5-5',
  effort: 'medium',
  insights_enabled: true,
  monthly_token_limit: null,
});

const modelOptions = [
  { value: 'claude-opus-5-5', label: 'Claude Opus 5.5' },
  { value: 'claude-sonnet-5-5', label: 'Claude Sonnet 5.5' },
  { value: 'claude-haiku-5-5', label: 'Claude Haiku 5.5' },
];
const effortOptions = computed(() => [
  { value: 'low', label: tf('Ai_Effort_Low', 'Fast') },
  { value: 'medium', label: tf('Ai_Effort_Medium', 'Balanced') },
  { value: 'high', label: tf('Ai_Effort_High', 'Thorough') },
]);

const keyHelp = computed(() => {
  if (info.key_from_env) return tf('Ai_Key_From_Env', 'Using ANTHROPIC_API_KEY from the server environment. Enter a key here to override it.');
  if (info.masked_key) return tf('Ai_Key_Stored', 'A key is stored (encrypted). Leave blank to keep it.');
  return tf('Ai_Key_Help', 'Create one at console.anthropic.com. It is stored encrypted and never sent to the browser.');
});
const usageText = computed(() =>
  `${tf('Ai_Used_This_Month', 'Used this month')}: ${Number(info.month_tokens || 0).toLocaleString()} ${tf('Ai_Tokens', 'tokens')}`
);

async function load() {
  loading.value = true;
  testResult.value = null;
  try {
    const data = await http.get('ai/settings');
    Object.assign(info, { masked_key: data.masked_key, key_from_env: data.key_from_env, month_tokens: data.month_tokens });
    Object.assign(form, {
      enabled: !!data.enabled,
      api_key: '',
      clear_key: false,
      model: data.model || 'claude-opus-5-5',
      effort: data.effort || 'medium',
      insights_enabled: !!data.insights_enabled,
      monthly_token_limit: data.monthly_token_limit ?? null,
    });
  } catch (e) {
    message.error(t('InvalidData'));
  } finally {
    loading.value = false;
  }
}

function clearKey() {
  form.clear_key = true;
  form.api_key = '';
  info.masked_key = null;
}

async function persist() {
  await http.put('ai/settings', { ...form, monthly_token_limit: form.monthly_token_limit || null });
  form.api_key = '';
  form.clear_key = false;
}

async function save() {
  saving.value = true;
  try {
    await persist();
    message.success(t('Successfully_Updated'));
    emit('saved');
    emit('update:open', false);
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    saving.value = false;
  }
}

/** Saves first, so the test uses exactly what is on screen. */
async function test() {
  testing.value = true;
  testResult.value = null;
  try {
    await persist();
    const data = await http.post('ai/settings/test', {});
    await load(); // refresh the masked key / usage (clears testResult)
    testResult.value = { ok: true, text: `${tf('Ai_Test_Ok', 'Connected')} — ${data.model}` };
    emit('saved');
  } catch (e) {
    testResult.value = { ok: false, text: e?.data?.message || tf('Ai_Request_Failed', 'Something went wrong. Please try again.') };
  } finally {
    testing.value = false;
  }
}
</script>

<style scoped>
.footer { display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap; }
</style>
