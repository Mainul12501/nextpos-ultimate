<template>
  <div class="page">
    <PageHeader :title="$t('Set_Environment_Env')" :breadcrumb="[$t('Settings'), $t('Set_Environment_Env')]" />

    <a-skeleton v-if="loading" active :paragraph="{ rows: 8 }" />

    <template v-else>
      <a-alert
        v-if="!writable" type="error" show-icon style="margin-bottom: 16px"
        :message="$t('Set_Env_Not_Writable')"
        :description="$t('Set_Env_Not_Writable_Desc')"
      />
      <a-alert
        v-else type="info" show-icon style="margin-bottom: 16px"
        :message="$t('Set_Env_Written_Directly')"
        :description="$t('Set_Env_Written_Directly_Desc', { path: 'storage/app/env-backups' })"
      />

      <a-card v-for="group in groups" :key="group.key" size="small" :title="group.title" style="margin-bottom: 16px">
        <div v-if="group.description" class="group-desc">{{ group.description }}</div>
        <a-alert
          v-if="group.key === 'queue' && retryWarning" type="warning" show-icon style="margin-bottom: 12px"
          :message="$t('Set_Env_Retry_Warning')"
        />

        <div v-for="f in group.fields" :key="f.key" class="env-row">
          <div class="env-meta">
            <div class="env-label">
              {{ f.label }}
              <a-tag v-if="dirty(f)" color="processing" style="margin-left: 6px">{{ $t('Set_Modified') }}</a-tag>
            </div>
            <code class="env-key">{{ f.key }}</code>
            <div class="env-hint">{{ f.hint }}</div>
            <div v-if="f.overridden_by" class="env-override">
              <i18n-t keypath="Set_Env_Overridden_By" tag="span">
                <template #field>{{ f.overridden_by.label }}</template>
                <template #value><b>{{ fmt(f.overridden_by.value) }}</b></template>
              </i18n-t>
            </div>
          </div>

          <div class="env-input">
            <a-switch v-if="f.type === 'boolean'" v-model:checked="form[f.key]" :disabled="!writable" />
            <a-select
              v-else-if="f.type === 'select'" v-model:value="form[f.key]" :options="f.options"
              :disabled="!writable" style="width: 100%"
            />
            <a-input-number
              v-else-if="f.type === 'integer'" v-model:value="form[f.key]" :min="f.min" :max="f.max"
              :placeholder="placeholder(f)" :disabled="!writable" style="width: 100%"
            />
            <a-input
              v-else v-model:value="form[f.key]" :placeholder="placeholder(f)" :disabled="!writable" allow-clear
            />
            <div v-if="f.type === 'integer'" class="env-range">{{ f.min }} – {{ f.max }}</div>
          </div>
        </div>
      </a-card>

      <a-card size="small">
        <a-space>
          <a-button type="primary" :loading="saving" :disabled="!writable || !hasChanges" @click="save">
            {{ $t('submit') }}
          </a-button>
          <a-button :disabled="!hasChanges || saving" @click="reset">{{ $t('Reset') }}</a-button>
        </a-space>
      </a-card>
    </template>
  </div>
</template>

<script setup>
/**
 * GET environment_settings → {groups: [{key, title, description, fields:
 * [{key, type, label, hint, default, min, max, options, value, in_file,
 * overridden_by}]}], writable, config_cached}.
 * PUT environment_settings {values: {ENV_KEY: value|null}} — only changed keys
 * are sent; null/blank removes the key from .env. The server whitelists keys,
 * clears a cached config and restarts queue workers after writing.
 */
import { ref, computed, onMounted } from 'vue';
import { message } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import PageHeader from '../../components/PageHeader.vue';
import http from '../../lib/http';

const { t } = useI18n();

const loading = ref(true);
const saving = ref(false);
const writable = ref(false);
const groups = ref([]);
const form = ref({});
const original = ref({});

const fields = computed(() => groups.value.flatMap(g => g.fields));

const norm = v => (v === undefined || v === '' ? null : v);
const dirty = f => norm(form.value[f.key]) !== norm(original.value[f.key]);
const hasChanges = computed(() => fields.value.some(dirty));

const retryWarning = computed(() => {
  const retry = form.value.QUEUE_RETRY_AFTER ?? 1200;
  const timeout = form.value.QUEUE_WORKER_TIMEOUT ?? 1200;
  return retry <= timeout;
});

const fmt = v => (typeof v === 'boolean' ? (v ? t('Set_On_Lower') : t('Set_Off_Lower')) : String(v));
const placeholder = f => (f.default === null || f.default === undefined ? t('Fleet_Not_Set') : t('Set_Default_Value', { value: fmt(f.default) }));

async function load() {
  loading.value = true;
  try {
    const data = await http.get('environment_settings');
    groups.value = data.groups || [];
    writable.value = !!data.writable;
    const values = {};
    fields.value.forEach((f) => {
      // A switch needs a boolean; an absent boolean key shows its default.
      values[f.key] = f.type === 'boolean' && f.value === null ? !!f.default : f.value;
    });
    form.value = { ...values };
    original.value = { ...values };
  } catch (e) {
    message.error(e?.data?.message || t('Set_Env_Load_Failed'));
  } finally {
    loading.value = false;
  }
}

function reset() {
  form.value = { ...original.value };
}

async function save() {
  const values = {};
  fields.value.filter(dirty).forEach((f) => { values[f.key] = norm(form.value[f.key]); });
  saving.value = true;
  try {
    const res = await http.put('environment_settings', { values });
    message.success(res?.changed?.length ? t('Set_Env_Saved_N', { n: res.changed.length }) : t('Set_Nothing_Changed'));
    original.value = { ...form.value };
  } catch (e) {
    const errors = e?.data?.errors;
    message.error(errors ? Object.values(errors).flat()[0] : (e?.data?.message || t('Set_Save_Failed')));
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.group-desc { color: #8c8c8c; font-size: 13px; margin-bottom: 8px; }
.env-row {
  display: flex; gap: 24px; align-items: flex-start; justify-content: space-between;
  padding: 12px 0; border-top: 1px solid rgba(128, 128, 128, 0.15);
}
.env-row:first-of-type { border-top: none; }
.env-meta { flex: 1; min-width: 0; }
.env-label { font-weight: 500; }
.env-key { font-size: 11px; opacity: 0.7; }
.env-hint { color: #8c8c8c; font-size: 12px; margin-top: 2px; }
.env-override { color: #d48806; font-size: 12px; margin-top: 4px; }
.env-input { width: 240px; flex-shrink: 0; }
.env-range { color: #8c8c8c; font-size: 11px; margin-top: 2px; text-align: right; }
@media (max-width: 640px) {
  .env-row { flex-direction: column; gap: 8px; }
  .env-input { width: 100%; }
}
</style>
