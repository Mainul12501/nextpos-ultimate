<template>
  <div class="page">
    <PageHeader :title="$t('Maintenance')" :breadcrumb="[$t('Fleet Management'), $t('Maintenance')]">
      <template #actions>
        <a-button type="primary" @click="openForm(null)">
          <template #icon><PlusOutlined /></template>
          {{ $t('Add') }}
        </a-button>
      </template>
    </PageHeader>

    <a-card size="small" style="margin-bottom: 16px">
      <div class="toolbar">
        <a-input-search
          v-model:value="crud.search.value" :placeholder="$t('Fleet_Search_Work_Vendor')"
          allow-clear class="tb-search" @search="crud.reload"
        />
        <a-select
          v-model:value="filters.vehicle_id" class="tb-item" allow-clear show-search
          option-filter-prop="label" :placeholder="$t('Fleet_All_Vehicles')"
          :options="vehicleOptions" @change="crud.reload"
        />
        <a-select
          v-model:value="filters.type" class="tb-item" allow-clear
          :placeholder="$t('All_types')" :options="maintenanceTypes" @change="crud.reload"
        />
        <a-select
          v-model:value="filters.status" class="tb-item" allow-clear
          :placeholder="$t('All_Status')" :options="maintenanceStatuses" @change="crud.reload"
        />
        <a-range-picker v-model:value="filters.range" class="tb-range" value-format="YYYY-MM-DD" @change="crud.reload" />
        <a-tag v-if="totalCost" color="purple" class="tb-total">{{ $t('Fleet_Total_Amount', { amount: money(totalCost) }) }}</a-tag>
      </div>
    </a-card>

    <DataTable :crud="crud" :columns="columns" :searchable="false" selectable>
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'vehicle'">
          <a class="link" @click="$router.push(`/fleet/vehicles/${record.vehicle_id}`)">{{ record.vehicle_name }}</a>
        </template>
        <template v-else-if="column.key === 'type'">
          <a-tag :color="optionOf(MAINTENANCE_TYPES, record.type).color">
            {{ labelOf(MAINTENANCE_TYPES, record.type) }}
          </a-tag>
        </template>
        <template v-else-if="column.key === 'status'">
          <a-tag :color="optionOf(MAINTENANCE_STATUSES, record.status).color">
            {{ labelOf(MAINTENANCE_STATUSES, record.status) }}
          </a-tag>
        </template>
        <template v-else-if="column.key === 'service_date'">{{ date(record.service_date) }}</template>
        <template v-else-if="column.key === 'odometer'">
          {{ record.odometer ? number(record.odometer, 0) : '—' }}
        </template>
        <template v-else-if="column.key === 'cost'">{{ money(record.cost) }}</template>
        <template v-else-if="column.key === 'next'">
          <div v-if="record.next_service_date || record.next_service_odometer" class="next">
            <span v-if="record.next_service_date">{{ date(record.next_service_date) }}</span>
            <span v-if="record.next_service_odometer" class="next-odo">
              @ {{ number(record.next_service_odometer, 0) }}
            </span>
          </div>
          <span v-else class="muted">—</span>
        </template>
        <template v-else-if="column.key === 'actions'">
          <a-space :size="0">
            <a-tooltip :title="$t('Edit')">
              <a-button type="text" size="small" @click="openForm(record)">
                <template #icon><EditOutlined /></template>
              </a-button>
            </a-tooltip>
            <a-tooltip :title="$t('Delete')">
              <a-button type="text" size="small" danger @click="crud.remove(record, { label: record.title })">
                <template #icon><DeleteOutlined /></template>
              </a-button>
            </a-tooltip>
          </a-space>
        </template>
      </template>
    </DataTable>

    <a-modal
      :open="formOpen" :title="editing ? $t('Fleet_Edit_Maintenance') : $t('Fleet_Record_Maintenance')" :width="640"
      :confirm-loading="saving" :ok-text="$t('submit')" :cancel-text="$t('Cancel')"
      @ok="submit" @cancel="formOpen = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-row :gutter="16">
          <a-col :xs="24" :md="12">
            <a-form-item :label="$t('Vehicle') + ' *'" name="vehicle_id">
              <a-select
                v-model:value="form.vehicle_id" show-search option-filter-prop="label"
                :options="vehicleOptions" :placeholder="$t('Fleet_Select_Vehicle')" @change="onVehicleChange"
              />
            </a-form-item>
          </a-col>
          <a-col :xs="24" :md="12">
            <a-form-item :label="$t('Rental_Work') + ' *'" name="title">
              <a-input v-model:value="form.title" :placeholder="$t('Fleet_Work_Placeholder')" allow-clear />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Type') + ' *'" name="type">
              <a-select v-model:value="form.type" :options="maintenanceTypes" />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Status') + ' *'" name="status">
              <a-select v-model:value="form.status" :options="maintenanceStatuses" />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Date') + ' *'" name="service_date">
              <a-date-picker v-model:value="form.service_date" style="width: 100%" value-format="YYYY-MM-DD" />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Fleet_Odometer')" name="odometer">
              <a-input-number v-model:value="form.odometer" :min="0" style="width: 100%" />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Cost')" name="cost">
              <a-input-number v-model:value="form.cost" :min="0" :step="1" style="width: 100%" />
            </a-form-item>
          </a-col>
          <a-col :xs="24" :md="8">
            <a-form-item :label="$t('Fleet_Vendor_Garage')">
              <a-input v-model:value="form.vendor" allow-clear />
            </a-form-item>
          </a-col>
          <a-col :xs="24" :md="12">
            <a-form-item :label="$t('Fleet_Next_Service_Date')" :extra="$t('Fleet_Next_Service_Date_Help')">
              <a-date-picker v-model:value="form.next_service_date" style="width: 100%" value-format="YYYY-MM-DD" />
            </a-form-item>
          </a-col>
          <a-col :xs="24" :md="12">
            <a-form-item :label="$t('Fleet_Next_Service_Odometer')">
              <a-input-number v-model:value="form.next_service_odometer" :min="0" style="width: 100%" />
            </a-form-item>
          </a-col>
          <a-col :span="24">
            <a-form-item :label="$t('Note')" style="margin-bottom: 0">
              <a-textarea v-model:value="form.notes" :rows="2" allow-clear />
            </a-form-item>
          </a-col>
        </a-row>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
