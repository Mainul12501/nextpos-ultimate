<template>
  <div class="page">
    <PageHeader :title="$t('profil')" :breadcrumb="[$t('Settings'), $t('profil')]" />

    <div v-if="loading" style="display: flex; justify-content: center; padding: 96px 0">
      <a-spin size="large" />
    </div>

    <a-row v-else :gutter="16">
      <!-- ============ Profile panel ============ -->
      <a-col :xs="24" :md="8" :lg="7">
        <a-card class="profile-card">
          <div class="profile-avatar">
            <img v-if="avatarSrc" :src="avatarSrc" alt="avatar" />
            <span v-else class="profile-initials">{{ initials }}</span>
          </div>
          <div class="profile-name">{{ displayName }}</div>
          <div class="profile-email">{{ form.email }}</div>

          <!-- Legacy capped this at an image under 200 KB. -->
          <a-upload
            :file-list="avatarList"
            accept="image/*"
            :before-upload="onAvatarPick"
            :max-count="1"
            :show-upload-list="false"
            @remove="clearAvatar"
          >
            <a-button block>
              <template #icon><UploadOutlined /></template>
              {{ $t('Choose_a_file') }}
            </a-button>
          </a-upload>
          <a-button
            v-if="pickedUrl" type="text" danger size="small" block
            style="margin-top: 8px" @click="clearAvatar"
          >
            {{ $t('Delete') }}
          </a-button>
        </a-card>
      </a-col>

      <!-- ============ Fields ============ -->
      <a-col :xs="24" :md="16" :lg="17">
        <a-card style="margin-bottom: 16px">
          <div class="section-title">
            <IdcardOutlined /> {{ $t('Account_Information') }}
          </div>
          <a-form ref="profileRef" :model="form" :rules="profileRules" layout="vertical">
            <a-row :gutter="16">
              <a-col :xs="24" :md="12">
                <a-form-item :label="$t('Firstname') + ' *'" name="firstname">
                  <a-input v-model:value="form.firstname" :placeholder="$t('Enter_Firstname')" allow-clear>
                    <template #prefix><UserOutlined class="field-prefix" /></template>
                  </a-input>
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="12">
                <a-form-item :label="$t('lastname') + ' *'" name="lastname">
                  <a-input v-model:value="form.lastname" :placeholder="$t('Enter_Lastname')" allow-clear>
                    <template #prefix><UserOutlined class="field-prefix" /></template>
                  </a-input>
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="12">
                <a-form-item :label="$t('username') + ' *'" name="username">
                  <a-input v-model:value="form.username" :placeholder="$t('Enter_Username')" allow-clear>
                    <template #prefix><IdcardOutlined class="field-prefix" /></template>
                  </a-input>
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="12">
                <a-form-item :label="$t('Phone') + ' *'" name="phone">
                  <a-input v-model:value="form.phone" :placeholder="$t('Enter_Phone')" allow-clear>
                    <template #prefix><PhoneOutlined class="field-prefix" /></template>
                  </a-input>
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="12">
                <a-form-item :label="$t('Email') + ' *'" name="email">
                  <a-input v-model:value="form.email" :placeholder="$t('Enter_Email')" allow-clear>
                    <template #prefix><MailOutlined class="field-prefix" /></template>
                  </a-input>
                </a-form-item>
              </a-col>
            </a-row>

            <a-button type="primary" :loading="savingProfile" @click="saveProfile">{{ $t('submit') }}</a-button>
          </a-form>
        </a-card>

        <a-card>
          <div class="section-title">
            <LockOutlined /> {{ $t('ChangePassword') }}
          </div>
          <a-form ref="passwordRef" :model="passwordForm" :rules="passwordRules" layout="vertical">
            <a-row :gutter="16">
              <a-col :xs="24" :md="8">
                <a-form-item :label="$t('CurrentPassword') + ' *'" name="current_password">
                  <a-input-password v-model:value="passwordForm.current_password" :placeholder="$t('Enter_Password')" />
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="8">
                <a-form-item :label="$t('Newpassword') + ' *'" name="new_password">
                  <a-input-password v-model:value="passwordForm.new_password" :placeholder="$t('Enter_Password')" />
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="8">
                <a-form-item :label="$t('ConfirmPassword') + ' *'" name="new_password_confirmation">
                  <a-input-password v-model:value="passwordForm.new_password_confirmation" :placeholder="$t('Enter_Password')" />
                </a-form-item>
              </a-col>
            </a-row>

            <a-button type="primary" :loading="savingPassword" @click="savePassword">
              {{ $t('UpdatePassword') }}
            </a-button>
          </a-form>
        </a-card>

        <!-- ============ Two-factor authentication ============ -->
        <a-card style="margin-top: 16px">
          <div class="section-title">
            <SafetyCertificateOutlined /> {{ tr('Tfa_Title', 'Two-factor authentication') }}
          </div>

          <a-alert
            v-if="!tfa.migrated" type="warning" show-icon
            :message="tr('Sec_Migration_Required', 'Database update required')"
            :description="tr('Sec_Migration_Required_Help', 'Run the database migrations (php artisan migrate) to enable these settings and the sign-in activity log.')"
          />
          <div v-else class="tfa-row">
            <div class="tfa-info">
              <div class="tfa-status">
                <a-tag :color="tfa.enabled ? 'success' : 'default'" style="margin: 0">
                  {{ tfa.enabled ? tr('Tfa_On', 'On') : tr('Tfa_Off', 'Off') }}
                </a-tag>
                <span v-if="tfa.enabled && tfa.confirmed_at" class="tfa-muted">
                  {{ tr('Tfa_Since', 'since :date').replace(':date', fmtDate(tfa.confirmed_at)) }}
                </span>
              </div>
              <div class="tfa-muted">
                {{ tfa.enabled
                  ? tr('Tfa_On_Help', 'Signing in asks for a code from your authenticator app after your password.')
                  : tr('Tfa_Off_Help', 'Add a second sign-in step: a 6-digit code from an authenticator app on your phone, such as Google Authenticator, Microsoft Authenticator or Authy.') }}
              </div>
              <div v-if="tfa.enabled" :class="['tfa-muted', { 'tfa-warn': tfa.recovery_codes_left <= 2 }]">
                {{ tr('Tfa_Codes_Left', 'Recovery codes left: :count').replace(':count', tfa.recovery_codes_left) }}
              </div>
              <a-alert
                v-if="tfa.required && !tfa.enabled" type="info" show-icon style="margin-top: 8px"
                :message="tfa.grace_until
                  ? tr('Tfa_Required_Grace_Notice', 'Your administrator requires two-factor authentication. You can postpone it at sign-in until :date; after that, setup is required.').replace(':date', fmtDate(tfa.grace_until * 1000))
                  : tr('Tfa_Required_Notice', 'Your administrator requires two-factor authentication. If you don’t turn it on now, you will be asked to set it up at your next sign-in.')"
              />
            </div>
            <a-space wrap>
              <a-button v-if="!tfa.enabled" type="primary" @click="openTfa('enable')">
                {{ tr('Tfa_Turn_On', 'Turn on') }}
              </a-button>
              <template v-else>
                <a-button @click="openTfa('regenerate')">{{ tr('Tfa_New_Codes', 'New recovery codes') }}</a-button>
                <a-tooltip :title="tfa.required ? tr('Tfa_Required_By_Admin', 'Required by your administrator') : ''">
                  <a-button danger :disabled="tfa.required" @click="openTfa('disable')">{{ tr('Tfa_Turn_Off', 'Turn off') }}</a-button>
                </a-tooltip>
              </template>
            </a-space>
          </div>
        </a-card>
      </a-col>
    </a-row>

    <!-- Turn on (password → scan → codes), new codes (password → codes), turn off (password). -->
    <a-modal
      v-model:open="tfaModal.open" :title="tfaModalTitle" :footer="null" :mask-closable="false"
      :width="460" destroy-on-close @cancel="closeTfa"
    >
      <template v-if="tfaModal.step === 'password'">
        <p class="tfa-muted">{{ tr('Tfa_Confirm_Password', 'Enter your current password to continue.') }}</p>
        <a-input-password
          v-model:value="tfaModal.password" :placeholder="$t('Enter_Password')" autofocus
          @press-enter="submitTfaPassword"
        />
        <div class="tfa-modal-actions">
          <a-button @click="closeTfa">{{ $t('Cancel') }}</a-button>
          <a-button type="primary" :danger="tfaModal.action === 'disable'" :loading="tfaModal.busy" @click="submitTfaPassword">
            {{ tfaModal.action === 'disable' ? tr('Tfa_Turn_Off', 'Turn off') : tr('Tfa_Continue', 'Continue') }}
          </a-button>
        </div>
      </template>

      <template v-else-if="tfaModal.step === 'scan'">
        <ol class="tfa-steps">
          <li>{{ tr('Tfa_Step_Install', 'Install an authenticator app on your phone.') }}</li>
          <li>{{ tr('Tfa_Step_Scan', 'Scan this QR code, or type the key below.') }}</li>
        </ol>
        <!-- qrcodejs owns the inner div's DOM, so Vue renders nothing inside it. -->
        <div class="tfa-qr">
          <span v-if="qrFailed" class="tfa-muted">{{ tr('Tfa_Qr_Failed', 'QR code unavailable — type the key instead.') }}</span>
          <a-spin v-else-if="!qrReady" />
          <div v-show="qrReady" ref="qrEl" class="tfa-qr-canvas" />
        </div>
        <div class="tfa-secret">
          <code>{{ formattedSecret }}</code>
          <a-button type="link" size="small" @click="copyText(tfaModal.secret)">{{ tr('Tfa_Copy', 'Copy') }}</a-button>
        </div>
        <ol class="tfa-steps" start="3">
          <li>{{ tr('Tfa_Step_Confirm', 'Enter the 6-digit code the app shows.') }}</li>
        </ol>
        <a-input
          v-model:value="tfaModal.code" class="tfa-code" placeholder="000000" :maxlength="6"
          inputmode="numeric" autocomplete="one-time-code" @press-enter="confirmTfa"
        />
        <div class="tfa-modal-actions">
          <a-button @click="closeTfa">{{ $t('Cancel') }}</a-button>
          <a-button type="primary" :loading="tfaModal.busy" @click="confirmTfa">{{ tr('Tfa_Turn_On', 'Turn on') }}</a-button>
        </div>
      </template>

      <template v-else-if="tfaModal.step === 'codes'">
        <a-alert
          type="warning" show-icon style="margin-bottom: 12px"
          :message="tr('Tfa_Codes_Save', 'Save these recovery codes somewhere safe. Each one signs you in once if you lose your phone. They won’t be shown again.')"
        />
        <div class="tfa-codes">
          <span v-for="code in tfaModal.codes" :key="code">{{ code }}</span>
        </div>
        <div class="tfa-modal-actions">
          <a-button @click="copyText(tfaModal.codes.join('\n'))">{{ tr('Tfa_Copy', 'Copy') }}</a-button>
          <a-button @click="downloadCodes">{{ tr('Tfa_Download', 'Download') }}</a-button>
          <a-button type="primary" @click="closeTfa">{{ tr('Tfa_Done', 'Done') }}</a-button>
        </div>
      </template>
    </a-modal>
  </div>
