<template>
  <div class="page">
    <PageHeader :title="$t('Milestones')" :subtitle="$t('Proj_Milestones_Subtitle')" :breadcrumb="[$t('Projects Management'), $t('Milestones')]">
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
          v-model:value="crud.search.value" :placeholder="$t('Search_Milestone')"
          allow-clear class="tb-search" @search="crud.reload"
        />
        <a-select
          v-model:value="filters.project_id" class="tb-item" allow-clear show-search
          option-filter-prop="label" :placeholder="$t('All_Projects')"
          :options="projectOptions" @change="crud.reload"
        />
        <a-select
          v-model:value="filters.status" class="tb-item-sm" allow-clear
          :placeholder="$t('Status')" :options="milestoneStatusOptions" @change="crud.reload"
        />
        <a-checkbox v-model:checked="filters.overdue" class="tb-check" @change="crud.reload">
          {{ $t('Proj_Overdue_Only') }}
        </a-checkbox>
      </div>
    </a-card>

    <DataTable :crud="crud" :columns="columns" :searchable="false" selectable>
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'title'">
          <div class="cell-title">{{ record.title }}</div>
          <div v-if="record.description" class="cell-sub">{{ record.description }}</div>
        </template>
        <template v-else-if="column.key === 'project'">
          <a class="link" @click="$router.push(`/projects/${record.project_id}`)">{{ record.project_title }}</a>
        </template>
        <template v-else-if="column.key === 'due_date'">
          <div v-if="record.due_date">
            {{ date(record.due_date) }}
            <a-tag v-if="dueLabel(record.days_to_due)" :color="dueLabel(record.days_to_due).color" class="tag-tight">
              {{ dueLabel(record.days_to_due).text }}
            </a-tag>
          </div>
          <span v-else class="muted">—</span>
        </template>
        <template v-else-if="column.key === 'progress'">
          <a-progress :percent="record.progress" size="small" :stroke-color="progressColor(record.progress)" />
        </template>
        <template v-else-if="column.key === 'status'">
          <a-select
            :value="record.status" size="small" class="status-select"
            :options="milestoneStatusOptions" @change="v => setStatus(record, v)"
          />
        </template>
        <template v-else-if="column.key === 'budget'">
          <span v-if="record.budget !== null">{{ money(record.budget) }}</span>
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
      :open="formOpen" :title="editing ? $t('Proj_Edit_Milestone') : $t('Proj_New_Milestone')" :width="600"
      :confirm-loading="saving" :ok-text="$t('submit')" :cancel-text="$t('Cancel')"
      @ok="submit" @cancel="formOpen = false"
    >
      <a-alert
        type="info" show-icon banner
        :message="$t('Proj_Milestone_Complete_Hint')"
        style="margin-bottom: 16px"
      />
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
        <a-row :gutter="16">
          <a-col :span="24">
            <a-form-item :label="$t('Project') + ' *'" name="project_id">
              <a-select
                v-model:value="form.project_id" show-search option-filter-prop="label"
                :options="projectOptions" :placeholder="$t('Proj_Select_Project')"
              />
            </a-form-item>
          </a-col>
          <a-col :span="24">
            <a-form-item :label="$t('Title') + ' *'" name="title">
              <a-input v-model:value="form.title" :placeholder="$t('Proj_Eg_Beta_Release')" allow-clear />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Rental_Due_Date')">
              <a-date-picker v-model:value="form.due_date" style="width: 100%" value-format="YYYY-MM-DD" />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Status') + ' *'" name="status">
              <a-select v-model:value="form.status" :options="milestoneStatusOptions" />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Proj_Progress_Percent')">
              <a-input-number
                v-model:value="form.progress" :min="0" :max="100"
                :disabled="form.status === 'completed'" style="width: 100%"
              />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Budget')">
              <a-input-number v-model:value="form.budget" :min="0" style="width: 100%" />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="8">
            <a-form-item :label="$t('Order')" :extra="$t('Proj_Position_In_Plan')">
              <a-input-number v-model:value="form.position" :min="0" style="width: 100%" />
            </a-form-item>
          </a-col>
          <a-col :span="24">
            <a-form-item :label="$t('Description')" style="margin-bottom: 0">
              <a-textarea v-model:value="form.description" :rows="3" />
            </a-form-item>
          </a-col>
        </a-row>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