/**
 * Service and repair log for the whole fleet, with a per-vehicle filter that
 * the vehicle detail page links into.
 *
 * Picking a vehicle prefills the odometer with its current reading — the number
 * is nearly always "whatever the dashboard says right now", and typing it again
 * is how logs end up with typos.
 */
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { message } from 'ant-design-vue';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import DataTable from '../../components/DataTable.vue';
import { useCrudTable } from '../../composables/useCrudTable';
import { useFormat } from '../../composables/useFormat';
import { MAINTENANCE_TYPES, MAINTENANCE_STATUSES, labelOf, optionOf, tr } from './fleetOptions';
import http from '../../lib/http';
import { t } from '../../i18n';

const route = useRoute();
const { money, number, date } = useFormat();

const filters = reactive({
  vehicle_id: route.query.vehicle_id ? Number(route.query.vehicle_id) : undefined,
  type: undefined,
  status: undefined,
  range: null,
});

const crud = useCrudTable('fleet/maintenances', {
  rowsKey: 'maintenances',
  sortField: 'service_date',
  params: () => ({
    vehicle_id: filters.vehicle_id || '',
    type: filters.type || '',
    status: filters.status || '',
    start_date: filters.range?.[0] || '',
    end_date: filters.range?.[1] || '',
  }),
});

const totalCost = computed(() => crud.payload.value?.total_cost || 0);

const maintenanceTypes = computed(() => tr(MAINTENANCE_TYPES));
const maintenanceStatuses = computed(() => tr(MAINTENANCE_STATUSES));

