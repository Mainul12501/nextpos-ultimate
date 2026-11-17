<template>
  <div class="page">
    <PageHeader
      :title="$t('Imp_Products_Update_Only_Title')"
      :breadcrumb="[$t('Products'), $t('Import (Update Only)')]"
    >
      <template #extra>
        <a-button @click="$router.push('/products')">{{ $t('Back') }}</a-button>
      </template>
    </PageHeader>

    <!-- Upload -->
    <a-card style="margin-bottom: 16px">
      <div style="max-width: 760px; margin: 0 auto">
          <a-alert
            type="info" show-icon style="margin-bottom: 16px"
            :message="$t('Imp_Update_Only')"
            :description="$t('Imp_Update_Only_Desc')"
          />

          <ImportDropzone
            ref="dz"
            :file="file"
            :accept="accept"
            :title="$t('Imp_Drop_File_Here')"
            :hint="$t('Imp_Allowed_Formats', { formats: 'CSV, XLSX, XLS', size: '20MB' })"
            :disabled="uploading"
            @file="loadFile"
            @clear="clearFile()"
          />

          <a-alert v-if="successMessage" type="success" style="margin-top: 16px" show-icon>
            <template #message>{{ successMessage }}</template>
            <template #description>
              <div v-if="importResults" style="font-size: 12px">
                <div>{{ $t('Imp_Updated_Products', { n: importResults.updated }) }}</div>
                <div v-if="importResults.not_found > 0" style="color: #d48806">
                  {{ $t('Imp_Not_Found_Codes', { n: importResults.not_found }) }}
                </div>
                <div v-if="importResults.errors > 0" style="color: #cf1322">
                  {{ $t('Imp_Errors_Count', { n: importResults.errors }) }}
                </div>
              </div>
            </template>
          </a-alert>

          <a-alert v-if="errorMessages.length" type="error" style="margin-top: 16px" show-icon>
            <template #message>{{ $t('Import_Failed_Fix_Below') }}</template>
            <template #description>
              <ul style="margin: 0; padding-left: 18px">
                <li v-for="(err, idx) in errorMessages" :key="'err-' + idx">{{ err }}</li>
              </ul>
            </template>
          </a-alert>

          <a-progress v-if="uploading" :percent="progress" size="small" style="margin-top: 16px" />

          <div style="display: flex; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap">
            <a-button type="primary" :disabled="!canSubmit || uploading" :loading="uploading" @click="submit">
              {{ $t('Imp_Import_Now') }}
            </a-button>
            <a-button href="/import/exemples/update_products.csv" target="_blank" rel="noopener">
              <DownloadOutlined /> {{ $t('Download_exemple') }}
            </a-button>
            <a-button :disabled="!file || uploading" @click="clearFile()">{{ $t('Reset') }}</a-button>
          </div>
      </div>
    </a-card>

    <!-- Expected file format -->
    <a-card size="small" :title="$t('Imp_Expected_File_Format')">
      <template #extra>
        <span style="font-size: 12px; color: #8c8c8c">{{ $t('Imp_One_Row_Per_Product') }} · {{ $t('Imp_All_Columns_Required') }}</span>
      </template>
      <a-table
        :columns="exampleColumns" :data-source="exampleRows"
        size="small" :pagination="false" row-key="code"
      />
      <ul style="padding-left: 18px; margin: 12px 0 0 0; font-size: 12px">
        <li>{{ $t('Imp_Note_Matching_Codes') }}</li>
      </ul>
    </a-card>
  </div>
</template>

<script setup>
/**
 * Import Products (Update Only) — POST products/import/update-only
 * (multipart, key `products`). Accepts csv/xlsx/xls (unlike the other
 * wizards). Success shows an updated/not_found/errors panel then redirects
 * after 2s — all copied from legacy Import_products_update.vue.
 */
