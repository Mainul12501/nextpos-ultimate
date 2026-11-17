<template>
  <div class="page">
    <PageHeader :title="$t('Hosp_Lab_Test_Catalogue')" :breadcrumb="[$t('Hospital'), $t('Laboratory'), $t('Hosp_Tests')]">
      <template #actions>
        <a-button @click="$router.push('/hospital/lab-orders')">
          <template #icon><ExperimentOutlined /></template>
          {{ $t('Lab Orders') }}
        </a-button>
        <a-button type="primary" @click="openForm(null)">
          <template #icon><PlusOutlined /></template>
          {{ $t('Add') }}
        </a-button>
      </template>
    </PageHeader>

    <a-card size="small" style="margin-bottom: 16px">
      <div class="toolbar">
        <a-input-search
          v-model:value="crud.search.value" :placeholder="$t('Hosp_Search_Test_Hint')"
          allow-clear class="tb-search" @search="crud.reload"
        />
        <a-select
          v-model:value="filters.category" class="tb-item" allow-clear show-search
          :placeholder="$t('All_Categories')" :options="categoryOptions" @change="crud.reload"
        />
        <a-select
          v-model:value="filters.active" class="tb-item-sm" allow-clear :placeholder="$t('All')"
          :options="activeOptions"
          @change="crud.reload"
        />
      </div>
    </a-card>

    <DataTable :crud="crud" :columns="columns" :searchable="false">
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'name'">
          <div class="cell-name">{{ record.name }}</div>
          <div v-if="record.code" class="cell-code">{{ record.code }}</div>
        </template>
        <template v-else-if="column.key === 'price'">{{ money(record.price) }}</template>
        <template v-else-if="column.key === 'turnaround_hours'">
          <span v-if="record.turnaround_hours">{{ $t('Hosp_N_Hours_Short', { n: record.turnaround_hours }) }}</span>
          <span v-else class="muted">—</span>
        </template>
        <template v-else-if="column.key === 'is_active'">
          <a-tag :color="record.is_active ? 'success' : 'default'">
            {{ record.is_active ? $t('Active') : $t('Inactive') }}
          </a-tag>
        </template>
        <template v-else-if="column.key === 'actions'">
          <a-space :size="0">
            <a-tooltip :title="$t('Edit')">
              <a-button type="text" size="small" @click="openForm(record)">
                <template #icon><EditOutlined /></template>
              </a-button>
            </a-tooltip>
            <a-tooltip :title="$t('Delete')">
              <a-button type="text" size="small" danger @click="crud.remove(record, { label: record.name })">
                <template #icon><DeleteOutlined /></template>
              </a-button>
            </a-tooltip>
          </a-space>
        </template>
      </template>
    </DataTable>

    <a-modal
      :open="formOpen" :title="editing ? $t('Hosp_Edit_Test') : $t('Hosp_New_Test')" :width="620"
      :confirm-loading="saving" :ok-text="$t('submit')" :cancel-text="$t('Cancel')"
      @ok="submit" @cancel="formOpen = false"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-alert
          type="info" show-icon banner
          :message="$t('Hosp_Lab_Test_Snapshot_Hint')"
          style="margin-bottom: 16px"
        />
        <a-row :gutter="16">
          <a-col :xs="24" :md="14">
            <a-form-item :label="$t('Hosp_Test_Name') + ' *'" name="name">
              <a-input v-model:value="form.name" :placeholder="$t('Hosp_Eg_Test_Name')" allow-clear />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="10">
            <a-form-item :label="$t('Code')">
              <a-input v-model:value="form.code" :placeholder="$t('Hosp_Eg_Test_Code')" allow-clear />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Category')">
              <a-auto-complete
                v-model:value="form.category" :options="categoryOptions"
                :placeholder="$t('Hosp_Eg_Category')" allow-clear
              />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Hosp_Sample_Type')">
              <a-input v-model:value="form.sample_type" :placeholder="$t('Hosp_Eg_Sample')" allow-clear />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Unit')">
              <a-input v-model:value="form.unit" :placeholder="$t('Hosp_Eg_Unit')" allow-clear />
            </a-form-item>
          </a-col>
          <a-col :xs="24" :md="8">
            <a-form-item :label="$t('Hosp_Normal_Range')">
              <a-input v-model:value="form.normal_range" :placeholder="$t('Hosp_Eg_Range')" allow-clear />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Price')">
              <a-input-number v-model:value="form.price" :min="0" :step="1" style="width: 100%" />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Hosp_Turnaround_Hours')">
              <a-input-number v-model:value="form.turnaround_hours" :min="0" style="width: 100%" />
            </a-form-item>
          </a-col>
          <a-col :span="24">
            <a-form-item style="margin-bottom: 0">
              <a-checkbox v-model:checked="form.is_active">{{ $t('Hosp_Available_To_Order') }}</a-checkbox>
            </a-form-item>
          </a-col>
        </a-row>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
