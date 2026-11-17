<template>
  <div class="page">
    <PageHeader
      :title="isNew ? $t('Create') : $t('Edit')"
      :breadcrumb="[$t('Store'), $t('Banners'), isNew ? $t('Create') : $t('Edit')]"
    />

    <div v-if="loading" style="display: flex; justify-content: center; padding: 96px 0">
      <a-spin size="large" />
    </div>

    <a-row v-else :gutter="[16, 16]">
      <a-col :xs="24" :lg="16">
        <a-card size="small">
          <a-form layout="vertical">
            <a-form-item :label="$t('Title') + ' *'">
              <a-input v-model:value="form.title" />
            </a-form-item>
            <a-form-item :label="$t('Position')">
              <a-select v-model:value="form.position">
                <a-select-option v-for="p in POSITIONS" :key="p.value" :value="p.value">{{ p.label }}</a-select-option>
              </a-select>
              <a-alert type="info" show-icon style="margin-top: 10px">
                <template #message>
                  <strong>{{ posInfo.label }}</strong> — {{ $t('StoreAdm_Banner_Recommended_Size', { w: posInfo.w, h: posInfo.h, ratio: posInfo.ratioText }) }}
                </template>
                <template v-if="imgW && imgH" #description>
                  {{ $t('StoreAdm_Banner_Uploaded_Size', { w: imgW, h: imgH, ratio: uploadedRatioText }) }}
                  <span v-if="aspectMismatch"> • {{ $t('Aspect ratio differs from recommendation') }}</span>
                  <span v-else> • {{ $t('Looks good') }} ✅</span>
                </template>
              </a-alert>
            </a-form-item>
            <a-form-item :label="$t('Image')">
              <a-upload :file-list="[]" :before-upload="() => false" :max-count="1" accept="image/*" @change="onFile">
                <a-button>
                  <template #icon><UploadOutlined /></template>
                  {{ $t('Choose_a_file') }}
                </a-button>
              </a-upload>
              <img v-if="preview" :src="preview" style="max-height: 120px; margin-top: 10px; border-radius: 6px" />
            </a-form-item>
          </a-form>
        </a-card>
      </a-col>

      <a-col :xs="24" :lg="8">
        <a-card size="small" style="margin-bottom: 16px">
          <div style="margin-bottom: 16px">
            <a-switch v-model:checked="form.active" />
            <span style="margin-left: 8px">{{ form.active ? $t('Active') : $t('Disabled') }}</span>
          </div>
          <a-space direction="vertical" style="width: 100%">
            <a-button type="primary" block :loading="saving" @click="save">{{ $t('Save') }}</a-button>
            <a-button block @click="$router.back()">{{ $t('Cancel') }}</a-button>
          </a-space>
        </a-card>

        <a-card size="small" :title="$t('Size guide')">
          <ul style="margin: 0; padding-left: 18px; font-size: 13px; color: rgba(0, 0, 0, 0.65)">
            <li>{{ $t('StoreAdm_Banner_Top_Both') }}: 1200×600 (2:1)</li>
            <li>{{ $t('StoreAdm_Banner_Center_Both') }}: 1200×600 (2:1)</li>
            <li>{{ $t('StoreAdm_Banner_Footer_Both') }}: 1200×600 (2:1)</li>
          </ul>
          <div style="margin-top: 8px; font-size: 12px; color: #999">{{ $t('StoreAdm_Banner_Retina_Tip', { size: '2400×1200' }) }}</div>
        </a-card>
      </a-col>
    </a-row>
  </div>
</template>

