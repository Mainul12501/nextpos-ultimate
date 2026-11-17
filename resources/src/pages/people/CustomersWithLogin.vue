<template>
  <div class="page">
    <PageHeader
      :title="$t('Customers_with_Login')"
      :breadcrumb="[$t('Store'), $t('Customers_with_Login')]"
    />

    <a-alert
      type="info" show-icon style="margin-bottom: 16px"
      :message="$t('Online_Store_Accounts')"
      :description="$t('Customers_with_login_alert')"
    />

    <DataTable :crud="crud" :columns="columns">
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'status'">
          <a-tag :color="record.status ? 'success' : 'default'">
            {{ record.status ? $t('Active') : $t('Inactive') }}
          </a-tag>
        </template>
        <template v-else-if="column.key === 'is_blocked'">
          <a-tag :color="record.is_blocked ? 'error' : 'default'">
            {{ record.is_blocked ? $t('Yes') : $t('No') }}
          </a-tag>
        </template>
        <template v-else-if="column.key === 'two_factor'">
          <a-tag v-if="record.two_factor" color="success" style="margin: 0">{{ record.two_factor === 'email' ? tr('Cust_2fa_Email', 'Email') : tr('Cust_2fa_App', 'App') }}</a-tag>
          <span v-else style="opacity: 0.45">{{ tr('Tfa_Off', 'Off') }}</span>
        </template>
        <template v-else-if="column.key === 'actions'">
          <a-space>
            <a-tooltip :title="$t('Edit')">
              <a-button type="text" size="small" @click="openEdit(record)">
                <template #icon><EditOutlined style="color: #52c41a" /></template>
              </a-button>
            </a-tooltip>
            <a-tooltip v-if="record.two_factor" :title="tr('Cust_2fa_Reset', 'Turn off 2-step')">
              <a-button type="text" size="small" @click="resetTwoFactor(record, `ecommerce_clients/${record.id}/two-factor/reset`)">
                <template #icon><SafetyCertificateOutlined style="color: #fa8c16" /></template>
              </a-button>
            </a-tooltip>
            <a-tooltip :title="$t('Del')">
              <a-button type="text" size="small" danger @click="crud.remove(record, { label: record.client_name })">
                <template #icon><DeleteOutlined /></template>
              </a-button>
            </a-tooltip>
          </a-space>
        </template>
      </template>
    </DataTable>

    <!-- Edit store account -->
    <a-modal
      v-model:open="modalOpen"
      :title="$t('Edit') + ' - ' + (form.client_name || '')"
      :confirm-loading="submitting"
      :ok-text="$t('submit')"
      :cancel-text="$t('Delete_cancelButtonText')"
      @ok="submit"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical" style="margin-top: 8px">
        <a-form-item :label="$t('CustomerName')">
          <a-input :value="form.client_name" readonly />
        </a-form-item>
        <a-form-item :label="$t('Email') + ' *'" name="email"
          :validate-status="emailExist ? 'error' : undefined" :help="emailExist || undefined">
          <a-input v-model:value="form.email" @change="emailExist = ''" />
        </a-form-item>
        <a-form-item :label="$t('password')" name="password">
          <a-input-password v-model:value="form.password" :placeholder="$t('password')" />
        </a-form-item>
        <a-row :gutter="16">
          <a-col :span="12">
            <a-form-item :label="$t('Status')">
              <a-switch v-model:checked="form.status" />
              {{ form.status ? $t('Active') : $t('Inactive') }}
            </a-form-item>
          </a-col>
          <a-col :span="12">
            <a-form-item :label="$t('Blocked')">
              <a-switch v-model:checked="form.is_blocked" />
              {{ form.is_blocked ? $t('Yes') : $t('No') }}
            </a-form-item>
          </a-col>
        </a-row>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