/**
 * Laboratory catalogue — the tests that can be ordered, their price and their
 * reference range.
 */
import { ref, reactive, computed, onMounted } from 'vue';
import { message } from 'ant-design-vue';
import { PlusOutlined, EditOutlined, DeleteOutlined, ExperimentOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import DataTable from '../../components/DataTable.vue';
import { useCrudTable } from '../../composables/useCrudTable';
import { useFormat } from '../../composables/useFormat';
import http from '../../lib/http';
import { t } from '../../i18n';

const { money } = useFormat();
const filters = reactive({ category: undefined, active: undefined });

const crud = useCrudTable('hospital/lab-tests', {
  rowsKey: 'lab_tests',
  sortField: 'name',
  sortType: 'asc',
  params: () => ({ category: filters.category || '', active: filters.active || '' }),
});

const categoryOptions = computed(() =>
  (crud.payload.value?.categories || []).map(c => ({ value: c, label: c })));

const activeOptions = computed(() => [
  { value: '1', label: t('Active', 'Active') },
  { value: '0', label: t('Inactive', 'Inactive') },
]);

const columns = computed(() => [
  { title: t('Hosp_Test', 'Test'), key: 'name', dataIndex: 'name', sorter: true },
  { title: t('Category', 'Category'), dataIndex: 'category', key: 'category', sorter: true, width: 160 },
  { title: t('Hosp_Sample', 'Sample'), dataIndex: 'sample_type', key: 'sample_type', width: 130 },
  { title: t('Unit', 'Unit'), dataIndex: 'unit', key: 'unit', width: 100 },
  { title: t('Hosp_Normal_Range', 'Normal range'), dataIndex: 'normal_range', key: 'normal_range', width: 150 },
  { title: t('Hosp_Turnaround', 'Turnaround'), key: 'turnaround_hours', dataIndex: 'turnaround_hours', width: 120 },
  { title: t('Price', 'Price'), key: 'price', dataIndex: 'price', sorter: true, width: 120 },
  { title: t('Status', 'Status'), key: 'is_active', dataIndex: 'is_active', sorter: true, width: 110 },
  { title: '', key: 'actions', width: 90 },
]);

const formRef = ref();
const formOpen = ref(false);
const saving = ref(false);
const editing = ref(null);
const form = ref(empty());

function empty() {
  return {
    name: '', code: '', category: undefined, sample_type: '', unit: '',
    normal_range: '', price: null, turnaround_hours: null, is_active: true,
  };
}

const rules = computed(() => ({
  name: [{ required: true, message: t('Field_is_required', 'This field is required') }],
}));

function openForm(record) {
  editing.value = record;
  form.value = record ? { ...empty(), ...record, is_active: !!record.is_active } : empty();
  formOpen.value = true;
  formRef.value?.clearValidate?.();
}

async function submit() {
  try {
    await formRef.value.validate();
  } catch (e) {
    return;
  }

  saving.value = true;
  try {
    if (editing.value) await http.put(`hospital/lab-tests/${editing.value.id}`, form.value);
    else await http.post('hospital/lab-tests', form.value);
    message.success(t('Saved_successfully', 'Saved successfully'));
    formOpen.value = false;
    editing.value = null;
    crud.fetchRows();
  } catch (e) {
    message.error(e?.data?.message || t('Hosp_Err_Save_Test', 'Could not save this test'));
  } finally {
    saving.value = false;
  }
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
  gap: 10px;
}
.tb-search {
  flex: 1 1 240px;
  min-width: 200px;
}
.tb-item {
  width: 170px;
}
.tb-item-sm {
  width: 120px;
}
.cell-name {
  font-weight: 500;
}
.cell-code {
  font-size: 11.5px;
  opacity: 0.55;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
</style>
