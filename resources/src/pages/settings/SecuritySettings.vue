<template>
  <div class="page">
    <PageHeader :title="$t('Security')" :breadcrumb="[$t('Settings'), $t('Security')]" />

    <a-skeleton v-if="loading" active :paragraph="{ rows: 10 }" />

    <template v-else>
      <a-alert
        v-if="!migrated"
        type="warning" show-icon style="margin-bottom: 16px"
        :message="tr('Sec_Migration_Required', 'Database update required')"
        :description="tr('Sec_Migration_Required_Help', 'Run the database migrations (php artisan migrate) to enable these settings and the sign-in activity log.')"
      />

      <!-- ============================== CAPTCHA ============================== -->
      <a-card size="small" class="sec-card">
        <template #title><RobotOutlined /> {{ tr('Sec_Login_Captcha', 'Login CAPTCHA') }}</template>
        <div class="setting-row first">
          <div>
            <div class="setting-label">{{ tr('Sec_Captcha_Enable', 'Require a CAPTCHA on the login page') }}</div>
            <div class="setting-help">{{ tr('Sec_Captcha_Enable_Help', 'Stops bots and scripts from submitting the staff sign-in form.') }}</div>
          </div>
          <a-switch v-model:checked="form.captcha_enabled" />
        </div>

        <template v-if="form.captcha_enabled">
          <div class="setting-row">
            <div>
              <div class="setting-label">{{ tr('Sec_Provider', 'Provider') }}</div>
              <div class="setting-help">
                <template v-if="form.captcha_provider === 'builtin'">
                  {{ imageCaptcha
                    ? tr('Sec_Provider_Builtin_Help', 'A distorted-text image hosted by this app. Works without any account or keys.')
                    : tr('Sec_Provider_Builtin_No_Gd', 'The server has no PHP GD extension, so a short sum question is shown instead of an image.') }}
                </template>
                <template v-else>{{ tr('Sec_Provider_External_Help', "Uses your own keys from the provider's dashboard. Allow this domain there:") }} <code>{{ host }}</code></template>
              </div>
            </div>
            <a-select v-model:value="form.captcha_provider" :options="providerOptions" style="width: 240px" @change="resetTest" />
          </div>

          <div v-if="form.captcha_provider !== 'builtin'" class="sub-block">
            <a-form layout="vertical">
              <a-row :gutter="16">
                <a-col :xs="24" :md="12">
                  <a-form-item :label="tr('Sec_Site_Key', 'Site key')" class="tight">
                    <a-input v-model:value="form.captcha_site_key" autocomplete="off" @change="resetTest" />
                  </a-form-item>
                </a-col>
                <a-col :xs="24" :md="12">
                  <a-form-item
                    :label="tr('Sec_Secret_Key', 'Secret key')" class="tight"
                    :help="secretSet ? tr('Sec_Secret_Saved', 'A secret key is saved. Leave empty to keep it.') : tr('Sec_Secret_Encrypted', 'Stored encrypted; never shown again.')"
                  >
                    <a-input-password v-model:value="form.captcha_secret_key" autocomplete="new-password" @change="resetTest" />
                  </a-form-item>
                </a-col>
              </a-row>
            </a-form>

            <div v-if="keysChanged" class="test-box">
              <div class="setting-label">{{ tr('Sec_Test_Keys', 'Test the keys') }}</div>
              <div class="setting-help" style="max-width: none">
                {{ tr('Sec_Test_Keys_Help', 'New keys must pass a live challenge before they can be saved, so a wrong key can never lock you out.') }}
              </div>
              <a-space style="margin-top: 8px" wrap>
                <a-button size="small" :loading="testLoading" @click="loadTest"><PlayCircleOutlined /> {{ tr('Sec_Load_Test', 'Load test challenge') }}</a-button>
                <a-tag v-if="testToken" color="success"><CheckCircleOutlined /> {{ tr('Sec_Test_Passed', 'Passed — you can save now') }}</a-tag>
              </a-space>
              <div ref="testHost" style="margin-top: 10px" />
              <div v-if="testError" class="test-error">{{ testError }}</div>
            </div>
          </div>

          <div class="setting-row">
            <div>
              <div class="setting-label">{{ tr('Sec_When_To_Show', 'When to show it') }}</div>
              <div class="setting-help">{{ tr('Sec_When_To_Show_Help', '"After failed attempts" keeps sign-in friction-free until someone starts guessing from an IP address.') }}</div>
            </div>
            <a-space wrap>
              <a-radio-group v-model:value="form.captcha_mode" size="small">
                <a-radio-button value="always">{{ tr('Sec_Every_Sign_In', 'Every sign-in') }}</a-radio-button>
                <a-radio-button value="after_failures">{{ tr('Sec_After_Failed_Attempts', 'After failed attempts') }}</a-radio-button>
              </a-radio-group>
              <a-input-number
                v-if="form.captcha_mode === 'after_failures'"
                v-model:value="form.captcha_after_failures" :min="1" :max="20" size="small" style="width: 120px"
                :addon-after="tr('Sec_Fails', 'fails')"
              />
            </a-space>
          </div>

          <div class="setting-row">
            <div>
              <div class="setting-label">{{ tr('Sec_Captcha_Forgot', 'Also on the "Forgot password" page') }}</div>
              <div class="setting-help">{{ tr('Sec_Captcha_Forgot_Help', 'Stops the reset form being abused to flood a mailbox with reset emails.') }}</div>
            </div>
            <a-switch v-model:checked="form.captcha_on_forgot_password" />
          </div>
        </template>
      </a-card>

      <!-- ============================== Lockout ============================== -->
      <a-card size="small" class="sec-card">
        <template #title><LockOutlined /> {{ tr('Sec_Lockout', 'Failed-attempt lockout') }}</template>
        <div class="setting-row first">
          <div>
            <div class="setting-label">{{ tr('Sec_Lockout_Enable', 'Block sign-in after repeated failed attempts') }}</div>
            <div class="setting-help">{{ tr('Sec_Lockout_Enable_Help', 'While blocked, even the correct password is refused. Also applies to the mobile app and the API token endpoint.') }}</div>
          </div>
          <a-switch v-model:checked="form.lockout_enabled" />
        </div>

        <template v-if="form.lockout_enabled">
          <div class="setting-row">
            <div>
              <div class="setting-label">{{ tr('Sec_Max_Attempts', 'Failed attempts allowed') }}</div>
              <div class="setting-help">{{ tr('Sec_Max_Attempts_Help', 'The block starts on this failure. Failures are forgotten after a successful sign-in or an hour without failures.') }}</div>
            </div>
            <a-input-number v-model:value="form.lockout_max_attempts" :min="2" :max="50" style="width: 140px" :addon-after="tr('Sec_Tries', 'tries')" />
          </div>

          <div class="setting-row">
            <div>
              <div class="setting-label">{{ tr('Sec_Block_For', 'Block for') }}</div>
              <div class="setting-help">{{ tr('Sec_Block_For_Help', 'How long sign-in stays blocked once the limit is reached.') }}</div>
            </div>
            <a-space wrap>
              <a-radio-group v-model:value="lockoutPreset" size="small">
                <a-radio-button v-for="m in LOCKOUT_PRESETS" :key="m" :value="String(m)">{{ minutesLabel(m) }}</a-radio-button>
                <a-radio-button value="custom">{{ $t('Custom') }}</a-radio-button>
              </a-radio-group>
              <a-input-number
                v-if="lockoutPreset === 'custom'"
                v-model:value="form.lockout_minutes" :min="1" :max="1440" size="small" style="width: 120px"
                addon-after="min"
              />
            </a-space>
          </div>

          <div class="setting-row">
            <div>
              <div class="setting-label">{{ tr('Sec_Scope', 'What to block') }}</div>
              <div class="setting-help">{{ tr('Sec_Scope_Help', 'Blocking an email on every IP stops guessing spread over many addresses, but also lets anyone who knows a staff email lock it out on purpose.') }}</div>
            </div>
            <a-select v-model:value="form.lockout_scope" :options="scopeOptions" style="width: 280px" />
          </div>

          <div class="setting-row">
            <div>
              <div class="setting-label">{{ tr('Sec_Show_Remaining', 'Show remaining attempts') }}</div>
              <div class="setting-help">{{ tr('Sec_Show_Remaining_Help', 'Add "N attempts left before sign-in is blocked" to the error message.') }}</div>
            </div>
            <a-switch v-model:checked="form.lockout_show_remaining" />
          </div>
        </template>
      </a-card>

      <!-- ============================== Two-factor ============================== -->
      <a-card size="small" class="sec-card">
        <template #title><SafetyCertificateOutlined /> {{ tr('Sec_Two_Factor', 'Two-factor authentication') }}</template>
        <template #extra>
          <a-button type="link" size="small" @click="guideOpen = true">
            <ReadOutlined /> {{ tr('Tfa_Guide_Button', 'Guide') }}
          </a-button>
        </template>
        <div class="setting-row first">
          <div>
            <div class="setting-label">{{ tr('Sec_2fa_Require', 'Require two-factor authentication') }}</div>
            <div class="setting-help">
              {{ tr('Sec_2fa_Require_Help', 'Staff must enter a code from an authenticator app after their password. Anyone can turn it on for themselves from their profile; this makes it mandatory. Users without it are walked through setup at their next sign-in.') }}
            </div>
            <div v-if="twoFactorStats.migrated" class="setting-help" style="margin-top: 4px">
              <a-space wrap :size="6">
                <a-tag color="processing" style="margin: 0">
                  {{ tr('Sec_2fa_Stats', ':enabled of :total active users have it on')
                    .replace(':enabled', twoFactorStats.enabled).replace(':total', twoFactorStats.total) }}
                </a-tag>
                <a-tag v-if="saved.two_factor_required && twoFactorStats.pending" color="warning" style="margin: 0">
                  {{ tr('Sec_2fa_Pending', ':count still to set it up').replace(':count', twoFactorStats.pending) }}
                </a-tag>
                <a v-if="saved.two_factor_required && twoFactorStats.pending" @click.prevent="$router.push({ path: '/users', query: { two_factor: 'pending' } })">
                  {{ tr('Sec_2fa_See_Who', 'See who') }}
                </a>
              </a-space>
            </div>
          </div>
          <a-switch v-model:checked="form.two_factor_required" />
        </div>

        <div v-if="form.two_factor_required" class="setting-row">
          <div>
            <div class="setting-label">{{ tr('Sec_2fa_Roles', 'Required for') }}</div>
            <div class="setting-help">{{ tr('Sec_2fa_Roles_Help', 'Leave empty to require it for every user, or pick the roles that must use it.') }}</div>
          </div>
          <a-select
            v-model:value="form.two_factor_roles" mode="multiple" :options="roleOptions" allow-clear
            :placeholder="tr('Sec_2fa_All_Users', 'All users')" style="width: 280px" option-filter-prop="label"
          />
        </div>

        <div v-if="form.two_factor_required" class="setting-row">
          <div>
            <div class="setting-label">{{ tr('Sec_2fa_Grace', 'Grace period') }}</div>
            <div class="setting-help">
              {{ tr('Sec_2fa_Grace_Help', 'Lets users choose “Remind me later” on the setup screen until the deadline, so nobody is stopped in the middle of a shift. Counted from when the requirement was switched on.') }}
              <template v-if="twoFactorStats.grace_until">
                <br><strong>{{ tr('Sec_2fa_Grace_Ends', 'Setup becomes mandatory on :date.').replace(':date', fmtDateTime(twoFactorStats.grace_until * 1000)) }}</strong>
              </template>
            </div>
          </div>
          <a-select v-model:value="form.two_factor_grace_days" :options="graceOptions" style="width: 220px" />
        </div>

        <div v-if="saved.two_factor_required" class="setting-row">
          <div>
            <div class="setting-label">{{ tr('Sec_2fa_Apply', 'Apply now') }}</div>
            <div class="setting-help">
              {{ tr('Sec_2fa_Apply_Help', 'People already signed in are only asked at their next sign-in, which can be months away with “Remember me”. This signs out everyone who must set up 2FA and hasn’t, on every device, so they set it up when they sign back in. You stay signed in.') }}
            </div>
          </div>
          <a-popconfirm
            :title="tr('Sec_2fa_Apply_Confirm', 'Sign out :count user(s) now?').replace(':count', twoFactorStats.pending)"
            :ok-text="tr('Sec_2fa_Apply_Ok', 'Sign them out')" :cancel-text="$t('Cancel')"
            :disabled="!twoFactorStats.pending || twoFactorDirty" @confirm="applyTwoFactor"
          >
            <a-tooltip :title="twoFactorDirty ? tr('Sec_2fa_Apply_Save_First', 'Save your changes first') : ''">
              <a-button :loading="applying" :disabled="!twoFactorStats.pending || twoFactorDirty">
                <LogoutOutlined /> {{ tr('Sec_2fa_Apply_Button', 'Sign out users without 2FA') }}
              </a-button>
            </a-tooltip>
          </a-popconfirm>
        </div>

        <div class="setting-row">
          <div>
            <div class="setting-label">{{ tr('Sec_2fa_Trust', 'Remember trusted devices') }}</div>
            <div class="setting-help">{{ tr('Sec_2fa_Trust_Help', 'Offers “Don’t ask again on this device” on the code screen. Turning 2FA off or resetting it forgets every trusted device.') }}</div>
          </div>
          <a-select v-model:value="form.two_factor_trust_days" :options="trustOptions" style="width: 180px" />
        </div>
      </a-card>

      <!-- ============================== Sessions ============================== -->
      <a-card size="small" class="sec-card">
        <template #title><ClockCircleOutlined /> {{ tr('Sec_Sessions', 'Sessions') }}</template>
        <div class="setting-row first">
          <div>
            <div class="setting-label">{{ $t('Set_Inactivity_Auto_Logout') }}</div>
            <div class="setting-help">
              {{ $t('Set_Inactivity_Auto_Logout_Help') }}
              {{ tr('Sec_Remember_Me_Off', 'While this is on, "Remember me" is removed from the login page.') }}
            </div>
          </div>
          <a-space wrap>
            <a-radio-group v-model:value="timeoutPreset" size="small">
              <a-radio-button value="0">{{ $t('Set_Never') }}</a-radio-button>
              <a-radio-button v-for="m in TIMEOUT_PRESETS" :key="m" :value="String(m)">{{ minutesLabel(m) }}</a-radio-button>
              <a-radio-button value="custom">{{ $t('Custom') }}</a-radio-button>
            </a-radio-group>
            <a-input-number
              v-if="timeoutPreset === 'custom'"
              v-model:value="form.session_timeout_minutes" :min="5" :max="43200" size="small" style="width: 120px"
              :addon-after="$t('Set_Minutes_Short')"
            />
          </a-space>
        </div>
        <div class="setting-row">
          <div>
            <div class="setting-label">{{ tr('Sec_Single_Session', 'One active session per user') }}</div>
            <div class="setting-help">{{ tr('Sec_Single_Session_Help', 'Signing in on a new device signs that user out everywhere else (including API tokens).') }}</div>
          </div>
          <a-switch v-model:checked="form.single_session" />
        </div>
        <div class="setting-row">
          <div>
            <div class="setting-label">{{ tr('Sec_Record_Activity', 'Record sign-in activity') }}</div>
            <div class="setting-help">{{ tr('Sec_Record_Activity_Help', 'Log every sign-in attempt (successful, failed, blocked) in the list below.') }}</div>
          </div>
          <a-switch v-model:checked="form.log_login_attempts" />
        </div>
        <div class="setting-row">
          <div>
            <div class="setting-label">{{ $t('Set_Login_Devices') }}</div>
            <div class="setting-help">{{ $t('Set_Login_Devices_Help') }}</div>
          </div>
          <a-button size="small" @click="emit('navigate', 'devices')">{{ $t('View') }}</a-button>
        </div>
      </a-card>

      <a-drawer
        v-model:open="guideOpen" placement="right" :width="guideWidth"
        :title="tr('Tfa_Guide_Title', 'Two-factor authentication guide')"
      >
        <TwoFactorGuide
          :required="!!form.two_factor_required" :role-ids="form.two_factor_roles || []" :roles="roles"
          :trust-days="Number(form.two_factor_trust_days) || 0" :stats="twoFactorStats"
          @close="guideOpen = false"
        />
      </a-drawer>

      <div class="save-bar">
        <a-button type="primary" :loading="saving" :disabled="!migrated" @click="save">{{ $t('submit') }}</a-button>
      </div>

      <!-- ============================== Activity ============================== -->
      <a-card size="small" class="sec-card">
        <template #title><HistoryOutlined /> {{ tr('Sec_Activity', 'Sign-in activity') }}</template>

        <template v-if="locked.length">
          <div class="setting-label" style="margin-bottom: 8px"><StopOutlined style="color: #cf1322" /> {{ tr('Sec_Blocked_Now', 'Blocked right now') }}</div>
          <a-table
            :columns="lockedColumns" :data-source="locked" :pagination="false" size="small"
            :row-key="r => (r.email || '') + '|' + (r.ip_address || '')" :scroll="{ x: 'max-content' }"
            style="margin-bottom: 20px"
          >
            <template #bodyCell="{ column, record }">
              <template v-if="column.key === 'left'">{{ secondsLabel(record.seconds_left) }}</template>
              <template v-else-if="column.key === 'actions'">
                <a-button size="small" :loading="unlocking === record.email + '|' + record.ip_address" @click="unlock(record)">
                  <UnlockOutlined /> {{ tr('Sec_Unblock', 'Unblock') }}
                </a-button>
              </template>
            </template>
          </a-table>
        </template>

        <div class="activity-toolbar">
          <a-space wrap>
            <a-select v-model:value="filters.event" :options="eventOptions" style="width: 200px" allow-clear :placeholder="tr('Sec_All_Events', 'All events')" @change="reload" />
            <a-input-search v-model:value="filters.search" :placeholder="tr('Sec_Email_Or_Ip', 'Email or IP')" style="width: 220px" allow-clear @search="reload" />
            <a-button @click="loadActivity"><ReloadOutlined /></a-button>
          </a-space>
          <a-popconfirm :title="tr('Sec_Clear_Log_Confirm', 'Delete the whole sign-in log?')" :ok-text="$t('Delete')" :cancel-text="$t('Cancel')" @confirm="clearLog">
            <a-button danger size="small" :disabled="!attempts.length"><DeleteOutlined /> {{ tr('Sec_Clear_Log', 'Clear log') }}</a-button>
          </a-popconfirm>
        </div>

        <a-table
          :columns="attemptColumns" :data-source="attempts" :loading="activityLoading" size="small"
          :row-key="r => r.id" :scroll="{ x: 'max-content' }"
          :pagination="{ current: filters.page, pageSize: filters.limit, total: totalRows, showSizeChanger: false, size: 'small' }"
          @change="p => { filters.page = p.current; loadActivity(); }"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'event'">
              <a-tag :color="EVENTS[record.event]?.color">{{ EVENTS[record.event]?.label || record.event }}</a-tag>
            </template>
          </template>
          <template #emptyText>
            <a-empty
              :description="saved.log_login_attempts ? tr('Sec_No_Attempts', 'No sign-in attempts recorded yet.') : tr('Sec_Recording_Off', 'Recording is off.')"
              style="padding: 24px 0"
            />
          </template>
        </a-table>
        <div class="setting-help" style="max-width: none; margin-top: 8px">{{ tr('Sec_Retention', 'Entries older than 90 days are removed automatically.') }}</div>
      </a-card>
    </template>
  </div>