</template>

<script setup>
/**
 * Profile — legacy profile.vue. Two independent forms on one page:
 *
 * - Profile: GET Get_user_profile → {user}; saved as multipart to
 *   POST update_user_profile/{id} with `_method=put` (Laravel needs the
 *   method override because PUT cannot carry a file upload).
 * - Password: POST update_user_password with current/new/confirmation. A 422
 *   means the current password was wrong; legacy surfaced the server message
 *   and fell back to CurrentPasswordIncorrect.
 *
 * Field rules are legacy's: firstname/lastname/username required 4–20 chars,
 * phone and email required, new password at least 6 characters and confirmed.
 * After a successful save the auth store is refreshed so the topbar avatar and
 * username update without a reload.
 *
 * Two-factor (TwoFactorController): GET two-factor → {migrated, enabled,
 * required, confirmed_at, recovery_codes_left}. Turning on = POST
 * two-factor/setup {password} → {secret, uri}, then POST two-factor/confirm
 * {code} → status + recovery_codes. POST two-factor/recovery-codes {password}
 * and POST two-factor/disable {password}. Strings use Tfa_* keys with an
 * English fallback until the seeder is re-run.
 */
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import { message } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import {
  UploadOutlined, UserOutlined, IdcardOutlined, MailOutlined,
  PhoneOutlined, LockOutlined, SafetyCertificateOutlined,
} from '@ant-design/icons-vue';
import dayjs from 'dayjs';
import PageHeader from '../../components/PageHeader.vue';
import { useAuthStore } from '../../stores/auth';
import { uploadForm } from '../../lib/upload';
import http from '../../lib/http';
import { renderQr } from '../../lib/qrcode';