import { ref, computed, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { notification } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import { DownloadOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import ImportDropzone from '../../components/ImportDropzone.vue';
import { uploadForm } from '../../lib/upload';
import { start as startProgress, done as doneProgress } from '../../lib/progress';

const router = useRouter();
const { t } = useI18n();

const endpoint = 'products/import/update-only';
const file = ref(null);
const uploading = ref(false);
const progress = ref(0);
const successMessage = ref('');
const importResults = ref(null);
const errorMessages = ref([]);
const warningMessages = ref([]);
const dz = ref();
let redirectTimer = null;

const maxSize = 20 * 1024 * 1024; // 20MB
const accept = '.csv,.xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv';

const canSubmit = computed(() => !!file.value && errorMessages.value.length === 0);

const exampleColumns = [
  { title: 'code *', dataIndex: 'code' },
  { title: 'cost *', dataIndex: 'cost' },
  { title: 'retail_price *', dataIndex: 'retail_price' },
];
const exampleRows = [
  { code: 'PROD-001', cost: '10.50', retail_price: '19.99' },
  { code: 'PROD-002', cost: '5.25', retail_price: '12.50' },
  { code: 'PROD-003', cost: '8.00', retail_price: '15.00' },
];

onBeforeUnmount(() => { if (redirectTimer) clearTimeout(redirectTimer); });

function toast(msg, title, type) {
  notification[type === 'danger' ? 'error' : type]({ message: title, description: msg });
}

function clearErrors() {
  errorMessages.value = [];
  warningMessages.value = [];
}

function loadFile(f) {
  clearErrors();
  successMessage.value = '';
  importResults.value = null;
  const msgs = [];
  if (f.size > maxSize) msgs.push(t('Imp_File_Too_Large'));
  const name = f.name || '';
  const ext = name.split('.').pop().toLowerCase();
  if (['xlsx', 'xls', 'csv'].indexOf(ext) === -1) msgs.push(t('Imp_Unsupported_Csv_Excel'));
  if (msgs.length) {
    errorMessages.value = msgs;
    clearFile(false);
    return;
  }
  file.value = f;
}

function clearFile(resetInput) {
  if (typeof resetInput === 'undefined') resetInput = true;
  file.value = null;
  successMessage.value = '';
  importResults.value = null;
  if (resetInput && dz.value) dz.value.resetInput();
}

function flattenLaravelErrors(errors) {
  const out = [];
  if (!errors) return out;
  if (Array.isArray(errors)) {
    errors.forEach((v) => { if (v != null && v !== '') out.push(String(v)); });
    return out;
  }
  if (typeof errors === 'object') {
    Object.keys(errors).forEach((k) => {
      const val = errors[k];
      if (Array.isArray(val)) val.forEach((vv) => { if (vv != null && vv !== '') out.push(String(vv)); });
      else if (val != null && val !== '') out.push(String(val));
    });
    return out;
  }
  if (typeof errors === 'string') return [errors];
  return out;
}

function collectErrorsFromResponse(data) {
  const out = [];
  if (!data) return out;
  if (typeof data === 'string') {
    const txt = data.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    if (txt && txt.toLowerCase() !== 'validation failed') out.push(txt);
    return out;
  }
  if (data.errors) {
    const errs = flattenLaravelErrors(data.errors);
    if (errs.length) return errs;
  }
  if (Array.isArray(data.messages)) out.push(...data.messages);
  if (data.details) {
    if (Array.isArray(data.details)) out.push(...data.details);
    else if (typeof data.details === 'string') out.push(data.details);
  }
  if (typeof data.error === 'string') out.push(data.error);
  if (!out.length && typeof data.message === 'string') {
    const msg = data.message.trim();
    if (msg.toLowerCase() !== 'validation failed') out.push(msg);
  }
  const seen = {}; const filtered = [];
  out.forEach((s) => {
    const t = String(s).trim();
    if (t && !seen[t]) { seen[t] = true; filtered.push(t); }
  });
  return filtered;
}

async function submit() {
  if (!file.value) {
    errorMessages.value = [t('Imp_Choose_File')];
    return;
  }
  clearErrors();
  successMessage.value = '';
  importResults.value = null;
  uploading.value = true;
  progress.value = 0;
  startProgress();

  try {
    const fd = new FormData();
    fd.append('products', file.value);

    const resp = await uploadForm(endpoint, fd, (p) => { progress.value = p; });
    const data = resp ? resp.data : null;
    const ok = data && typeof data === 'object' && (data.status === true || data.success === true);

    if (!ok) {
      const msgs = collectErrorsFromResponse(data);
      errorMessages.value = msgs.length ? msgs : [t('Imp_Review_File')];
      toast(t('Imp_Check_Error_List'), t('Imp_Import_Failed'), 'danger');
      return;
    }

    importResults.value = {
      updated: data.updated || 0,
      not_found: data.not_found || 0,
      errors: data.errors || 0,
    };
    successMessage.value = data.message || t('Imp_Products_Updated');
    toast(successMessage.value, t('Success'), 'success');
    redirectTimer = setTimeout(() => { router.push('/products'); }, 2000);
  } catch (err) {
    errorMessages.value = err && err.message ? [String(err.message)] : [t('Imp_Upload_Went_Wrong')];
    toast(t('Imp_Check_Error_List'), t('Imp_Import_Failed'), 'danger');
  } finally {
    doneProgress();
    uploading.value = false;
    progress.value = 0;
  }
}
</script>