</template>

<script setup>
/**
 * System Settings > Security (staff login).
 * - GET security/login-security → {options, providers[{value,label,script,class}],
 *   image_captcha, migrated, roles[{id,name}], two_factor{migrated,enabled,total}}; options mirror App\Support\Security\SecurityOptions
 *   (captcha_secret_key always '', captcha_secret_set flag instead). The
 *   inactivity timeout is the settings.session_timeout_minutes column that
 *   SetSessionConfig turns into the session lifetime.
 * - PUT security/login-security {…options, captcha_test_token}: an external
 *   CAPTCHA with new keys is refused unless the token from the test widget
 *   verifies with them (protects against locking everyone out)
 * - GET security/login-attempts {event, search, page, limit} → {attempts,
 *   totalRows, locked[{email, ip_address, seconds_left}]}
 * - POST security/login-attempts/unlock {email, ip_address}; DELETE security/login-attempts
 * Strings use Sec_* keys with an English fallback until the seeder is re-run.
 */
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import { message } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import dayjs from 'dayjs';
import {
  RobotOutlined, LockOutlined, ClockCircleOutlined, HistoryOutlined, StopOutlined, UnlockOutlined,
  ReloadOutlined, DeleteOutlined, PlayCircleOutlined, CheckCircleOutlined, SafetyCertificateOutlined, ReadOutlined,
  LogoutOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import TwoFactorGuide from './TwoFactorGuide.vue';
import http from '../../lib/http';

const emit = defineEmits(['navigate']);
const { t } = useI18n();
const tr = (key, fallback) => {
  const out = t(key);
  return out === key ? fallback : out;
};

const LOCKOUT_PRESETS = [1, 2, 5, 10, 15, 30, 60];
const TIMEOUT_PRESETS = [15, 30, 60, 240, 480, 720];
const EVENTS = computed(() => ({
  success: { label: tr('Sec_Event_Success', 'Signed in'), color: 'success' },
  failed: { label: tr('Sec_Event_Failed', 'Wrong password'), color: 'warning' },
  locked_out: { label: tr('Sec_Event_Locked_Out', 'Blocked (limit reached)'), color: 'error' },
  blocked: { label: tr('Sec_Event_Blocked', 'Refused while blocked'), color: 'volcano' },
  captcha_failed: { label: tr('Sec_Event_Captcha_Failed', 'CAPTCHA failed'), color: 'purple' },
  two_factor_failed: { label: tr('Sec_Event_2fa_Failed', 'Wrong 2FA code'), color: 'magenta' },
}));

const host = window.location.host;
const loading = ref(true);
const saving = ref(false);
const migrated = ref(true);
const imageCaptcha = ref(true);
const providers = ref([]);
const secretSet = ref(false);
const roles = ref([]);
const twoFactorStats = ref({ migrated: false, enabled: 0, total: 0, pending: 0, grace_until: 0 });
const applying = ref(false);
const guideOpen = ref(false);
const guideWidth = Math.min(560, window.innerWidth);
const saved = ref({}); // last saved options, to detect key changes
const form = reactive({});

/* ------------------------------------------------------------- labels */
function minutesLabel(m) {
  if (m < 60) return `${m} min`;
  const h = m / 60;
  return Number.isInteger(h) ? `${h} h` : `${m} min`;
}
function secondsLabel(s) {
  if (s < 60) return `${s} s`;
  return `${Math.ceil(s / 60)} min`;
}

const providerOptions = computed(() => [
  { value: 'builtin', label: tr('Sec_Provider_Builtin', 'Built-in image (no keys)') },
  ...providers.value.map(p => ({ value: p.value, label: p.label })),
]);
const scopeOptions = computed(() => [
  { value: 'email_ip', label: tr('Sec_Scope_Email_Ip', 'That email from that IP (recommended)') },
  { value: 'email', label: tr('Sec_Scope_Email', 'That email from every IP') },
  { value: 'ip', label: tr('Sec_Scope_Ip', 'That IP for every email') },
]);
const roleOptions = computed(() => roles.value.map(r => ({ value: r.id, label: r.name })));
const TRUST_DAYS = [7, 14, 30, 60, 90];
const GRACE_DAYS = [3, 7, 14, 30];
const graceOptions = computed(() => [
  { value: 0, label: tr('Sec_2fa_Grace_Off', 'None — set up at next sign-in') },
  ...GRACE_DAYS.map(d => ({ value: d, label: tr('Sec_2fa_Trust_Days', ':days days').replace(':days', d) })),
]);
// "Apply now" acts on the SAVED settings, so it waits for unsaved 2FA edits.
const twoFactorDirty = computed(() => !!form.two_factor_required !== !!saved.value.two_factor_required
  || [...(form.two_factor_roles || [])].sort().join(',') !== [...(saved.value.two_factor_roles || [])].sort().join(','));
const trustOptions = computed(() => [
  { value: 0, label: tr('Sec_2fa_Trust_Off', 'Always ask for a code') },
  ...TRUST_DAYS.map(d => ({ value: d, label: tr('Sec_2fa_Trust_Days', ':days days').replace(':days', d) })),
]);
const eventOptions = computed(() => Object.entries(EVENTS.value).map(([value, e]) => ({ value, label: e.label })));

/* ------------------------------------------------------------- presets */
function presetModel(field, presets, zeroIsPreset) {
  const custom = ref(false);
  return computed({
    get() {
      const v = Number(form[field]) || 0;
      if (custom.value) return 'custom';
      if (zeroIsPreset && v <= 0) return '0';
      return presets.includes(v) ? String(v) : 'custom';
    },
    set(val) {
      custom.value = val === 'custom';
      if (val !== 'custom') form[field] = Number(val);
      else if (!(Number(form[field]) > 0)) form[field] = presets[presets.length - 1];
    },
  });
}
const lockoutPreset = presetModel('lockout_minutes', LOCKOUT_PRESETS, false);
const timeoutPreset = presetModel('session_timeout_minutes', TIMEOUT_PRESETS, true);

/* ------------------------------------------------------------- key test */
const testHost = ref(null);
const testToken = ref('');
const testError = ref('');
const testLoading = ref(false);
const GLOBALS = { recaptcha: 'grecaptcha', hcaptcha: 'hcaptcha', turnstile: 'turnstile' };
const scriptPromises = {};

const keysChanged = computed(() => form.captcha_enabled
  && form.captcha_provider !== 'builtin'
  && (!saved.value.captcha_enabled
    || saved.value.captcha_provider !== form.captcha_provider
    || saved.value.captcha_site_key !== (form.captcha_site_key || '').trim()
    || !!form.captcha_secret_key));

function resetTest() {
  testToken.value = '';
  testError.value = '';
  if (testHost.value) testHost.value.innerHTML = '';
}

// reCAPTCHA, hCaptcha and Turnstile share the explicit-render API:
// <global>.render(element, { sitekey, callback }).
function loadProviderScript(name) {
  const g = GLOBALS[name];
  if (window[g]?.render) return Promise.resolve();
  if (scriptPromises[name]) return scriptPromises[name];
  const provider = providers.value.find(p => p.value === name);
  scriptPromises[name] = new Promise((resolve, reject) => {
    const cb = `__secCaptchaReady_${name}`;
    window[cb] = () => resolve();
    const s = document.createElement('script');
    s.src = `${provider.script}?render=explicit&onload=${cb}`;
    s.async = true;
    s.onerror = () => { delete scriptPromises[name]; reject(new Error('load')); };
    document.head.appendChild(s);
  });
  return scriptPromises[name];
}

async function loadTest() {
  resetTest();
  const siteKey = (form.captcha_site_key || '').trim();
  if (!siteKey) { testError.value = tr('Sec_Need_Site_Key', 'Enter the site key first.'); return; }
  testLoading.value = true;
  try {
    await loadProviderScript(form.captcha_provider);
    const el = document.createElement('div');
    testHost.value.appendChild(el);
    window[GLOBALS[form.captcha_provider]].render(el, {
      sitekey: siteKey,
      callback: token => { testToken.value = token; testError.value = ''; },
    });
  } catch (e) {
    testError.value = tr('Sec_Test_Load_Failed', 'The challenge could not load. Check the site key and the allowed domains.');
  } finally {
    testLoading.value = false;
  }
}

/* ------------------------------------------------------------- load / save */
function applyOptions(options) {
  const { captcha_secret_set: set, ...rest } = options || {};
  Object.assign(form, rest, { captcha_secret_key: '' });
  secretSet.value = !!set;
  saved.value = { ...rest };
}

async function load() {
  try {
    const d = await http.get('security/login-security');
    providers.value = d.providers || [];
    imageCaptcha.value = d.image_captcha !== false;
    migrated.value = !!d.migrated;
    roles.value = d.roles || [];
    if (d.two_factor) twoFactorStats.value = d.two_factor;
    applyOptions(d.options);
  } catch (e) {
    message.error(t('InvalidData'));
  } finally {
    loading.value = false;
  }
}

async function applyTwoFactor() {
  applying.value = true;
  try {
    const d = await http.post('security/two-factor/apply');
    if (d.two_factor) twoFactorStats.value = d.two_factor;
    message.success(tr('Sec_2fa_Applied', ':count user(s) signed out.').replace(':count', d.signed_out || 0));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    applying.value = false;
  }
}

async function save() {
  if (keysChanged.value && !testToken.value) {
    message.warning(tr('Sec_Test_Required', 'Pass the CAPTCHA test with the new keys before saving.'));
    return;
  }
  saving.value = true;
  try {
    const payload = { ...form };
    if (testToken.value) payload.captcha_test_token = testToken.value;
    const d = await http.put('security/login-security', payload);
    applyOptions(d.options);
    // Pending count and grace deadline depend on what was just saved.
    http.get('security/login-security').then(r => { if (r.two_factor) twoFactorStats.value = r.two_factor; }).catch(() => {});
    resetTest();
    message.success(t('Successfully_Updated'));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    saving.value = false;
  }
}

/* ------------------------------------------------------------- activity */
const DATE_FMT = (() => {
  try { return localStorage.getItem('app_date_format') || 'YYYY-MM-DD'; } catch (e) { return 'YYYY-MM-DD'; }
})();
const fmtDateTime = v => (v ? dayjs(v).format(`${DATE_FMT} HH:mm:ss`) : '');

const attempts = ref([]);
const locked = ref([]);
const totalRows = ref(0);
const activityLoading = ref(false);
const unlocking = ref('');
const filters = reactive({ event: undefined, search: '', page: 1, limit: 15 });

const attemptColumns = computed(() => [
  { title: tr('Sec_Col_Time', 'Time'), dataIndex: 'created_at', key: 'created_at', customRender: ({ text }) => fmtDateTime(text) },
  { title: tr('Sec_Col_Event', 'Event'), dataIndex: 'event', key: 'event' },
  { title: tr('Sec_Col_Email', 'Email'), dataIndex: 'email', key: 'email' },
  { title: tr('Sec_Col_Ip', 'IP Address'), dataIndex: 'ip_address', key: 'ip_address' },
  { title: tr('Sec_Col_Device', 'Device / Browser'), dataIndex: 'device', key: 'device' },
]);
const lockedColumns = computed(() => [
  { title: tr('Sec_Col_Email', 'Email'), dataIndex: 'email', key: 'email' },
  { title: tr('Sec_Col_Ip', 'IP Address'), dataIndex: 'ip_address', key: 'ip_address' },
  { title: tr('Sec_Col_Blocked_At', 'Blocked at'), dataIndex: 'locked_at', key: 'locked_at', customRender: ({ text }) => fmtDateTime(text) },
  { title: tr('Sec_Col_Time_Left', 'Time left'), key: 'left' },
  { title: '', key: 'actions', align: 'right' },
]);

function reload() {
  filters.page = 1;
  loadActivity();
}

async function loadActivity() {
  activityLoading.value = true;
  try {
    const d = await http.get('security/login-attempts', {
      event: filters.event || '',
      search: filters.search || '',
      page: filters.page,
      limit: filters.limit,
    });
    attempts.value = d.attempts || [];
    locked.value = d.locked || [];
    totalRows.value = d.totalRows || 0;
  } catch (e) {
    /* tab still usable without the log */
  } finally {
    activityLoading.value = false;
  }
}

async function unlock(record) {
  unlocking.value = record.email + '|' + record.ip_address;
  try {
    await http.post('security/login-attempts/unlock', { email: record.email, ip_address: record.ip_address });
    message.success(tr('Sec_Unblocked', 'Unblocked'));
    loadActivity();
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    unlocking.value = '';
  }
}

async function clearLog() {
  try {
    await http.delete('security/login-attempts');
    reload();
  } catch (e) {
    message.error(t('InvalidData'));
  }
}

// "Time left" counts down without refetching.
let ticker = null;
onMounted(() => {
  load();
  loadActivity();
  ticker = setInterval(() => {
    if (!locked.value.length) return;
    locked.value = locked.value
      .map(r => ({ ...r, seconds_left: r.seconds_left - 1 }))
      .filter(r => r.seconds_left > 0);
  }, 1000);
});
onBeforeUnmount(() => clearInterval(ticker));
</script>

<style scoped>
.sec-card {
  margin-bottom: 16px;
}
.setting-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 14px 0;
  border-top: 1px solid rgba(128, 128, 128, 0.12);
  flex-wrap: wrap;
}
.setting-row.first {
  border-top: none;
  padding-top: 4px;
}
.setting-label {
  font-weight: 500;
}
.setting-help {
  font-size: 12px;
  color: #8c8c8c;
  max-width: 520px;
}
.sub-block {
  padding: 4px 0 12px;
}
.tight {
  margin-bottom: 8px;
}
.test-box {
  border: 1px dashed rgba(128, 128, 128, 0.35);
  border-radius: 8px;
  padding: 12px;
}
.test-error {
  color: #cf1322;
  font-size: 12px;
  margin-top: 6px;
}
.save-bar {
  margin: 4px 0 20px;
}
.activity-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}
</style>
