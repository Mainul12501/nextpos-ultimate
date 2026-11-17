<template>
  <div class="page">
    <PageHeader :title="$t('Patients')" :breadcrumb="[$t('Hospital'), $t('Patients')]">
      <template #actions>
        <a-button @click="$router.push('/hospital/dashboard')">
          <template #icon><DashboardOutlined /></template>
          {{ $t('Dashboard') }}
        </a-button>
        <a-button v-if="canAdd" type="primary" @click="$router.push('/hospital/patients/create')">
          <template #icon><UserAddOutlined /></template>
          {{ $t('Hosp_Register_Patient') }}
        </a-button>
      </template>
    </PageHeader>

    <a-card size="small" style="margin-bottom: 16px">
      <div class="toolbar">
        <a-input-search
          v-model:value="crud.search.value"
          :placeholder="$t('Hosp_Search_Patient_Hint')"
          allow-clear class="tb-search" @search="crud.reload"
        />
        <a-select
          v-model:value="filters.status" class="tb-item" allow-clear
          :placeholder="$t('All_Status')" :options="patientStatuses" @change="crud.reload"
        />
        <a-select
          v-model:value="filters.gender" class="tb-item" allow-clear
          :placeholder="$t('Hosp_Any_Gender')" :options="genders" @change="crud.reload"
        />
        <a-select
          v-model:value="filters.blood_group" class="tb-item-sm" allow-clear
          :placeholder="$t('Hosp_Blood')" :options="BLOOD_GROUPS" @change="crud.reload"
        />
        <a-checkbox v-model:checked="filters.admitted" class="tb-check" @change="crud.reload">
          {{ $t('Hosp_Currently_Admitted_Short') }}
        </a-checkbox>
      </div>
    </a-card>

    <DataTable :crud="crud" :columns="columns" :searchable="false" :selectable="canDelete">
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'name'">
          <button type="button" class="cell-p" @click="open(record)">
            <a-avatar :size="36" :src="record.image_url" class="cell-avatar">
              {{ initials(record.name) }}
            </a-avatar>
            <span class="cell-text">
              <span class="cell-name">
                {{ record.name }}
                <a-tag v-if="record.is_admitted" color="processing" class="cell-badge">{{ $t('Hosp_Admitted') }}</a-tag>
              </span>
              <span class="cell-mrn">{{ record.mrn }}</span>
            </span>
          </button>
        </template>

        <template v-else-if="column.key === 'age'">
          <span v-if="record.age !== null">{{ record.age }}</span>
          <span v-else class="muted">—</span>
        </template>

        <template v-else-if="column.key === 'gender'">{{ labelOf(GENDERS, record.gender) }}</template>

        <template v-else-if="column.key === 'blood_group'">
          <a-tag v-if="record.blood_group" color="red">{{ record.blood_group }}</a-tag>
          <span v-else class="muted">—</span>
        </template>

        <template v-else-if="column.key === 'allergies'">
          <a-tooltip v-if="record.allergies" :title="record.allergies">
            <a-tag color="warning"><WarningOutlined /> {{ $t('Hosp_Allergies') }}</a-tag>
          </a-tooltip>
          <span v-else class="muted">—</span>
        </template>

        <template v-else-if="column.key === 'status'">
          <a-tag :color="optionOf(PATIENT_STATUSES, record.status).color">
            {{ labelOf(PATIENT_STATUSES, record.status) }}
          </a-tag>
        </template>

        <template v-else-if="column.key === 'actions'">
          <a-space :size="0">
            <a-tooltip :title="$t('Hosp_Open_Record')">
              <a-button type="text" size="small" @click="open(record)">
                <template #icon><EyeOutlined /></template>
              </a-button>
            </a-tooltip>
            <a-tooltip v-if="canEdit" :title="$t('Edit')">
              <a-button type="text" size="small" @click="$router.push(`/hospital/patients/${record.id}/edit`)">
                <template #icon><EditOutlined /></template>
              </a-button>
            </a-tooltip>
            <a-tooltip v-if="canDelete" :title="$t('Delete')">
              <a-button type="text" size="small" danger @click="crud.remove(record, { label: record.name })">
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
 * Patient register. The row is the entry point to a patient's whole record, so
 * the name cell — not a separate action — is the primary click target.
 *
 * "Currently admitted" is a server-side filter, not a column sort: it is the
 * ward round's worklist and needs to work across the whole register, not just
 * the visible page.
 */
import { reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import {
  UserAddOutlined, EditOutlined, DeleteOutlined, EyeOutlined,
  DashboardOutlined, WarningOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import DataTable from '../../components/DataTable.vue';
import { useCrudTable } from '../../composables/useCrudTable';
import { useAuthStore } from '../../stores/auth';
import { GENDERS, BLOOD_GROUPS, PATIENT_STATUSES, labelOf, optionOf, tr } from './hospitalOptions';
import { t } from '../../i18n';

const auth = useAuthStore();
const router = useRouter();

const canAdd = computed(() => auth.can('hms_patients_add'));
const canEdit = computed(() => auth.can('hms_patients_edit'));
const canDelete = computed(() => auth.can('hms_patients_delete'));

const genders = computed(() => tr(GENDERS));
const patientStatuses = computed(() => tr(PATIENT_STATUSES));

const filters = reactive({ status: undefined, gender: undefined, blood_group: undefined, admitted: false });

const crud = useCrudTable('hospital/patients', {
  rowsKey: 'patients',
  sortField: 'created_at',
  bulkDeleteEndpoint: 'hospital/patients/delete/by_selection',
  params: () => ({
    status: filters.status || '',
    gender: filters.gender || '',
    blood_group: filters.blood_group || '',
    admitted: filters.admitted ? 1 : '',
  }),
});

const columns = computed(() => [
  { title: t('Patient', 'Patient'), key: 'name', dataIndex: 'name', sorter: true },
  { title: t('Age', 'Age'), key: 'age', dataIndex: 'age', width: 80 },
  { title: t('Gender', 'Gender'), key: 'gender', dataIndex: 'gender', sorter: true, width: 110 },
  { title: t('Hosp_Blood', 'Blood'), key: 'blood_group', dataIndex: 'blood_group', width: 100 },
  { title: t('Phone', 'Phone'), dataIndex: 'phone', key: 'phone', sorter: true, width: 150 },
  { title: t('Alerts', 'Alerts'), key: 'allergies', width: 130 },
  { title: t('Status', 'Status'), key: 'status', dataIndex: 'status', sorter: true, width: 120 },
  { title: '', key: 'actions', width: 120 },
]);

function open(patient) {
  router.push(`/hospital/patients/${patient.id}`);
}

/** Fallback avatar content when a patient has no photo. */
function initials(name) {
  return String(name || '?')
    .split(/\s+/).filter(Boolean).slice(0, 2)
    .map(part => part[0].toUpperCase()).join('');
}

onMounted(crud.fetchRows);
</script>

<style scoped>
.muted {
  color: rgba(128, 128, 128, 0.7);
}
.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}
.tb-search {
  flex: 1 1 240px;
  min-width: 200px;
}
.tb-item {
  width: 150px;
}
.tb-item-sm {
  width: 100px;
}
.tb-check {
  white-space: nowrap;
}
.cell-p {
  display: flex;
  align-items: center;
  gap: 10px;
  border: 0;
  background: none;
  padding: 0;
  cursor: pointer;
  color: inherit;
  font: inherit;
  text-align: left;
  min-width: 200px;
}
.cell-avatar {
  flex: none;
  background: rgba(var(--app-primary-rgb, 109, 40, 217), 0.15);
  color: var(--app-primary, #6d28d9);
  font-size: 13px;
}
.cell-text {
  min-width: 0;
}
.cell-name {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 500;
}
.cell-badge {
  margin-inline-end: 0;
  font-size: 10.5px;
}
.cell-mrn {
  display: block;
  font-size: 11.5px;
  opacity: 0.55;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.cell-p:hover .cell-name {
  color: var(--app-primary, #6d28d9);
}
@media (max-width: 767px) {
  .tb-item,
  .tb-item-sm {
    width: 100%;
  }
}
</style>
