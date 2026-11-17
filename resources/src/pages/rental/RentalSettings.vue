<template>
  <div class="page">
    <PageHeader
      :title="$t('Rental_Settings')"
      :subtitle="$t('Rental_Settings_Subtitle')"
      :breadcrumb="[$t('Rental_Management'), $t('Rental_Settings')]"
    >
      <template #actions>
        <a-button type="primary" :loading="saving" :disabled="loading || !loaded" @click="save">
          <template #icon><SaveOutlined /></template>
          {{ $t('Save') }}
        </a-button>
      </template>
    </PageHeader>

    <div v-if="loading" class="loading"><a-spin size="large" /></div>

    <a-form v-else ref="formRef" :model="form" :rules="rules" layout="vertical">
      <a-row :gutter="16">
        <a-col :xs="24" :lg="12">
          <!-- Numbering -->
          <a-card size="small" class="block">
            <template #title>
              <span class="card-title"><NumberOutlined /> {{ $t('Rental_Numbering') }}</span>
            </template>
            <p class="card-help">{{ $t('Rental_Numbering_Help') }}</p>
            <a-row :gutter="16">
              <a-col v-for="p in prefixes" :key="p.field" :xs="24" :sm="12">
                <a-form-item :label="p.label" :name="p.field">
                  <a-input v-model:value="form[p.field]" :maxlength="16" @blur="upper(p.field)">
                    <template #suffix>
                      <span class="preview">{{ (form[p.field] || '').toUpperCase() }}-0001</span>
                    </template>
                  </a-input>
                </a-form-item>
              </a-col>
            </a-row>
          </a-card>

          <!-- Deposits & scheduling -->
          <a-card size="small" class="block">
            <template #title>
              <span class="card-title"><SafetyCertificateOutlined /> {{ $t('Rental_Deposits_Scheduling') }}</span>
            </template>
            <a-row :gutter="16">
              <a-col :xs="24" :sm="12">
                <a-form-item :label="$t('Rental_Default_Deposit')" name="default_deposit" :extra="$t('Rental_Default_Deposit_Help')">
                  <a-input-number v-model:value="form.default_deposit" :min="0" style="width: 100%" />
                </a-form-item>
              </a-col>
              <a-col :xs="24" :sm="12">
                <a-form-item :label="$t('Rental_Buffer_Time')" name="buffer_hours" :extra="$t('Rental_Buffer_Time_Help')">
                  <a-input-number v-model:value="form.buffer_hours" :min="0" :max="720" :precision="0" style="width: 100%" :addon-after="$t('Rental_Unit_Hours')" />
                </a-form-item>
              </a-col>
              <a-col :xs="24" :sm="12">
                <a-form-item :label="$t('Rental_Default_Hold')" name="default_hold_hours" :extra="$t('Rental_Default_Hold_Help')">
                  <a-input-number v-model:value="form.default_hold_hours" :min="1" :max="2160" :precision="0" style="width: 100%" :addon-after="$t('Rental_Unit_Hours')" />
                </a-form-item>
              </a-col>
            </a-row>
          </a-card>

          <!-- Alerts -->
          <a-card size="small" class="block">
            <template #title>
              <span class="card-title"><BellOutlined /> {{ $t('Rental_Alerts_Reminders') }}</span>
            </template>
            <a-row :gutter="16">
              <a-col :xs="24" :sm="12">
                <a-form-item :label="$t('Rental_Expiring_Soon')" name="expiring_soon_days" :extra="$t('Rental_Expiring_Soon_Help')">
                  <a-input-number v-model:value="form.expiring_soon_days" :min="1" :max="365" :precision="0" style="width: 100%" :addon-after="$t('Rental_Unit_Days')" />
                </a-form-item>
              </a-col>
              <a-col :xs="24" :sm="12">
                <a-form-item :label="$t('Rental_Reminder_Before')" name="reminder_days_before" :extra="$t('Rental_Reminder_Before_Help')">
                  <a-input-number v-model:value="form.reminder_days_before" :min="0" :max="90" :precision="0" style="width: 100%" :addon-after="$t('Rental_Unit_Days')" />
                </a-form-item>
              </a-col>
              <a-col :xs="24" :sm="12">
                <a-form-item :label="$t('Rental_Invoice_Lead')" name="invoice_lead_days" :extra="$t('Rental_Invoice_Lead_Help')">
                  <a-input-number v-model:value="form.invoice_lead_days" :min="0" :max="90" :precision="0" style="width: 100%" :addon-after="$t('Rental_Unit_Days')" />
                </a-form-item>
              </a-col>
            </a-row>
          </a-card>
        </a-col>

        <a-col :xs="24" :lg="12">
          <!-- Late payments -->
          <a-card size="small" class="block">
            <template #title>
              <span class="card-title"><FieldTimeOutlined /> {{ $t('Rental_Late_Payments') }}</span>
            </template>
            <a-form-item :label="$t('Rental_Grace_Period')" name="grace_days" :extra="$t('Rental_Grace_Period_Help')">
              <a-input-number v-model:value="form.grace_days" :min="0" :max="365" :precision="0" style="width: 220px" :addon-after="$t('Rental_Unit_Days')" />
            </a-form-item>
            <a-form-item :label="$t('Rental_Late_Fee')">
              <a-segmented v-model:value="form.late_fee_type" :options="feeTypeOptions" block />
            </a-form-item>
            <a-row v-if="form.late_fee_type !== 'none'" :gutter="16">
              <a-col :xs="24" :sm="12">
                <a-form-item :label="form.late_fee_type === 'percent' ? $t('Rental_Late_Fee_Percent') : $t('Rental_Late_Fee_Amount')" name="late_fee_value">
                  <a-input-number
                    v-model:value="form.late_fee_value" :min="0" :max="form.late_fee_type === 'percent' ? 100 : undefined"
                    style="width: 100%" :addon-after="form.late_fee_type === 'percent' ? '%' : undefined"
                  />
                </a-form-item>
              </a-col>
              <a-col :xs="24" :sm="12">
                <a-form-item :label="$t('Rental_Late_Fee_Charged')">
                  <a-select v-model:value="form.late_fee_frequency" :options="frequencyOptions" />
                </a-form-item>
              </a-col>
            </a-row>
            <a-alert type="info" show-icon :message="lateFeeSummary" class="summary" />
          </a-card>

          <!-- Reminders -->
          <a-card size="small" class="block">
            <template #title>
              <span class="card-title"><NotificationOutlined /> {{ $t('Rental_Reminders') }}</span>
            </template>
            <template #extra><a-switch v-model:checked="form.reminders_enabled" /></template>
            <p class="card-help">{{ $t('Rental_Reminders_Help') }}</p>
            <div :class="{ dim: !form.reminders_enabled }">
              <a-form-item :label="$t('Rental_Channels')">
                <a-checkbox-group v-model:value="form.reminder_channels">
                  <a-checkbox value="email">{{ $t('Email') }}</a-checkbox>
                  <a-checkbox value="sms">{{ $t('SMS') }}</a-checkbox>
                  <a-checkbox value="whatsapp" :disabled="!form.whatsapp_active">WhatsApp<span v-if="!form.whatsapp_active" class="muted"> ({{ $t('Rental_Not_Connected') }})</span></a-checkbox>
                </a-checkbox-group>
              </a-form-item>
              <a-row :gutter="16">
                <a-col :xs="24" :sm="12">
                  <a-form-item :label="$t('Rental_Overdue_Every')">
                    <a-input-number v-model:value="form.overdue_reminder_every_days" :min="1" :max="90" :precision="0" style="width: 100%" :addon-after="$t('Rental_Unit_Days')" />
                  </a-form-item>
                </a-col>
                <a-col :xs="24" :sm="12">
                  <a-form-item :label="$t('Rental_Expiry_Reminder')">
                    <a-input-number v-model:value="form.expiry_reminder_days" :min="1" :max="365" :precision="0" style="width: 100%" :addon-after="$t('Rental_Unit_Days')" />
                  </a-form-item>
                </a-col>
              </a-row>
              <a-tabs v-model:activeKey="tplTab" size="small">
                <a-tab-pane v-for="tt in TEMPLATE_TYPES" :key="tt.value" :tab="tt.label">
                  <a-form-item :label="$t('Rental_Email_Subject')">
                    <a-input v-model:value="form.reminder_templates[tt.value].subject" />
                  </a-form-item>
                  <a-form-item :label="$t('Rental_Message')" :extra="`${$t('Rental_Reminder_Placeholders')} ${PLACEHOLDERS}`" class="no-mb">
                    <a-textarea v-model:value="form.reminder_templates[tt.value].body" :rows="5" />
                  </a-form-item>
                </a-tab-pane>
              </a-tabs>
              <p class="cron-note"><InfoCircleOutlined /> {{ $t('Rental_Reminders_Cron') }} <code>php artisan rental:send-reminders</code></p>
            </div>
          </a-card>

          <!-- Handover checklists -->
          <a-card size="small" class="block">
            <template #title>
              <span class="card-title"><CheckSquareOutlined /> {{ $t('Rental_Handover_Checklists') }}</span>
            </template>
            <p class="card-help">{{ $t('Rental_Handover_Checklists_Help') }}</p>
            <a-form-item :label="$t('Rental_Checkout_Checklist')">
              <a-select v-model:value="form.checkout_checklist" mode="tags" :token-separators="[';']" :open="false" />
            </a-form-item>
            <a-form-item :label="$t('Rental_Checkin_Checklist')" class="no-mb">
              <a-select v-model:value="form.checkin_checklist" mode="tags" :token-separators="[';']" :open="false" />
            </a-form-item>
          </a-card>

          <!-- Agreement terms -->
          <a-card size="small" class="block">
            <template #title>
              <span class="card-title"><FileTextOutlined /> {{ $t('Rental_Agreement_Terms') }}</span>
            </template>
            <p class="card-help">{{ $t('Rental_Agreement_Terms_Help') }}</p>
            <a-textarea v-model:value="form.agreement_terms" :rows="10" :placeholder="$t('Rental_Agreement_Terms_Placeholder')" />
          </a-card>
        </a-col>
      </a-row>
    </a-form>
  </div>