const { t } = useI18n();
const tr = (key, fallback) => {
  const out = t(key);
  return out === key ? fallback : out;
};
const auth = useAuthStore();

const loading = ref(true);
const savingProfile = ref(false);
const savingPassword = ref(false);
const profileRef = ref();
const passwordRef = ref();

const form = ref({ id: '', firstname: '', lastname: '', username: '', email: '', phone: '' });
const passwordForm = ref({ current_password: '', new_password: '', new_password_confirmation: '' });

const avatarFile = ref(null);
const avatarList = ref([]);
// Live preview: a freshly-picked file wins, otherwise the saved avatar.
const pickedUrl = ref('');
const avatarSrc = computed(() => pickedUrl.value || auth.avatarUrl);
const displayName = computed(() => {
  const full = `${form.value.firstname || ''} ${form.value.lastname || ''}`.trim();
  return full || form.value.username || auth.username || '';
});
const initials = computed(() => {
  const f = (form.value.firstname || '').charAt(0);
  const l = (form.value.lastname || '').charAt(0);
  const both = (f + l).toUpperCase();
  return both || (form.value.username || auth.username || 'U').charAt(0).toUpperCase();
});

const len = { min: 3, max: 20 };
const profileRules = computed(() => ({
  firstname: [{ required: true, message: t('Field_is_required') }, { ...len }],
  lastname: [{ required: true, message: t('Field_is_required') }, { ...len }],
  username: [{ required: true, message: t('Field_is_required') }, { ...len }],
  phone: [{ required: true, message: t('Field_is_required') }],
  email: [{ required: true, message: t('Field_is_required') }],
}));