/**
 * Project milestones. Status is editable inline from the list — moving a
 * milestone to "completed" is the most common single action here, and it should
 * not require opening a form.
 */
import { ref, reactive, computed, onMounted } from 'vue';
import { message } from 'ant-design-vue';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import DataTable from '../../components/DataTable.vue';
import { useCrudTable } from '../../composables/useCrudTable';
import { useFormat } from '../../composables/useFormat';
import { MILESTONE_STATUSES, progressColor, dueLabel, tr } from './workspaceOptions';
import http from '../../lib/http';
import { t } from '../../i18n';

const { money, date } = useFormat();

const filters = reactive({ project_id: undefined, status: undefined, overdue: false });

const crud = useCrudTable('project_milestones', {
  rowsKey: 'milestones',
  sortField: 'due_date',
  sortType: 'asc',
  params: () => ({
    project_id: filters.project_id || '',
    status: filters.status || '',
    overdue: filters.overdue ? 1 : '',
  }),
});

const projects = ref([]);
const projectOptions = computed(() => projects.value.map(p => ({ value: p.id, label: p.title })));
const milestoneStatusOptions = computed(() => tr(MILESTONE_STATUSES));

const columns = computed(() => [
  { title: t('Milestone', 'Milestone'), key: 'title', dataIndex: 'title', sorter: true },
  { title: t('Project', 'Project'), key: 'project', dataIndex: 'project_title', width: 180 },
  { title: t('Due', 'Due'), key: 'due_date', dataIndex: 'due_date', sorter: true, width: 200 },
  { title: t('Progress', 'Progress'), key: 'progress', dataIndex: 'progress', sorter: true, width: 150 },
  { title: t('Status', 'Status'), key: 'status', dataIndex: 'status', sorter: true, width: 160 },
  { title: t('Budget', 'Budget'), key: 'budget', dataIndex: 'budget', width: 120 },
  { title: '', key: 'actions', width: 90 },
]);

async function setStatus(record, status) {
  try {
    await http.post(`project_milestones/${record.id}/status`, { status });
    message.success(t('Proj_Milestone_Updated', 'Milestone updated.'));
    crud.fetchRows();
  } catch (e) {
    message.error(e?.data?.message || t('Proj_Could_Not_Update_Milestone', 'Could not update this milestone'));
  }
}

const formRef = ref();
const formOpen = ref(false);
const saving = ref(false);
const editing = ref(null);
const form = ref(empty());

function empty() {
  return {
    project_id: filters.project_id, title: '', description: '',
    due_date: null, status: 'pending', progress: 0, budget: null, position: 0,
  };
}

const required = () => [{ required: true, message: t('Field_is_required', 'This field is required') }];
const rules = computed(() => ({
  project_id: required(),
  title: required(),
  status: required(),
}));

function openForm(record) {
  editing.value = record;
  form.value = record ? { ...empty(), ...record } : empty();
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
    if (editing.value) await http.put(`project_milestones/${editing.value.id}`, form.value);
    else await http.post('project_milestones', form.value);
    message.success(t('Saved_successfully', 'Saved successfully'));
    formOpen.value = false;
    editing.value = null;
    crud.fetchRows();
  } catch (e) {
    message.error(e?.data?.message || t('Proj_Could_Not_Save_Milestone', 'Could not save this milestone'));
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  crud.fetchRows();
  try {
    const meta = await http.get('projects/meta');
    projects.value = meta?.projects || [];
  } catch (e) { /* the select stays empty */ }
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
  width: 180px;
}
.tb-item-sm {
  width: 140px;
}
.tb-check {
  white-space: nowrap;
}
.cell-title {
  font-weight: 500;
}
.cell-sub {
  font-size: 11.5px;
  opacity: 0.55;
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.status-select {
  width: 140px;
}
.tag-tight {
  margin-inline-start: 6px;
}
</style>