/**
 * Customers with a store login — GET ecommerce_clients → {accounts,
 * totalRows} (rowsKey `accounts`). Edit modal: PUT ecommerce_clients/{id}
 * {email, password||null, status 1/0, is_blocked 1/0}; email-taken errors
 * (errors.email[0]) inline; other server field errors toast their first
 * message. DELETE ecommerce_clients/{id}. Legacy validation: email
 * required+email; password optional 6–32.
 * Rows carry two_factor ('email'|'app'|null); POST ecommerce_clients/{id}/two-factor/reset.
 */
import { ref, computed } from 'vue';
import { message, Modal } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import { EditOutlined, DeleteOutlined, SafetyCertificateOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import DataTable from '../../components/DataTable.vue';
import { useCrudTable } from '../../composables/useCrudTable';
import http from '../../lib/http';

const { t } = useI18n();
const tr = (key, fallback) => {
  const out = t(key);
  return out === key ? fallback : out;
};

const crud = useCrudTable('ecommerce_clients', { rowsKey: 'accounts' });
crud.fetchRows();

const columns = computed(() => [
  { title: t('Code'), dataIndex: 'client_code', key: 'client_code', sorter: true },
  { title: t('Name'), dataIndex: 'client_name', key: 'client_name', sorter: true },
  { title: t('Phone'), dataIndex: 'phone', key: 'phone' },
  { title: t('Email'), dataIndex: 'email', key: 'email' },
  { title: t('Status'), key: 'status', width: 100, align: 'center' },
  { title: t('Blocked'), key: 'is_blocked', width: 100, align: 'center' },
  { title: tr('Tfa_Column', '2FA'), key: 'two_factor', width: 90, align: 'center' },
  { title: t('Action'), key: 'actions', width: 130, align: 'center' },
]);

// Lost phone and recovery codes: turn the customer's two-step verification off.
function resetTwoFactor(record, url) {
  Modal.confirm({
    title: tr('Cust_2fa_Reset_Title', 'Turn off two-step verification?'),
    content: tr('Cust_2fa_Reset_Help', 'This customer will sign in with just their password and can turn it on again from their account. Do this only after confirming who is asking.'),
    okText: tr('Cust_2fa_Reset', 'Turn off 2-step'),
    okType: 'danger',
    cancelText: t('Cancel'),
    async onOk() {
      try {
        await http.post(url);
        record.two_factor = null;
        message.success(t('Successfully_Updated'));
      } catch (e) {
        message.error(e?.data?.message || t('InvalidData'));
      }
    },
  });
}


const modalOpen = ref(false);
const submitting = ref(false);
const formRef = ref();
const form = ref({});
const emailExist = ref('');

const rules = computed(() => ({
  email: [
    { required: true, message: t('Field_is_required') },
    { type: 'email', message: t('InvalidData') },
  ],
  password: [{ min: 6, max: 32, message: '6 – 32' }],
}));

function openEdit(record) {
  form.value = {
    id: record.id,
    client_name: record.client_name,
    email: record.email || '',
    password: '',
    status: !!record.status,
    is_blocked: !!record.is_blocked,
  };
  emailExist.value = '';
  modalOpen.value = true;
}

async function submit() {
  try {
    await formRef.value.validate();
  } catch (e) {
    message.error(t('Please_fill_the_form_correctly'));
    return;
  }
  submitting.value = true;
  emailExist.value = '';
  try {
    await http.put(`ecommerce_clients/${form.value.id}`, {
      email: form.value.email,
      password: form.value.password || null,
      status: form.value.status ? 1 : 0,
      is_blocked: form.value.is_blocked ? 1 : 0,
    });
    message.success(t('Updated_in_successfully'));
    modalOpen.value = false;
    crud.fetchRows();
  } catch (e) {
    const errors = e?.data?.errors;
    if (errors?.email?.length) {
      emailExist.value = String(errors.email[0]);
      message.error(emailExist.value);
    } else if (errors) {
      const firstVal = Object.values(errors)[0];
      message.error(String(Array.isArray(firstVal) ? firstVal[0] : firstVal) || t('InvalidData'));
    } else {
      message.error(t('InvalidData'));
    }
  } finally {
    submitting.value = false;
  }
}
</script>