const passwordRules = computed(() => ({
  current_password: [{ required: true, message: t('Field_is_required') }],
  new_password: [{ required: true, message: t('Field_is_required') }, { min: 6 }],
  new_password_confirmation: [
    { required: true, message: t('Field_is_required') },
    {
      validator: (_rule, value) =>
        value === passwordForm.value.new_password
          ? Promise.resolve()
          : Promise.reject(t('Please_fill_the_form_correctly')),
    },
  ],
}));

/** Returning false keeps the file local — we upload it with the form. */
function onAvatarPick(file) {
  if (file.size > 200 * 1024) {
    message.error(t('Please_fill_the_form_correctly'));
    return false;
  }
  avatarFile.value = file;
  avatarList.value = [file];
  if (pickedUrl.value) URL.revokeObjectURL(pickedUrl.value);
  pickedUrl.value = URL.createObjectURL(file);
  return false;
}

function clearAvatar() {
  if (pickedUrl.value) URL.revokeObjectURL(pickedUrl.value);
  pickedUrl.value = '';
  avatarFile.value = null;
  avatarList.value = [];
}

async function loadProfile() {
  try {
    const data = await http.get('Get_user_profile');
    if (data?.user) form.value = { ...form.value, ...data.user };
  } catch (e) { /* leaves the form empty, as legacy did */ } finally {
    loading.value = false;
  }
}