const columns = computed(() => [
  { title: t('Date', 'Date'), key: 'service_date', dataIndex: 'service_date', sorter: true, width: 110 },
  { title: t('Vehicle', 'Vehicle'), key: 'vehicle', dataIndex: 'vehicle_name', width: 200 },
  { title: t('Rental_Work', 'Work'), dataIndex: 'title', key: 'title' },
  { title: t('Type', 'Type'), key: 'type', dataIndex: 'type', sorter: true, width: 110 },
  { title: t('Fleet_Odometer', 'Odometer'), key: 'odometer', dataIndex: 'odometer', sorter: true, width: 100 },
  { title: t('Rental_Vendor', 'Vendor'), dataIndex: 'vendor', key: 'vendor', width: 140 },
  { title: t('Status', 'Status'), key: 'status', dataIndex: 'status', sorter: true, width: 120 },
  { title: t('Rental_Next_Due', 'Next due'), key: 'next', width: 140 },
  { title: t('Cost', 'Cost'), key: 'cost', dataIndex: 'cost', sorter: true, width: 110 },
  { title: '', key: 'actions', width: 90 },
]);

// ---------------- form ----------------

const vehicles = ref([]);
const vehicleOptions = computed(() => vehicles.value.map(v => ({ value: v.id, label: v.label })));

const formRef = ref();
const formOpen = ref(false);
const saving = ref(false);
const editing = ref(null);
const form = ref(emptyForm());

function emptyForm() {
  return {
    vehicle_id: undefined, title: '', type: 'service', status: 'completed',
    service_date: new Date().toISOString().slice(0, 10),
    odometer: null, cost: null, vendor: '',
    next_service_date: null, next_service_odometer: null, notes: '',
  };
}

const required = () => [{ required: true, message: t('Field_is_required', 'This field is required') }];
const rules = computed(() => ({
  vehicle_id: required(),
  title: required(),
  type: required(),
  status: required(),
  service_date: required(),
}));

function openForm(record) {
  editing.value = record;
  form.value = record
    ? {
        vehicle_id: record.vehicle_id,
        title: record.title,
        type: record.type,
        status: record.status,
        service_date: record.service_date,
        odometer: record.odometer,
        cost: record.cost,
        vendor: record.vendor || '',
        next_service_date: record.next_service_date,
        next_service_odometer: record.next_service_odometer,
        notes: record.notes || '',
      }
    : { ...emptyForm(), vehicle_id: filters.vehicle_id };
  formOpen.value = true;
  formRef.value?.clearValidate?.();
}

/** Prefill the odometer from the vehicle's current reading (new entries only). */
function onVehicleChange(id) {
  if (editing.value || form.value.odometer) return;
  const vehicle = vehicles.value.find(v => v.id === id);
  if (vehicle) form.value.odometer = vehicle.odometer || null;
}

async function submit() {
  try {
    await formRef.value.validate();
  } catch (e) {
    return;
  }

  saving.value = true;
  try {
    if (editing.value) await http.put(`fleet/maintenances/${editing.value.id}`, form.value);
    else await http.post('fleet/maintenances', form.value);
    message.success(t('Saved_successfully', 'Saved successfully'));
    formOpen.value = false;
    editing.value = null;
    crud.fetchRows();
    loadVehicles();
  } catch (e) {
    message.error(firstError(e) || t('Fleet_Save_Entry_Failed', 'Could not save this entry'));
  } finally {
    saving.value = false;
  }
}

function firstError(e) {
  const errors = e?.data?.errors;
  if (errors) {
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first.length) return first[0];
  }
  return e?.data?.message || '';
}

async function loadVehicles() {
  try {
    const data = await http.get('fleet/meta');
    vehicles.value = data?.vehicles || [];
  } catch (e) { /* the select stays empty */ }
}

onMounted(() => {
  crud.fetchRows();
  loadVehicles();
});
</script>

<style scoped>
.muted {
  color: rgba(128, 128, 128, 0.7);
}
.link {
  color: var(--app-primary, #6d28d9);
  cursor: pointer;
}
.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}
.tb-search {
  flex: 1 1 220px;
  min-width: 180px;
}
.tb-item {
  width: 160px;
}
.tb-range {
  width: 240px;
}
.tb-total {
  margin-inline-start: auto;
  margin-inline-end: 0;
  font-size: 13px;
  padding: 3px 10px;
}
.next {
  display: flex;
  flex-direction: column;
  font-size: 12.5px;
}
.next-odo {
  opacity: 0.6;
}
@media (max-width: 767px) {
  .tb-item,
  .tb-range {
    width: 100%;
  }
  .tb-total {
    margin-inline-start: 0;
  }
}
</style>
