<template>
  <div class="page">
    <PageHeader :title="$t('Appearance_Settings')" :breadcrumb="[$t('Settings'), $t('Appearance_Settings')]" />

    <div v-if="loading" style="display: flex; justify-content: center; padding: 96px 0">
      <a-spin size="large" />
    </div>

    <a-form v-else layout="vertical">
      <a-card size="small" style="margin-bottom: 16px">
        <a-row :gutter="16">
          <a-col :xs="24" :md="12">
            <a-form-item :label="$t('CompanyName')">
              <a-input v-model:value="setting.app_name" />
            </a-form-item>
          </a-col>
          <a-col :xs="24" :md="12">
            <a-form-item :label="$t('page_title_suffix')">
              <a-input v-model:value="setting.page_title_suffix" />
            </a-form-item>
          </a-col>
          <a-col :xs="24" :md="12">
            <a-form-item :label="$t('developed_by')">
              <a-input v-model:value="setting.developed_by" />
            </a-form-item>
          </a-col>
          <a-col :xs="24" :md="12">
            <a-form-item :label="$t('Set_Footer')">
              <a-input v-model:value="setting.footer" />
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="6">
            <a-form-item :label="$t('ChangeLogo')">
              <a-upload
                :file-list="logoList" :max-count="1" accept="image/*" list-type="picture"
                :before-upload="f => { logoList = [f]; return false; }" @remove="logoList = []"
              >
                <a-button><UploadOutlined /> {{ $t('Choose') }}</a-button>
              </a-upload>
            </a-form-item>
          </a-col>
          <a-col :xs="12" :md="6">
            <a-form-item :label="$t('ChangeFavicon')">
              <a-upload
                :file-list="faviconList" :max-count="1" accept="image/*" list-type="picture"
                :before-upload="f => { faviconList = [f]; return false; }" @remove="faviconList = []"
              >
                <a-button><UploadOutlined /> {{ $t('Choose') }}</a-button>
              </a-upload>
            </a-form-item>
          </a-col>
          <a-col :xs="24" :md="12">
            <a-form-item label=" ">
              <a-space direction="vertical" size="small">
                <a-checkbox v-model:checked="setting.customize_button_visible">{{ $t('Set_Show_Customize_Button') }}</a-checkbox>
                <a-checkbox v-model:checked="setting.hide_site_name">{{ $t('Set_Hide_Site_Name') }}</a-checkbox>
              </a-space>
            </a-form-item>
          </a-col>
        </a-row>
      </a-card>

      <a-card size="small" :title="$t('Set_Login_Page')" style="margin-bottom: 16px">
        <a-row :gutter="16">
          <a-col v-for="f in LOGIN_FIELDS" :key="f.key" :xs="24" :md="12">
            <a-form-item :label="$t(f.label, f.params || {})">
              <a-input v-model:value="setting[f.key]" />
            </a-form-item>
          </a-col>
          <a-col :xs="24" :md="12">
            <a-form-item :label="$t('Set_Background_Color')">
              <a-space>
                <input
                  type="color"
                  :value="setting.login_bg_color || '#4f46e5'"
                  style="width: 48px; height: 32px; padding: 2px; border: 1px solid #d9d9d9; border-radius: 6px; background: transparent; cursor: pointer"
                  @input="e => (setting.login_bg_color = e.target.value)"
                />
                <a-input v-model:value="setting.login_bg_color" placeholder="#4f46e5" style="width: 130px" />
                <a-button v-if="setting.login_bg_color" @click="setting.login_bg_color = null">{{ $t('Reset') }}</a-button>
              </a-space>
            </a-form-item>
          </a-col>
        </a-row>
      </a-card>

      <a-button type="primary" size="large" :loading="saving" @click="save">{{ $t('submit') }}</a-button>
    </a-form>
  </div>
</template>

<script setup>
/**
 * GET get_appearance_settings → {settings}; save = multipart POST
 * update_appearance_settings/{id} with _method=put; checkbox flags travel as
 * '1'/'0' strings; logo/favicon only appended when a new file was picked.
 */
import { ref, onMounted } from 'vue';
import { message } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import { UploadOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import http from '../../lib/http';
import { useAuthStore } from '../../stores/auth';

const { t } = useI18n();
const auth = useAuthStore();

const LOGIN_FIELDS = [
  // label = i18n key (params fill its placeholders), translated at render time.
  { key: 'login_hero_title', label: 'Hero_Title' },
  { key: 'login_hero_subtitle', label: 'Hero_Subtitle' },
  { key: 'login_panel_title', label: 'Set_Panel_Title' },
  { key: 'login_panel_subtitle', label: 'Set_Panel_Subtitle' },
  { key: 'login_hero_badge', label: 'Set_Hero_Badge' },
  { key: 'login_hero_feature_1', label: 'Set_Hero_Feature_N', params: { n: 1 } },
  { key: 'login_hero_feature_2', label: 'Set_Hero_Feature_N', params: { n: 2 } },
  { key: 'login_hero_feature_3', label: 'Set_Hero_Feature_N', params: { n: 3 } },
  { key: 'login_btn_text', label: 'Set_Login_Button_Text' },
  { key: 'login_footer_text', label: 'Set_Login_Footer_Text' },
];

const loading = ref(true);
const saving = ref(false);
const setting = ref({});
const logoList = ref([]);
const faviconList = ref([]);

async function save() {
  saving.value = true;
  const s = setting.value;
  const fd = new FormData();
  if (faviconList.value.length) fd.append('favicon', faviconList.value[0].originFileObj || faviconList.value[0]);
  if (logoList.value.length) fd.append('logo', logoList.value[0].originFileObj || logoList.value[0]);
  fd.append('app_name', s.app_name || '');
  fd.append('page_title_suffix', s.page_title_suffix || '');
  fd.append('developed_by', s.developed_by || '');
  fd.append('footer', s.footer || '');
  fd.append('customize_button_visible', s.customize_button_visible ? '1' : '0');
  fd.append('hide_site_name', s.hide_site_name ? '1' : '0');
  LOGIN_FIELDS.forEach(({ key }) => fd.append(key, s[key] || ''));
  fd.append('login_bg_color', s.login_bg_color || '');
  fd.append('_method', 'put');
  try {
    await http.postForm(`update_appearance_settings/${s.id}`, fd);
    message.success(t('Successfully_Updated'));
    // Refresh the auth payload so the sidebar brand (logo, site name, footer)
    // reflects the change immediately, without a page reload.
    auth.reload?.().catch?.(() => {});
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    const data = await http.get('get_appearance_settings');
    const s = data.settings || {};
    setting.value = {
      ...s,
      customize_button_visible: !!Number(s.customize_button_visible),
      hide_site_name: !!Number(s.hide_site_name),
    };
  } catch (e) {
    message.error(t('InvalidData'));
  } finally {
    loading.value = false;
  }
});
</script>