async function saveProfile() {
  try {
    await profileRef.value.validate();
  } catch (e) {
    message.error(t('Please_fill_the_form_correctly'));
    return;
  }
  savingProfile.value = true;
  const fd = new FormData();
  fd.append('firstname', form.value.firstname);
  fd.append('lastname', form.value.lastname);
  fd.append('username', form.value.username);
  fd.append('email', form.value.email);
  fd.append('phone', form.value.phone);
  if (avatarFile.value) fd.append('avatar', avatarFile.value);
  fd.append('_method', 'put');

  try {
    const res = await uploadForm(`update_user_profile/${form.value.id}`, fd);
    if (res.status >= 200 && res.status < 300) {
      message.success(t('Successfully_Updated'));
      clearAvatar();
      // Refresh so the topbar picks up the new name/avatar immediately.
      auth.loaded = false;
      await auth.load();
      await loadProfile();
    } else {
      message.error(res.data?.message || t('InvalidData'));
    }
  } catch (e) {
    message.error(t('InvalidData'));
  } finally {
    savingProfile.value = false;
  }
}

async function savePassword() {
  try {
    await passwordRef.value.validate();
  } catch (e) {
    message.error(t('Please_fill_the_form_correctly'));
    return;
  }
  savingPassword.value = true;
  try {
    await http.post('update_user_password', { ...passwordForm.value });
    message.success(t('PasswordUpdated'));
    passwordForm.value = { current_password: '', new_password: '', new_password_confirmation: '' };
    passwordRef.value.clearValidate();
  } catch (e) {
    // 422 = the current password did not match.
    message.error(e?.data?.message || t('CurrentPasswordIncorrect'));
  } finally {
    savingPassword.value = false;
  }
}

/* ------------------------------------------------------------- two-factor */
const tfa = ref({ migrated: true, enabled: false, required: false, confirmed_at: null, recovery_codes_left: 0 });
const tfaModal = reactive({ open: false, action: 'enable', step: 'password', password: '', code: '', secret: '', uri: '', codes: [], busy: false });
const qrEl = ref(null);
const qrFailed = ref(false);
const qrReady = ref(false);

const DATE_FMT = (() => {
  try { return localStorage.getItem('app_date_format') || 'YYYY-MM-DD'; } catch (e) { return 'YYYY-MM-DD'; }
})();
const fmtDate = v => (v ? dayjs(v).format(DATE_FMT) : '');
const formattedSecret = computed(() => (tfaModal.secret.match(/.{1,4}/g) || []).join(' '));
const tfaModalTitle = computed(() => ({
  enable: tr('Tfa_Turn_On_Title', 'Turn on two-factor authentication'),
  regenerate: tr('Tfa_New_Codes', 'New recovery codes'),
  disable: tr('Tfa_Turn_Off_Title', 'Turn off two-factor authentication'),
}[tfaModal.action]));

async function loadTfa() {
  try {
    tfa.value = await http.get('two-factor');
  } catch (e) { /* card shows "off" */ }
}

function openTfa(action) {
  Object.assign(tfaModal, { open: true, action, step: 'password', password: '', code: '', secret: '', uri: '', codes: [], busy: false });
}

function closeTfa() {
  tfaModal.open = false;
  tfaModal.password = '';
  tfaModal.secret = '';
  tfaModal.codes = [];
}

