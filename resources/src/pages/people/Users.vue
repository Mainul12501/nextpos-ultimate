<template>
  <div class="page">
    <PageHeader :title="$t('Users')" :breadcrumb="[$t('User_Management'), $t('Users')]">
      <template #actions>
        <a-button v-if="auth.can('users_add')" type="primary" @click="$router.push('/users/create')">
          <template #icon><PlusOutlined /></template>
          {{ $t('Add') }}
        </a-button>
      </template>
    </PageHeader>

    <DataTable :crud="crud" :columns="columns">
      <template #toolbar>
        <a-select
          v-model:value="tfFilter" :options="tfFilterOptions" allow-clear style="width: 200px"
          :placeholder="tr('Tfa_Filter_All', '2FA: all users')" @change="crud.reload()"
        />
      </template>
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'avatar'">
          <a-avatar :size="36" :src="avatarUrl(record)">
            {{ initials(record) }}
          </a-avatar>
        </template>
        <template v-else-if="column.key === 'statut'">
          <!-- Activate/deactivate toggle, like the legacy switch. The logged-in
               user cannot deactivate their own account. -->
          <a-tooltip :title="isSelf(record) ? $t('People_Cannot_Deactivate_Self') : ''">
            <a-switch
              :checked="!!Number(record.statut)"
              :loading="switching === record.id"
              :disabled="isSelf(record)"
              @change="toggleStatus(record)"
            />
          </a-tooltip>
        </template>
        <template v-else-if="column.key === 'two_factor'">
          <a-tag v-if="record.two_factor_confirmed_at" color="success" style="margin: 0">{{ tr('Tfa_On', 'On') }}</a-tag>
          <a-tooltip v-else-if="record.two_factor_required" :title="tr('Tfa_Needs_Setup_Help', 'Required by System Settings > Security; asked to set it up at the next sign-in.')">
            <a-tag color="warning" style="margin: 0">{{ tr('Tfa_Needs_Setup', 'Needs setup') }}</a-tag>
          </a-tooltip>
          <span v-else style="opacity: 0.45">{{ tr('Tfa_Off', 'Off') }}</span>
        </template>
        <template v-else-if="column.key === 'actions'">
          <a-space>
            <a-tooltip :title="$t('Edit')">
              <a-button type="text" size="small" @click="$router.push(`/users/${record.id}/edit`)">
                <template #icon><EditOutlined style="color: #52c41a" /></template>
              </a-button>
            </a-tooltip>
            <!-- Lost phone + recovery codes: clear the user's 2FA so they can sign in and set it up again. -->
            <a-tooltip v-if="record.two_factor_confirmed_at && auth.can('users_edit')" :title="tr('Tfa_Reset', 'Reset two-factor')">
              <a-button type="text" size="small" @click="resetTwoFactor(record)">
                <template #icon><SafetyCertificateOutlined style="color: #fa8c16" /></template>
              </a-button>
            </a-tooltip>
            <!-- Legacy hides delete for the current user. -->
            <a-tooltip v-if="!isSelf(record)" :title="$t('Del')">
              <a-button type="text" size="small" danger @click="crud.remove(record)">
                <template #icon><DeleteOutlined /></template>
              </a-button>
            </a-tooltip>
          </a-space>
        </template>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
/**
 * Users list: GET users → { users, totalRows, roles, warehouses }.
 * Status toggle: PUT users_switch_activated/{id} { statut, id } (legacy
 * contract sends the NEW value). No bulk delete endpoint for users.
 * 2FA reset: POST users/{id}/two-factor/reset (TwoFactorController).
 * 2FA filter: two_factor=on|off|pending; rows carry two_factor_required.
 */
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { message, Modal } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import { PlusOutlined, EditOutlined, DeleteOutlined, SafetyCertificateOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import DataTable from '../../components/DataTable.vue';
import { useCrudTable } from '../../composables/useCrudTable';
import { useAuthStore } from '../../stores/auth';
import http from '../../lib/http';

const { t } = useI18n();
const tr = (key, fallback) => {
  const out = t(key);
  return out === key ? fallback : out;
};
const auth = useAuthStore();

// 2FA filter; Security settings links here with ?two_factor=pending.
const route = useRoute();
const tfFilter = ref(['on', 'off', 'pending'].includes(route.query.two_factor) ? route.query.two_factor : undefined);
const tfFilterOptions = computed(() => [
  { value: 'on', label: tr('Tfa_Filter_On', '2FA on') },
  { value: 'off', label: tr('Tfa_Filter_Off', '2FA off') },
  { value: 'pending', label: tr('Tfa_Filter_Pending', 'Needs setup (required)') },
]);

const crud = useCrudTable('users', { rowsKey: 'users', params: () => ({ two_factor: tfFilter.value || '' }) });
const switching = ref(null);

// The logged-in user may not deactivate or delete their own account (legacy parity).
const isSelf = record => auth.user && Number(record.id) === Number(auth.user.id);

// Avatars live under public/images/avatar/; the antd Avatar falls back to
// initials when the image is missing or fails to load.
const avatarUrl = record => (record.avatar ? `/images/avatar/${record.avatar}` : undefined);
const initials = record =>
  `${(record.firstname || '')[0] || ''}${(record.lastname || '')[0] || ''}`.toUpperCase()
  || (record.username || '?')[0].toUpperCase();

const columns = computed(() => [
  { title: t('Image'), key: 'avatar', dataIndex: 'avatar', width: 70, align: 'center', exportValue: r => r.avatar || '' },
  { title: t('Firstname'), dataIndex: 'firstname', key: 'firstname', sorter: true },
  { title: t('lastname'), dataIndex: 'lastname', key: 'lastname', sorter: true },
  { title: t('username'), dataIndex: 'username', key: 'username', sorter: true },
  { title: t('Email'), dataIndex: 'email', key: 'email' },
  { title: t('Phone'), dataIndex: 'phone', key: 'phone' },
  { title: t('Status'), key: 'statut', dataIndex: 'statut', exportValue: r => (Number(r.statut) ? t('ActivateUser') : t('DisActivateUser')) },
  { title: tr('Tfa_Column', '2FA'), key: 'two_factor', align: 'center', exportValue: r => (r.two_factor_confirmed_at ? tr('Tfa_On', 'On') : tr('Tfa_Off', 'Off')) },
  { title: t('Action'), key: 'actions', width: 130, align: 'center' },
]);

function resetTwoFactor(record) {
  Modal.confirm({
    title: tr('Tfa_Reset_Title', 'Reset two-factor authentication?'),
    content: tr('Tfa_Reset_Help', ':name will sign in with just their password and can then set up their authenticator app again. Do this only after confirming who is asking.')
      .replace(':name', record.username || record.email),
    okText: tr('Tfa_Reset', 'Reset two-factor'),
    okType: 'danger',
    cancelText: t('Cancel'),
    async onOk() {
      try {
        await http.post(`users/${record.id}/two-factor/reset`);
        record.two_factor_confirmed_at = null;
        message.success(t('Successfully_Updated'));
      } catch (e) {
        message.error(e?.data?.message || t('InvalidData'));
      }
    },
  });
}

async function toggleStatus(record) {
  if (isSelf(record)) return;
  switching.value = record.id;
  const next = Number(record.statut) ? 0 : 1;
  try {
    await http.put(`users_switch_activated/${record.id}`, { statut: next, id: record.id });
    record.statut = next;
    message.success(next ? t('ActivateUser') : t('DisActivateUser'));
  } catch (e) {
    message.error(t('InvalidData'));
  } finally {
    switching.value = null;
  }
}

onMounted(crud.fetchRows);
</script>