<script setup>
/**
 * Banner create/edit — GET store/banners/{id} (edit); save MULTIPART: create
 * POST store/banners, edit POST store/banners/{id}?_method=PUT. Fields:
 * everything on the form object as strings (legacy Object.entries loop),
 * active as '1'|'0', image only when re-picked. Aspect-ratio helper kept.
 */
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { message } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import { UploadOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import http from '../../lib/http';
import { uploadForm } from '../../lib/upload';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const id = route.params.id || 'new';
const isNew = computed(() => id === 'new' || !id);

const POSITION_KEYS = {
  top_left: ['StoreAdm_Banner_Top_Left', 'Top — Left'],
  top_right: ['StoreAdm_Banner_Top_Right', 'Top — Right'],
  center_left: ['StoreAdm_Banner_Center_Left', 'Center — Left'],
  center_right: ['StoreAdm_Banner_Center_Right', 'Center — Right'],
  footer_left: ['StoreAdm_Banner_Footer_Left', 'Footer — Left'],
  footer_right: ['StoreAdm_Banner_Footer_Right', 'Footer — Right'],
};
const posLabel = (v) => (POSITION_KEYS[v] ? t(POSITION_KEYS[v][0], POSITION_KEYS[v][1]) : '—');
const POSITIONS = computed(() => Object.keys(POSITION_KEYS).map((value) => ({ value, label: posLabel(value) })));
const REC = {
  top_left: { w: 1200, h: 600 },
  top_right: { w: 1200, h: 600 },
  center_left: { w: 1200, h: 600 },
  center_right: { w: 1200, h: 600 },
  footer_left: { w: 1200, h: 600 },
  footer_right: { w: 1200, h: 600 },
};

const loading = ref(true);
const saving = ref(false);
const form = ref({ title: '', position: 'top_left', active: true, image: null });
const preview = ref(null);
const imgW = ref(null);
const imgH = ref(null);

function toRatioText(w, h) {
  const gcd = (a, b) => (b ? gcd(b, a % b) : a);
  const g = gcd(Math.round(w), Math.round(h)) || 1;
  return `${Math.round(w / g)}:${Math.round(h / g)}`;
}
const posInfo = computed(() => {
  const meta = REC[form.value.position] || { w: 1200, h: 600 };
  return { ...meta, label: posLabel(form.value.position), ratioText: toRatioText(meta.w, meta.h) };
});
const uploadedRatioText = computed(() =>
  imgW.value && imgH.value ? toRatioText(imgW.value, imgH.value) : '—');
const aspectMismatch = computed(() => {
  if (!imgW.value || !imgH.value) return false;
  const recRatio = posInfo.value.w / posInfo.value.h;
  const upRatio = imgW.value / imgH.value;
  return Math.abs(upRatio - recRatio) / recRatio > 0.05;
});

function readImageDims(src) {
  const img = new Image();
  img.onload = () => {
    imgW.value = img.width;
    imgH.value = img.height;
  };
  img.src = src;
}
function onFile({ file }) {
  const f = file.originFileObj || file;
  form.value.image = f;
  imgW.value = null;
  imgH.value = null;
  if (f) {
    const r = new FileReader();
    r.onload = () => {
      preview.value = r.result;
      readImageDims(r.result);
    };
    r.readAsDataURL(f);
  }
}

async function save() {
  saving.value = true;
  try {
    const fd = new FormData();
    Object.entries(form.value).forEach(([k, v]) => {
      if (k === 'image') {
        if (v) fd.append('image', v);
      } else if (k === 'active') {
        fd.append('active', form.value.active ? '1' : '0');
      } else {
        fd.append(k, v ?? '');
      }
    });
    const url = isNew.value ? 'store/banners' : `store/banners/${id}?_method=PUT`;
    const { status } = await uploadForm(url, fd);
    if (status >= 200 && status < 300) {
      message.success(t('Saved_successfully'));
      router.push('/store/banners');
    } else {
      message.error(t('InvalidData'));
    }
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  if (!isNew.value) {
    try {
      const data = await http.get(`store/banners/${id}`);
      Object.assign(form.value, { ...data, image: null, active: !!data.active });
      preview.value = data.image_url || (data.image ? `/${data.image}` : null);
      if (preview.value) readImageDims(preview.value);
    } catch (e) {
      message.error(t('InvalidData'));
    }
  }
  loading.value = false;
});
</script>
