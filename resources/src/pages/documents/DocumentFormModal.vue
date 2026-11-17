<template>
  <a-modal
    :open="open"
    :title="isEdit ? $t('DocArc_Edit_Document_Details') : $t('DocArc_Upload_Documents')"
    :width="620"
    :confirm-loading="saving"
    :ok-text="isEdit ? $t('submit') : $t('Upload')"
    :cancel-text="$t('Cancel')"
    :ok-button-props="{ disabled: !isEdit && !files.length }"
    @ok="submit"
    @cancel="close"
  >
    <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
      <!-- Upload area (create only — replacing a file is a version, not an edit) -->
      <template v-if="!isEdit">
        <a-upload-dragger
          :file-list="files"
          multiple
          :before-upload="addFile"
          @remove="removeFile"
        >
          <p class="ant-upload-drag-icon"><InboxOutlined /></p>
          <p class="ant-upload-text">{{ $t('DocArc_Drop_Files') }}</p>
          <p class="ant-upload-hint">{{ $t('DocArc_Drop_Files_Hint') }}</p>
        </a-upload-dragger>

        <a-progress
          v-if="saving && progress > 0" :percent="progress" size="small"
          style="margin-top: 12px"
        />
      </template>

      <a-form-item
        v-if="isEdit || files.length <= 1"
        :label="$t('Title')" name="title"
        :extra="!isEdit && !files.length ? $t('DocArc_Title_Empty_Hint') : null"
        style="margin-top: 16px"
      >
        <a-input v-model:value="form.title" :placeholder="$t('DocArc_Eg_Lease')" allow-clear />
      </a-form-item>
      <a-alert
        v-else type="info" show-icon banner
        :message="$t('DocArc_Files_Selected_Hint', { n: files.length })"
        style="margin: 16px 0"
      />

      <a-row :gutter="16">
        <a-col :xs="24" :sm="12">
          <a-form-item :label="$t('DocArc_Folder')">
            <a-tree-select
              v-model:value="form.folder_id"
              :tree-data="folderOptions"
              :placeholder="$t('DocArc_Uncategorised')"
              allow-clear
              tree-default-expand-all
              :field-names="{ label: 'title', value: 'value', children: 'children' }"
            />
          </a-form-item>
        </a-col>
        <a-col :xs="24" :sm="12">
          <a-form-item :label="$t('Reference')">
            <a-input v-model:value="form.reference" :placeholder="$t('DocArc_Reference_Placeholder')" allow-clear />
          </a-form-item>
        </a-col>
      </a-row>

      <a-row :gutter="16">
        <a-col :xs="24" :sm="12">
          <a-form-item :label="$t('DocArc_Expiry_Date')" :extra="$t('DocArc_Expiry_Date_Hint')">
            <a-date-picker v-model:value="form.expiry_date" style="width: 100%" value-format="YYYY-MM-DD" />
          </a-form-item>
        </a-col>
        <a-col :xs="24" :sm="12">
          <a-form-item :label="$t('Tags')">
            <a-select
              v-model:value="form.tags" mode="tags" style="width: 100%"
              :placeholder="$t('DocArc_Type_And_Enter')" :options="tagOptions" :token-separators="[',']"
            />
          </a-form-item>
        </a-col>
      </a-row>

      <a-form-item :label="$t('Description')" style="margin-bottom: 0">
        <a-textarea v-model:value="form.description" :rows="2" allow-clear />
      </a-form-item>
    </a-form>
  </a-modal>
</template>

<script setup>
/**
 * Upload (multi-file) and metadata editing in one modal.
 *
 * Create posts multipart through lib/upload's XHR helper so the progress bar is
 * real; edit is a plain JSON PUT since the file itself never changes here —
 * replacing a file goes through the version endpoint in the preview drawer.
 */
import { ref, computed, watch } from 'vue';
import { message } from 'ant-design-vue';
import { InboxOutlined } from '@ant-design/icons-vue';
import { uploadForm } from '../../lib/upload';
import http from '../../lib/http';
import { t } from '../../i18n';
import { tp } from './documentKinds';