</template>

<script setup>
/**
 * Rental module settings — GET/PUT rental/settings. One row of numbering
 * prefixes, late-fee policy, deposit default, booking buffer and the alert
 * windows the later phases (contracts, payments, reminders) read.
 */
import { ref, computed, onMounted } from 'vue';
import { message } from 'ant-design-vue';
import {
  SaveOutlined, NumberOutlined, SafetyCertificateOutlined, BellOutlined,
  FieldTimeOutlined, FileTextOutlined, CheckSquareOutlined, NotificationOutlined, InfoCircleOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import { useFormat } from '../../composables/useFormat';
import http from '../../lib/http';
import { t, i18n } from '../../i18n';

const { money } = useFormat();

/** t() with named {params}; the English fallback gets the same substitution. */
function tp(key, fallback, params) {
  const out = i18n.global.t(key, params);
  if (out !== key) return out;
  return Object.entries(params).reduce((s, [k, v]) => s.replace(`{${k}}`, v), fallback);
}

const formRef = ref();
const tplTab = ref('rent_due');
// Shown literally (never through i18n, which would treat {x} as a parameter).
const PLACEHOLDERS = '{customer_name} {contract_number} {invoice_number} {amount} {due_date} {days_late} {end_date} {items} {company_name} {company_phone}';
const TEMPLATE_TYPES = computed(() => [
  { value: 'rent_due', label: t('Rental_Rem_Rent_Due', 'Rent due') },
  { value: 'rent_overdue', label: t('Rental_Rem_Rent_Overdue', 'Rent overdue') },
  { value: 'contract_expiring', label: t('Rental_Rem_Expiring', 'Contract ending') },
  { value: 'return_due', label: t('Rental_Rem_Return_Due', 'Return due') },
]);
const loading = ref(false);
const saving = ref(false);
const form = ref({
  item_code_prefix: 'RI', contract_prefix: 'RNT', reservation_prefix: 'RSV', invoice_prefix: 'RINV',
  grace_days: 3, late_fee_type: 'none', late_fee_value: 0, late_fee_frequency: 'once',
  default_deposit: 0, buffer_hours: 0, default_hold_hours: 48, expiring_soon_days: 30, reminder_days_before: 3, invoice_lead_days: 7, checkout_checklist: [], checkin_checklist: [],
  reminders_enabled: false, reminder_channels: ['email'], overdue_reminder_every_days: 3, expiry_reminder_days: 14, whatsapp_active: false,
  reminder_templates: { rent_due: { subject: '', body: '' }, rent_overdue: { subject: '', body: '' }, contract_expiring: { subject: '', body: '' }, return_due: { subject: '', body: '' } },
  agreement_terms: '',
});

const prefixes = computed(() => [
  { field: 'item_code_prefix', label: t('Rental_Item_Code_Prefix', 'Item code prefix') },
  { field: 'contract_prefix', label: t('Rental_Contract_Prefix', 'Contract prefix') },
  { field: 'reservation_prefix', label: t('Rental_Reservation_Prefix', 'Reservation prefix') },
  { field: 'invoice_prefix', label: t('Rental_Invoice_Prefix', 'Invoice prefix') },
]);

const feeTypeOptions = computed(() => [
  { value: 'none', label: t('Rental_Late_Fee_None', 'No late fee') },
  { value: 'fixed', label: t('Rental_Late_Fee_Fixed', 'Fixed amount') },
  { value: 'percent', label: t('Rental_Late_Fee_Percentage', 'Percentage') },
]);

const frequencyOptions = computed(() => [
  { value: 'once', label: t('Rental_Late_Fee_Once', 'Once per late payment') },
  { value: 'daily', label: t('Rental_Late_Fee_Daily', 'Every day it stays late') },
]);

/** Plain-language read-back of the policy, so the numbers can't be misread. */
const lateFeeSummary = computed(() => {
  const f = form.value;
  const grace = tp('Rental_Summary_Grace', 'A payment becomes late {days} day(s) after its due date.', { days: f.grace_days ?? 0 });
  if (f.late_fee_type === 'none') {
    return `${grace} ${t('Rental_Summary_No_Fee', 'No late fee is charged.')}`;
  }
  const amount = f.late_fee_type === 'percent'
    ? tp('Rental_Summary_Percent', '{value}% of the overdue amount', { value: f.late_fee_value || 0 })
    : money(f.late_fee_value || 0);
  const when = f.late_fee_frequency === 'daily'
    ? t('Rental_Summary_Daily', 'is added for every day it stays unpaid.')
    : t('Rental_Summary_Once', 'is added once.');
  return `${grace} ${amount} ${when}`;
});

const PREFIX_RULE = () => [
  { required: true, message: t('Field_is_required', 'This field is required') },
  { pattern: /^[A-Za-z0-9_]+$/, message: t('Rental_Prefix_Rule', 'Letters, digits and underscores only') },
];
const rules = computed(() => ({
  item_code_prefix: PREFIX_RULE(),
  contract_prefix: PREFIX_RULE(),
  reservation_prefix: PREFIX_RULE(),
  invoice_prefix: PREFIX_RULE(),
}));

function upper(field) {
  form.value[field] = (form.value[field] || '').toUpperCase().trim();
}

// Save stays off until the real settings arrived, so defaults never overwrite them.
const loaded = ref(false);

async function load() {
  loading.value = true;
  try {
    const data = await http.get('rental/settings');
    if (data?.settings) {
      form.value = { ...form.value, ...data.settings, agreement_terms: data.settings.agreement_terms || '' };
      loaded.value = true;
    }
  } catch (e) {
    message.error(t('Rental_Could_Not_Load_Settings', 'Could not load the rental settings'));
  } finally {
    loading.value = false;
  }
}

async function save() {
  try {
    await formRef.value.validate();
  } catch (e) {
    return;
  }
  saving.value = true;
  try {
    const data = await http.put('rental/settings', form.value);
    if (data?.settings) form.value = { ...form.value, ...data.settings, agreement_terms: data.settings.agreement_terms || '' };
    message.success(t('Updated_in_successfully', 'Updated successfully'));
  } catch (e) {
    const errors = e?.data?.errors;
    const first = errors ? Object.values(errors)[0]?.[0] : null;
    message.error(first || e?.data?.message || t('Rental_Could_Not_Save_Settings', 'Could not save the settings'));
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.loading {
  display: flex;
  justify-content: center;
  padding: 96px 0;
}
.block {
  margin-bottom: 16px;
}
.card-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.card-title :deep(.anticon) {
  color: var(--app-primary, #6d28d9);
}
.card-help {
  margin: 0 0 14px;
  font-size: 12.5px;
  opacity: 0.65;
}
.preview {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 11.5px;
  opacity: 0.5;
}
.summary {
  margin-top: 4px;
}
.no-mb {
  margin-bottom: 0;
}
.dim {
  opacity: 0.55;
}
.muted {
  opacity: 0.6;
}
.cron-note {
  margin: 12px 0 0;
  font-size: 12px;
  opacity: 0.7;
}
.cron-note code {
  font-size: 11.5px;
}
</style>