async function submitTfaPassword() {
  if (!tfaModal.password) {
    message.error(t('Field_is_required'));
    return;
  }
  tfaModal.busy = true;
  try {
    const payload = { password: tfaModal.password };
    if (tfaModal.action === 'enable') {
      const d = await http.post('two-factor/setup', payload);
      Object.assign(tfaModal, { secret: d.secret, uri: d.uri, step: 'scan' });
      qrFailed.value = false;
      qrReady.value = false;
      await nextTick();
      qrReady.value = await renderQr(qrEl.value, d.uri, 180);
      qrFailed.value = !qrReady.value;
    } else if (tfaModal.action === 'regenerate') {
      const d = await http.post('two-factor/recovery-codes', payload);
      tfa.value = d;
      Object.assign(tfaModal, { codes: d.recovery_codes || [], step: 'codes' });
    } else {
      tfa.value = await http.post('two-factor/disable', payload);
      closeTfa();
      message.success(tr('Tfa_Turned_Off', 'Two-factor authentication is off.'));
    }
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    tfaModal.busy = false;
    tfaModal.password = '';
  }
}

async function confirmTfa() {
  if (!/^\d{6}$/.test((tfaModal.code || '').trim())) {
    message.error(tr('Tfa_Enter_Code', 'Enter the 6-digit code from the app.'));
    return;
  }
  tfaModal.busy = true;
  try {
    const d = await http.post('two-factor/confirm', { code: tfaModal.code.trim() });
    tfa.value = d;
    Object.assign(tfaModal, { codes: d.recovery_codes || [], step: 'codes', secret: '' });
    message.success(tr('Tfa_Turned_On', 'Two-factor authentication is on.'));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    tfaModal.busy = false;
  }
}

function copyText(text) {
  const done = () => message.success(tr('Tfa_Copied', 'Copied'));
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(done).catch(() => {});
    return;
  }
  const ta = document.createElement('textarea');
  ta.value = text;
  document.body.appendChild(ta);
  ta.select();
  try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ }
  document.body.removeChild(ta);
}

function downloadCodes() {
  const blob = new Blob([tfaModal.codes.join('\n') + '\n'], { type: 'text/plain' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'recovery-codes.txt';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(a.href);
}

onMounted(() => {
  loadProfile();
  loadTfa();
});
</script>

<style scoped>
/* ---------------- Profile panel ---------------- */
.profile-card {
  text-align: center;
  position: sticky;
  top: 88px;
}
.profile-avatar {
  width: 96px;
  height: 96px;
  margin: 4px auto 12px;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--app-primary, #6d28d9), #9333ea);
  box-shadow: 0 6px 18px rgba(var(--app-primary-rgb, 109, 40, 217), 0.28);
}
.profile-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.profile-initials {
  color: #fff;
  font-size: 36px;
  font-weight: 700;
  letter-spacing: 1px;
}
.profile-name {
  font-size: 16px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.profile-email {
  opacity: 0.6;
  font-size: 13px;
  margin-bottom: 16px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ---------------- Field sections ---------------- */
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  opacity: 0.65;
  margin-bottom: 16px;
}
.field-prefix {
  opacity: 0.4;
}

/* ---------------- Two-factor ---------------- */
.tfa-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
}
.tfa-info {
  flex: 1;
  min-width: 240px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.tfa-status {
  display: flex;
  align-items: center;
  gap: 8px;
}
.tfa-muted {
  font-size: 13px;
  opacity: 0.65;
}
.tfa-warn {
  color: #d46b08;
  opacity: 1;
}
.tfa-steps {
  padding-inline-start: 20px;
  margin: 0 0 12px;
  font-size: 13px;
}
.tfa-qr {
  width: 204px;
  height: 204px;
  margin: 0 auto 12px;
  padding: 12px;
  background: #fff;
  border-radius: 10px;
  border: 1px solid rgba(128, 128, 128, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.tfa-qr-canvas :deep(img),
.tfa-qr-canvas :deep(canvas) {
  width: 180px !important;
  height: 180px !important;
}
.tfa-secret {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  margin-bottom: 16px;
  word-break: break-all;
}
.tfa-code {
  font-size: 20px;
  letter-spacing: 0.3em;
  text-align: center;
}
.tfa-codes {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  padding: 14px;
  border-radius: 10px;
  border: 1px solid rgba(128, 128, 128, 0.2);
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  text-align: center;
}
.tfa-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
}
</style>