const props = defineProps({
  open: { type: Boolean, default: false },
  // null = upload mode; a record = edit-metadata mode.
  document: { type: Object, default: null },
  folders: { type: Array, default: () => [] },
  knownTags: { type: Array, default: () => [] },
  defaultFolderId: { type: [Number, String], default: null },
});
const emit = defineEmits(['close', 'saved']);

const formRef = ref();
const saving = ref(false);
const progress = ref(0);
const files = ref([]);
const form = ref(emptyForm());

const isEdit = computed(() => !!props.document?.id);

function emptyForm() {
  return { title: '', description: '', folder_id: null, reference: '', expiry_date: null, tags: [] };
}

const rules = computed(() => (isEdit.value
  ? { title: [{ required: true, message: t('Field_is_required', 'This field is required') }] }
  : {}));

const folderOptions = computed(() => {
  const build = parentId => props.folders
    .filter(f => (f.parent_id || null) === parentId)
    .map(f => ({ title: f.name, value: f.id, children: build(f.id) }));
  return build(null);
});

const tagOptions = computed(() => props.knownTags.map(x => ({ value: x.tag || x, label: x.tag || x })));

watch(() => props.open, isOpen => {
  if (!isOpen) return;
  files.value = [];
  progress.value = 0;
  form.value = props.document
    ? {
        title: props.document.title,
        description: props.document.description || '',
        folder_id: props.document.folder_id || null,
        reference: props.document.reference || '',
        expiry_date: props.document.expiry_date || null,
        tags: [...(props.document.tags || [])],
      }
    : { ...emptyForm(), folder_id: props.defaultFolderId || null };
  formRef.value?.clearValidate?.();
});

/** Returning false keeps antd from auto-uploading; we send them ourselves. */
function addFile(file) {
  files.value = [...files.value, file];
  return false;
}
function removeFile(file) {
  files.value = files.value.filter(f => f.uid !== file.uid);
}

function close() {
  if (saving.value) return;
  emit('close');
}

async function submit() {
  try {
    await formRef.value.validate();
  } catch (e) {
    return;
  }

  saving.value = true;
  try {
    if (isEdit.value) await saveMetadata();
    else await upload();
  } finally {
    saving.value = false;
  }
}

async function saveMetadata() {
  try {
    await http.put(`documents/${props.document.id}`, {
      title: form.value.title,
      description: form.value.description,
      folder_id: form.value.folder_id || null,
      reference: form.value.reference,
      expiry_date: form.value.expiry_date || null,
      tags: form.value.tags,
    });
    message.success(t('Updated_in_successfully', 'Updated successfully'));
    emit('saved');
  } catch (e) {
    message.error(firstError(e) || t('DocArc_Could_Not_Save_Document', 'Could not save the document'));
  }
}

async function upload() {
  if (!files.value.length) {
    message.warning(t('DocArc_Choose_File', 'Choose at least one file to upload.'));
    return;
  }

  const fd = new FormData();
  files.value.forEach(f => fd.append('files[]', f.originFileObj || f));
  if (files.value.length === 1 && form.value.title) fd.append('title', form.value.title);
  if (form.value.description) fd.append('description', form.value.description);
  if (form.value.folder_id) fd.append('folder_id', form.value.folder_id);
  if (form.value.reference) fd.append('reference', form.value.reference);
  if (form.value.expiry_date) fd.append('expiry_date', form.value.expiry_date);
  fd.append('tags', JSON.stringify(form.value.tags || []));

  progress.value = 0;
  const res = await uploadForm('documents', fd, p => { progress.value = p; });

  if (res.status >= 200 && res.status < 300) {
    const count = res.data?.count || files.value.length;
    message.success(count === 1
      ? t('DocArc_Uploaded_One', '1 document uploaded.')
      : tp('DocArc_Uploaded_Many', { n: count }, `${count} documents uploaded.`));
    emit('saved');
    return;
  }
  // uploadForm resolves on 422 rather than throwing, so validation errors land here.
  message.error(firstError({ data: res.data }) || t('DocArc_Could_Not_Upload_Files', 'Could not upload the files'));
}

/** Laravel answers 422 with { message, errors: { field: [msg] } }. */
function firstError(e) {
  const errors = e?.data?.errors;
  if (errors) {
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first.length) return first[0];
  }
  return e?.data?.message || '';
}
</script>
