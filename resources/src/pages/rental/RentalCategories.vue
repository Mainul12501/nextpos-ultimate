<template>
  <div class="page">
    <PageHeader :title="$t('Rental_Categories')" :breadcrumb="[$t('Rental_Management'), $t('Rental_Categories')]">
      <template #actions>
        <a-button type="primary" @click="openModal()">
          <template #icon><PlusOutlined /></template>
          {{ $t('Add_Category') }}
        </a-button>
      </template>
    </PageHeader>

    <DataTable :crud="crud" :columns="columns" selectable>
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'name'">
          <span class="cat-name">
            <span class="cat-ic">
              <component :is="typeIcon(record.type)" :size="16" :stroke-width="1.8" />
            </span>
            <span>
              <span class="cat-title">{{ record.name }}</span>
              <span v-if="record.description" class="cat-desc">{{ record.description }}</span>
            </span>
          </span>
        </template>
        <template v-else-if="column.key === 'type'">
          <span v-if="record.type">{{ labelOf(RENTAL_TYPES, record.type) }}</span>
          <span v-else class="muted">{{ $t('Rental_Any_Type') }}</span>
        </template>
        <template v-else-if="column.key === 'items_count'">
          <router-link v-if="record.items_count" :to="`/rental/items?category_id=${record.id}`">
            <a-tag color="cyan">{{ record.items_count }}</a-tag>
          </router-link>
          <a-tag v-else>0</a-tag>
        </template>
        <template v-else-if="column.key === 'actions'">
          <a-space :size="0">
            <a-tooltip :title="$t('Edit')">
              <a-button type="text" size="small" @click="openModal(record)">
                <template #icon><EditOutlined /></template>
              </a-button>
            </a-tooltip>
            <a-tooltip :title="$t('Delete')">
              <a-button type="text" size="small" danger @click="crud.remove(record)">
                <template #icon><DeleteOutlined /></template>
              </a-button>
            </a-tooltip>
          </a-space>
        </template>
      </template>
    </DataTable>

    <a-modal
      v-model:open="modalOpen" :title="form.id ? $t('Edit_Category') : $t('Add_Category')"
      :confirm-loading="saving" :ok-text="$t('submit')" :cancel-text="$t('Cancel')" @ok="save"
    >
      <a-form ref="formRef" :model="form" :rules="rules" layout="vertical" style="margin-top: 12px">
        <a-form-item :label="$t('Name')" name="name">
          <a-input v-model:value="form.name" :placeholder="$t('Rental_Category_Name_Placeholder')" allow-clear />
        </a-form-item>
        <a-form-item :label="$t('Rental_Default_Type')" :extra="$t('Rental_Default_Type_Help')">
          <a-select v-model:value="form.type" :options="typeOptions" allow-clear :placeholder="$t('Rental_Any_Type')" />
        </a-form-item>
        <a-form-item :label="$t('Description')">
          <a-textarea v-model:value="form.description" :rows="3" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
/**
 * Rental categories — GET rental/categories → { categories, totalRows }.
 * Create is POST, edit is PUT (JSON — no file upload here). Deleting a
 * category that still holds items answers 422 with the reason.
 */
import { ref, computed, onMounted, nextTick } from 'vue';
import { message } from 'ant-design-vue';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import DataTable from '../../components/DataTable.vue';
import { useCrudTable } from '../../composables/useCrudTable';
import http from '../../lib/http';
import { t } from '../../i18n';
import { RENTAL_TYPES, tr, labelOf, typeIcon } from './rentalOptions';

const crud = useCrudTable('rental/categories', { rowsKey: 'categories' });

const typeOptions = computed(() => tr(RENTAL_TYPES));

const columns = computed(() => [
  { title: t('Name'), dataIndex: 'name', key: 'name', sorter: true },
  { title: t('Rental_Default_Type'), dataIndex: 'type', key: 'type', sorter: true, width: 170 },
  { title: t('Rental_Items'), key: 'items_count', width: 120, align: 'center' },
  { title: t('Action'), key: 'actions', width: 100 },
]);

const modalOpen = ref(false);
const saving = ref(false);
const formRef = ref();
const form = ref({ id: null, name: '', type: undefined, description: '' });

const rules = computed(() => ({
  name: [{ required: true, message: t('Field_is_required', 'This field is required') }],
}));

function openModal(row = null) {
  form.value = row
    ? { id: row.id, name: row.name, type: row.type || undefined, description: row.description || '' }
    : { id: null, name: '', type: undefined, description: '' };
  modalOpen.value = true;
  nextTick(() => formRef.value?.clearValidate());
}

async function save() {
  try {
    await formRef.value.validate();
  } catch (e) {
    return;
  }
  saving.value = true;
  const body = { name: form.value.name, type: form.value.type || null, description: form.value.description || null };
  try {
    if (form.value.id) {
      await http.put(`rental/categories/${form.value.id}`, body);
      message.success(t('Updated_in_successfully', 'Updated successfully'));
    } else {
      await http.post('rental/categories', body);
      message.success(t('Created_in_successfully', 'Created successfully'));
    }
    modalOpen.value = false;
    await crud.fetchRows();
  } catch (e) {
    message.error(e?.data?.message || t('Gen_Could_Not_Save', 'Could not save'));
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
.cat-name {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}
.cat-ic {
  width: 32px;
  height: 32px;
  flex: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 9px;
  color: var(--app-primary, #6d28d9);
  background: rgba(var(--app-primary-rgb, 109, 40, 217), 0.1);
}
.cat-title {
  display: block;
  font-weight: 500;
}
.cat-desc {
  display: block;
  font-size: 12px;
  opacity: 0.6;
  max-width: 420px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
