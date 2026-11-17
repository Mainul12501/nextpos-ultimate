<template>
  <div class="page">
    <PageHeader :title="$t('update_settings')" :breadcrumb="[$t('Settings'), $t('Update_Log')]" />

    <a-card>
      <a-tabs>
        <!-- ============================ Automatic upgrade (upload-based) ============================ -->
        <a-tab-pane key="system" :tab="$t('Automatic_Update')">

          <a-alert
            type="error" show-icon style="margin-bottom: 16px"
            :message="$t('Upd_Note_Code_Changes_Lost')"
          />

          <!-- Recovery / interrupted run banner -->
          <a-alert
            v-if="needsAttention"
            type="error" show-icon style="margin-bottom: 16px"
            :message="$t('Update_Needs_Attention')"
          >
            <template #description>
              <div style="margin-bottom: 10px">
                {{ attentionText }}
                <div v-if="state && state.error" style="margin-top: 6px; font-size: 12px">
                  <b>{{ $t('Failed_Step') }}:</b> {{ phaseLabel(state.error.phase) }} — {{ state.error.message }}
                </div>
              </div>
              <a-space wrap>
                <a-button size="small" type="primary" :loading="stepping" @click="resumeRun">{{ $t('Resume_Update') }}</a-button>
                <a-button size="small" danger :loading="stepping" @click="confirmRollback">{{ $t('Rollback_Now') }}</a-button>
                <a-button size="small" @click="openRecoveryConsole">{{ $t('Open_Recovery_Console') }}</a-button>
              </a-space>
            </template>
          </a-alert>

          <a-row :gutter="[16, 16]">
            <!-- Version + upload -->
            <a-col :xs="24" :lg="10">
              <a-card size="small" style="height: 100%">
                <a-statistic :title="$t('Installed_Version')" :value="currentVersion || '—'" />
                <div style="font-size: 12px; color: #8c8c8c; margin-bottom: 16px">{{ $t('Currently_Running') }}</div>

                <template v-if="!running">
                  <a-upload-dragger
                    v-if="!packageInfo"
                    :accept="'.zip'"
                    :show-upload-list="false"
                    :custom-request="onUploadRequest"
                    :disabled="uploading"
                  >
                    <p style="font-size: 34px; color: #1677ff; margin-bottom: 6px"><InboxOutlined /></p>
                    <p style="font-weight: 600">{{ $t('Upload_Update_Package') }}</p>
                    <p style="font-size: 12px; color: #8c8c8c">{{ $t('Upload_Update_Package_Hint') }}</p>
                  </a-upload-dragger>

                  <div v-if="uploading" style="margin-top: 12px">
                    <a-progress :percent="uploadPercent" size="small" />
                    <div style="font-size: 12px; color: #8c8c8c">{{ $t('Uploading_Package') }}…</div>
                  </div>

                  <template v-if="packageInfo && !uploading">
                    <a-descriptions size="small" :column="1" bordered style="margin-top: 4px">
                      <a-descriptions-item :label="$t('File')">{{ packageInfo.filename }}</a-descriptions-item>
                      <a-descriptions-item :label="$t('Size')">{{ formatBytes(packageInfo.size) }}</a-descriptions-item>
                      <a-descriptions-item v-if="packageInfo.version" :label="$t('Version')">v{{ packageInfo.version }}</a-descriptions-item>
                    </a-descriptions>
                    <a-space style="margin-top: 12px" wrap>
                      <a-button :loading="validating" @click="validatePackage">
                        <SafetyCertificateOutlined v-if="!validating" /> {{ $t('Validate_Package') }}
                      </a-button>
                      <a-button
                        v-if="report && report.ok" type="primary" danger
                        @click="confirmStart"
                      >
                        <CloudUploadOutlined /> {{ $t('Update_Now') }}
                      </a-button>
                      <a-button @click="removePackage"><DeleteOutlined /> {{ $t('Remove') }}</a-button>
                    </a-space>
                  </template>
                </template>
                <a-alert v-else type="info" show-icon :message="$t('Update_In_Progress_Banner')" style="margin-top: 4px" />
              </a-card>
            </a-col>

            <!-- Validation report -->
            <a-col :xs="24" :lg="14">
              <a-card size="small" style="height: 100%" :title="$t('Pre_Update_Checks')">
                <template v-if="report">
                  <div style="max-height: 300px; overflow: auto">
                    <div
                      v-for="(check, i) in report.checks" :key="i"
                      style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px"
                    >
                      <CheckCircleFilled v-if="check.ok" style="color: #52c41a; margin-top: 3px" />
                      <ExclamationCircleFilled v-else-if="check.level === 'warning'" style="color: #faad14; margin-top: 3px" />
                      <CloseCircleFilled v-else style="color: #ff4d4f; margin-top: 3px" />
                      <div style="font-size: 13px">
                        {{ check.label }}
                        <div v-if="!check.ok && check.detail" style="font-size: 12px; color: #8c8c8c">{{ check.detail }}</div>
                      </div>
                    </div>
                  </div>
                  <a-divider style="margin: 12px 0" />
                  <a-tag :color="report.ok ? 'success' : 'error'">
                    {{ report.ok ? $t('Package_Ready_To_Install') : $t('Package_Cannot_Be_Installed') }}
                  </a-tag>
                </template>
                <div v-else style="color: #8c8c8c; font-size: 13px">{{ $t('Upload_Then_Validate_Hint') }}</div>
              </a-card>
            </a-col>

            <!-- Live run progress -->
            <a-col :span="24" v-if="state">
              <a-card size="small">
                <template #title>
                  {{ state.type === 'restore' ? $t('Restoring_Backup') : (isRollingBack ? $t('Rolling_Back') : $t('Update_Progress')) }}
                  <a-tag style="margin-left: 8px" :color="statusColor">{{ statusLabel }}</a-tag>
                </template>
                <template #extra>
                  <span v-if="state.from_version" style="font-size: 12px; color: #8c8c8c">
                    v{{ state.from_version }} → v{{ state.to_version || '?' }}
                  </span>
                </template>

                <a-progress
                  :percent="state.percent || 0"
                  :status="progressStatus"
                />
                <a-steps
                  v-if="state.type === 'update' && !isRollingBack"
                  :current="currentStepIndex" size="small" style="margin: 16px 0 4px"
                  :status="state.status === 'failed' || state.status === 'recovery_required' ? 'error' : undefined"
                  :items="updateSteps.map(s => ({ title: s.label }))"
                />
                <div v-else style="margin: 12px 0 4px; font-size: 13px">
                  <b>{{ $t('Current_Step') }}:</b> {{ phaseLabel(state.phase) }}
                </div>

                <a-alert
                  v-if="state.maintenance_secret"
                  type="warning" show-icon style="margin-top: 12px"
                  :message="$t('Maintenance_Bypass_Hint')"
                >
                  <template #description>
                    <a :href="origin + '/' + state.maintenance_secret" target="_blank">{{ origin }}/{{ state.maintenance_secret }}</a>
                    &nbsp;•&nbsp;
                    <a :href="origin + '/system-update/recovery'" target="_blank">{{ $t('Open_Recovery_Console') }}</a>
                  </template>
                </a-alert>

                <a-alert
                  v-if="state.status === 'completed'"
                  type="success" show-icon style="margin-top: 12px"
                  :message="state.type === 'restore' ? $t('Backup_Restored_Successfully') : $t('Update_Successful')"
                  :description="$t('Reload_After_Update_Hint')"
                >
                </a-alert>
                <a-alert
                  v-if="state.status === 'rolled_back'"
                  type="warning" show-icon style="margin-top: 12px"
                  :message="$t('Update_failed_rolled_back')"
                  :description="state.error ? (phaseLabel(state.error.phase) + ' — ' + state.error.message) : ''"
                />

                <a-space style="margin-top: 12px" wrap>
                  <a-button v-if="state.status === 'completed'" type="primary" @click="reloadApp">{{ $t('Reload_Application') }}</a-button>
                  <a-button v-if="isTerminal" @click="discardRun">{{ $t('Dismiss') }}</a-button>
                </a-space>

                <a-collapse v-if="(state.log_tail || []).length" style="margin-top: 14px" ghost>
                  <a-collapse-panel key="log" :header="$t('Technical_Log')">
                    <pre class="update-log">{{ state.log_tail.join('\n') }}</pre>
                  </a-collapse-panel>
                </a-collapse>
              </a-card>
            </a-col>

            <!-- Update history -->
            <a-col :xs="24" :lg="14">
              <a-card size="small" :title="$t('Update_History')">
                <a-table
                  :columns="historyColumns" :data-source="history"
                  size="small" :pagination="{ pageSize: 8 }" row-key="id"
                >
                  <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'status'">
                      <a-tooltip :title="record.error ? record.error.message : ''">
                        <a-tag :color="historyStatusColor(record.status)">{{ historyStatusLabel(record.status) }}</a-tag>
                      </a-tooltip>
                    </template>
                    <template v-else-if="column.key === 'versions'">
                      {{ record.type === 'restore' ? $t('Restore') + ' ' : '' }}
                      v{{ record.from_version || '?' }} → v{{ record.to_version || '?' }}
                    </template>
                    <template v-else-if="column.key === 'duration'">
                      {{ record.duration != null ? formatDuration(record.duration) : '—' }}
                    </template>
                    <template v-else-if="column.key === 'user'">
                      {{ record.user ? record.user.name : '—' }}
                    </template>
                  </template>
                </a-table>
              </a-card>
            </a-col>

            <!-- Backups -->
            <a-col :xs="24" :lg="10">
              <a-card size="small" :title="$t('Update_Backups')">
                <template #extra>
                  <a-space size="small">
                    <span style="font-size: 12px; color: #8c8c8c">{{ $t('Keep_Last') }}</span>
                    <a-input-number v-model:value="retention" :min="1" :max="20" size="small" style="width: 60px" />
                    <a-button size="small" @click="saveRetention">{{ $t('Save') }}</a-button>
                  </a-space>
                </template>
                <a-table
                  :columns="backupColumns" :data-source="backups"
                  size="small" :pagination="false" row-key="id"
                >
                  <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'backup'">
                      <div style="font-size: 13px">v{{ record.version || '?' }} <a-tag v-if="!record.complete" color="warning" style="margin-left: 4px">{{ $t('Incomplete') }}</a-tag></div>
                      <div style="font-size: 12px; color: #8c8c8c">{{ record.created_at }} • {{ formatBytes(record.files_size + record.db_size) }}</div>
                    </template>
                    <template v-else-if="column.key === 'actions'">
                      <a-space size="small">
                        <a-tooltip :title="$t('Restore_This_Backup')">
                          <a-button size="small" :disabled="!record.complete || running" @click="confirmRestore(record)"><HistoryOutlined /></a-button>
                        </a-tooltip>
                        <a-tooltip :title="$t('Del')">
                          <a-button size="small" danger :disabled="running" @click="confirmDeleteBackup(record)"><DeleteOutlined /></a-button>
                        </a-tooltip>
                      </a-space>
                    </template>
                  </template>
                </a-table>
                <div style="font-size: 12px; color: #8c8c8c; margin-top: 8px">{{ $t('Backups_Stored_Hint') }}</div>
              </a-card>
            </a-col>
          </a-row>
        </a-tab-pane>

        <!-- ============================ Manual upgrade ============================ -->
        <a-tab-pane key="manual" :tab="$t('Manual_Update')">
          <h4>{{ $t('Upd_Manual_Steps_Intro') }}</h4>
          <a-alert type="error" style="margin: 8px 0" :message="$t('Upd_Note_Code_Changes_Lost')" />
          <a-alert type="error" style="margin-bottom: 16px" :message="$t('Upd_Note_Permission_Only', { perm: 'update_settings' })" />
          <ol class="manual-steps">
            <i18n-t keypath="Upd_Manual_Step_Backup_Db" tag="li">
              <template #backup><router-link to="/settings/backup">{{ $t('Backup') }}</router-link></template>
              <template #path><strong>/storage/app/public/backup</strong></template>
            </i18n-t>
            <li>{{ $t('Upd_Manual_Step_Backup_Files') }}</li>
            <li>{{ $t('Upd_Manual_Step_Download') }}</li>
            <li>
              <i18n-t keypath="Upd_Manual_Step_Remove" tag="span">
                <template #except><strong>{{ $t('Upd_Except') }}</strong></template>
              </i18n-t>
              <ul>
                <i18n-t keypath="Upd_File_Label" tag="li"><template #name><strong>.env</strong></template></i18n-t>
                <i18n-t keypath="Upd_Folder_Label" tag="li"><template #name><strong>storage</strong></template></i18n-t>
                <i18n-t keypath="Upd_Folder_Label" tag="li"><template #name><strong>/public/images</strong></template></i18n-t>
              </ul>
            </li>
            <i18n-t keypath="Upd_Manual_Step_Reupload" tag="li">
              <template #except><strong>{{ $t('Upd_Except') }}</strong></template>
            </i18n-t>
            <i18n-t keypath="Upd_Manual_Step_Visit" tag="li">
              <template #url><strong><a :href="origin + '/update'" target="_blank">{{ origin }}/update</a></strong></template>
            </i18n-t>
            <li>{{ $t('Upd_Manual_Step_Clear_Cache') }}</li>
            <li>{{ $t('Upd_Manual_Step_Done') }}</li>
          </ol>
          <a-alert type="error" :message="$t('Upd_Note_Blank_Pages')" />
        </a-tab-pane>

        <!-- ============================ Update Guide ============================ -->
        <a-tab-pane key="guide" :tab="$t('Update_Guide')">
          <div class="guide">

            <h3>{{ $t('Upd_Guide_Before_Title') }}</h3>
            <ol class="manual-steps">
              <i18n-t keypath="Upd_Guide_Before_Download" tag="li">
                <template #package><strong>{{ $t('Upd_Latest_Full_Zip') }}</strong></template>
                <template #path><em>Downloads → Installable files</em></template>
              </i18n-t>
              <i18n-t keypath="Upd_Guide_Before_Admin" tag="li">
                <template #perm><strong>"update_settings"</strong></template>
              </i18n-t>
              <li>{{ $t('Upd_Guide_Before_Quiet') }}</li>
              <i18n-t keypath="Upd_Guide_Before_No_Manual" tag="li">
                <template #not><strong>{{ $t('Upd_Not') }}</strong></template>
              </i18n-t>
            </ol>
            <a-alert type="warning" show-icon style="margin: 8px 0 20px"
              :message="$t('Upd_Guide_Code_Overwritten')" />

            <h3>{{ $t('Upd_Guide_Running_Title') }}</h3>
            <ol class="manual-steps">
              <i18n-t keypath="Upd_Guide_Run_Open" tag="li">
                <template #path><strong>{{ $t('Settings') }} → {{ $t('System_Update') }}</strong></template>
                <template #tab><strong>{{ $t('Automatic_Update') }}</strong></template>
              </i18n-t>
              <i18n-t keypath="Upd_Guide_Run_Upload" tag="li">
                <template #upload><strong>{{ $t('Upd_Upload_The_Zip') }}</strong></template>
              </i18n-t>
              <i18n-t keypath="Upd_Guide_Run_Validation" tag="li">
                <template #auto><strong>{{ $t('Upd_Validation_Runs_Automatically') }}</strong></template>
                <template #button>"{{ $t('Validate_Package') }}"</template>
              </i18n-t>
              <i18n-t keypath="Upd_Guide_Run_Review" tag="li">
                <template #panel><strong>{{ $t('Pre_Update_Checks') }}</strong></template>
                <template #button><strong>{{ $t('Update_Now') }}</strong></template>
              </i18n-t>
              <li>
                <i18n-t keypath="Upd_Guide_Run_Click" tag="span">
                  <template #button><strong>{{ $t('Update_Now') }}</strong></template>
                  <template #keep><strong>{{ $t('Upd_Keep_Page_Open') }}</strong></template>
                </i18n-t>
                <ul>
                  <i18n-t keypath="Upd_Guide_Phase_Maintenance" tag="li">
                    <template #label><strong>{{ $t('Maintenance') }}</strong></template>
                  </i18n-t>
                  <i18n-t keypath="Upd_Guide_Phase_Backup" tag="li">
                    <template #label><strong>{{ $t('Creating_Backup') }}</strong></template>
                    <template #env><code>.env</code></template>
                    <template #verified><em>{{ $t('Upd_Verified') }}</em></template>
                    <template #images><code>public/images</code></template>
                    <template #storage><code>storage/</code></template>
                  </i18n-t>
                  <i18n-t keypath="Upd_Guide_Phase_Prepare" tag="li">
                    <template #label><strong>{{ $t('Preparing_Update') }}</strong></template>
                  </i18n-t>
                  <i18n-t keypath="Upd_Guide_Phase_Files" tag="li">
                    <template #label><strong>{{ $t('Updating_Files') }}</strong></template>
                    <template #env><code>.env</code></template>
                    <template #storage><code>storage/</code></template>
                    <template #images><code>public/images/</code></template>
                  </i18n-t>
                  <i18n-t keypath="Upd_Guide_Phase_Database" tag="li">
                    <template #label><strong>{{ $t('Updating_Database') }}</strong></template>
                  </i18n-t>
                  <i18n-t keypath="Upd_Guide_Phase_Cache" tag="li">
                    <template #label><strong>{{ $t('Clearing_Cache') }}</strong></template>
                  </i18n-t>
                  <i18n-t keypath="Upd_Guide_Phase_Verify" tag="li">
                    <template #label><strong>{{ $t('Verifying_Installation') }}</strong></template>
                  </i18n-t>
                  <i18n-t keypath="Upd_Guide_Phase_Done" tag="li">
                    <template #label><strong>{{ $t('Done') }}</strong></template>
                  </i18n-t>
                </ul>
              </li>
              <i18n-t keypath="Upd_Guide_Run_Success" tag="li">
                <template #success><strong>"{{ $t('Upd_Update_Successful_Short') }}"</strong></template>
                <template #reload><strong>{{ $t('Reload_Application') }}</strong></template>
                <template #keys>Ctrl+F5 / Cmd+Shift+R</template>
              </i18n-t>
            </ol>
            <a-alert type="info" show-icon style="margin: 8px 0 20px"
              :message="$t('Upd_Guide_No_Internet')" />

            <h3>{{ $t('Upd_Guide_Wrong_Title') }}</h3>
            <ol class="manual-steps">
              <i18n-t keypath="Upd_Guide_Wrong_Rollback" tag="li">
                <template #label><strong>{{ $t('Upd_Automatic_Rollback') }}</strong></template>
                <template #message><em>"{{ $t('Upd_Failed_Previous_Restored') }}"</em></template>
              </i18n-t>
              <i18n-t keypath="Upd_Guide_Wrong_Interrupted" tag="li">
                <template #label><strong>{{ $t('Upd_Interrupted_Update') }}</strong></template>
                <template #resume><strong>{{ $t('Resume_Update') }}</strong></template>
                <template #rollback><strong>{{ $t('Rollback_Now') }}</strong></template>
              </i18n-t>
              <i18n-t keypath="Upd_Guide_Wrong_Unreachable" tag="li">
                <template #label><strong>{{ $t('Upd_Admin_Unreachable') }}</strong></template>
                <template #console><strong>{{ $t('Upd_Recovery_Console') }}</strong></template>
                <template #path><code>/system-update/recovery</code></template>
              </i18n-t>
              <i18n-t keypath="Upd_Guide_Wrong_Locked_Out" tag="li">
                <template #label><strong>{{ $t('Upd_Locked_Out_Maintenance') }}</strong></template>
                <template #link><strong>{{ $t('Upd_Bypass_Link') }}</strong></template>
              </i18n-t>
              <i18n-t keypath="Upd_Guide_Wrong_Concurrent" tag="li">
                <template #label><strong>{{ $t('Upd_Another_Admin_Updating') }}</strong></template>
                <template #message><em>"{{ $t('Update_In_Progress_Banner') }}"</em></template>
              </i18n-t>
            </ol>

            <h3>{{ $t('Upd_Guide_Backups_Title') }}</h3>
            <ol class="manual-steps">
              <i18n-t keypath="Upd_Guide_Backups_Created" tag="li">
                <template #before><em>{{ $t('Upd_Before') }}</em></template>
                <template #env><code>.env</code></template>
                <template #storage><code>storage/</code></template>
                <template #path><code>storage/app/updater/backups</code></template>
              </i18n-t>
              <i18n-t keypath="Upd_Guide_Backups_Panel" tag="li">
                <template #panel><strong>{{ $t('Update_Backups') }}</strong></template>
                <template #tab>{{ $t('Automatic_Update') }}</template>
                <template #restore><strong>{{ $t('Upd_Restore_Verb') }}</strong></template>
                <template #delete><strong>{{ $t('Upd_Delete_Verb') }}</strong></template>
              </i18n-t>
              <i18n-t keypath="Upd_Guide_Backups_Retention" tag="li">
                <template #keep><strong>"{{ $t('Keep_Last') }} N"</strong></template>
              </i18n-t>
            </ol>

            <h3>{{ $t('Upd_Guide_Manual_Title') }}</h3>
            <i18n-t keypath="Upd_Guide_Manual_Text" tag="p" style="margin-bottom: 8px">
              <template #tab><strong>{{ $t('Manual_Update') }}</strong></template>
            </i18n-t>
            <a-alert type="error" show-icon style="margin-top: 8px"
              :message="$t('Upd_Guide_Clear_Cache_First')" />
          </div>
        </a-tab-pane>
      </a-tabs>
    </a-card>
  </div>
</template>

<script setup>
/**
 * System Update — upload-based resumable updater.
 *
 * The server runs a state machine (api/system-update/*): upload chunks →
 * validate → start → then POST step repeatedly; every call performs one
 * bounded phase (backup, stage, swap, migrate, …) and returns the full
 * status. Steps keep working during maintenance mode (whitelisted routes).
 * If the browser closes mid-run the state survives server-side: on reload
 * the banner offers Resume / Rollback, and /system-update/recovery is a
 * standalone console that works even when the SPA is broken.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { message, Modal } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import {
  InboxOutlined, CheckCircleFilled, CloseCircleFilled, ExclamationCircleFilled,
  SafetyCertificateOutlined, CloudUploadOutlined, DeleteOutlined, HistoryOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import http from '../../lib/http';

const { t } = useI18n();

const CHUNK_SIZE = 2 * 1024 * 1024;
const origin = window.location.origin;

const currentVersion = ref('');
const state = ref(null);
const packageInfo = ref(null);
const report = ref(null);
const history = ref([]);
const backups = ref([]);
const retention = ref(3);

const uploading = ref(false);
const uploadPercent = ref(0);
const validating = ref(false);
const stepping = ref(false);
let pollTimer = null;
let stepErrors = 0;

// --------------------------------------------------------------- computed

const running = computed(() => !!state.value && state.value.status === 'running' && !state.value.stalled);
const isTerminal = computed(() => !!state.value && ['completed', 'failed', 'rolled_back'].includes(state.value.status));
const isRollingBack = computed(() => !!state.value && String(state.value.phase || '').startsWith('rb_'));

const needsAttention = computed(() => {
  if (!state.value) return false;
  if (state.value.status === 'recovery_required') return true;
  return state.value.status === 'running' && state.value.stalled && !stepping.value;
});
const attentionText = computed(() => {
  if (!state.value) return '';
  if (state.value.status === 'recovery_required') return t('Recovery_Required_Text');
  return t('Update_Interrupted_Text');
});

const statusLabel = computed(() => {
  if (!state.value) return '';
  const map = {
    running: state.value.stalled ? t('Interrupted') : t('Running'),
    completed: t('Successful'),
    failed: t('Failed'),
    rolled_back: t('Rolled_Back'),
    recovery_required: t('Recovery_Required'),
  };
  return map[state.value.status] || state.value.status;
});
const statusColor = computed(() => {
  if (!state.value) return 'default';
  if (state.value.status === 'completed') return 'success';
  if (state.value.status === 'running') return state.value.stalled ? 'error' : 'processing';
  if (state.value.status === 'rolled_back') return 'warning';
  return 'error';
});
const progressStatus = computed(() => {
  if (!state.value) return 'normal';
  if (['failed', 'recovery_required'].includes(state.value.status)) return 'exception';
  if (state.value.status === 'completed') return 'success';
  return 'active';
});

// Updater phase codes (app/Services/Updater/UpdateManager) → readable step
// names; an unknown code is shown as-is.
const PHASE_KEYS = {
  init: 'Set_Starting', rst_init: 'Set_Starting',
  backup_db: 'Upd_Phase_Backup_Db', rst_backup_db: 'Upd_Phase_Backup_Db',
  backup_files: 'Upd_Phase_Backup_Files', backup_verify: 'Upd_Phase_Backup_Verify',
  stage_extract: 'Upd_Phase_Extract_Package', stage_validate: 'Upd_Phase_Validate_Package',
  rst_extract: 'Upd_Phase_Extract_Backup',
  maintenance_on: 'Upd_Phase_Maintenance_On', maintenance_off: 'Upd_Phase_Maintenance_Off',
  apply: 'Upd_Phase_Apply', migrate: 'Upd_Phase_Migrate', finalize: 'Finalizing',
  cache: 'Upd_Phase_Cache', rst_cache: 'Upd_Phase_Cache',
  verify: 'Upd_Phase_Verify', cleanup: 'Upd_Phase_Cleanup', rst_cleanup: 'Upd_Phase_Cleanup',
  rb_files: 'Upd_Phase_Restore_Files', rst_swap: 'Upd_Phase_Restore_Files',
  rb_db: 'Upd_Phase_Restore_Db', rst_db: 'Upd_Phase_Restore_Db', rb_finalize: 'Upd_Phase_Rollback_Finish',
};
const phaseLabel = p => (PHASE_KEYS[p] ? t(PHASE_KEYS[p]) : (p || '—'));

const updateSteps = computed(() => [
  { label: t('Creating_Backup'), phases: ['init', 'maintenance_on', 'backup_db', 'backup_files', 'backup_verify'] },
  { label: t('Preparing_Update'), phases: ['stage_extract', 'stage_validate'] },
  { label: t('Updating_Files'), phases: ['apply'] },
  { label: t('Updating_Database'), phases: ['migrate', 'finalize'] },
  { label: t('Clearing_Cache'), phases: ['cache'] },
  { label: t('Verifying_Installation'), phases: ['verify'] },
  { label: t('Done'), phases: ['maintenance_off', 'cleanup', 'completed'] },
]);
const currentStepIndex = computed(() => {
  if (!state.value) return 0;
  if (state.value.status === 'completed') return updateSteps.value.length - 1;
  const idx = updateSteps.value.findIndex(s => s.phases.includes(state.value.phase));
  return idx >= 0 ? idx : 0;
});

const historyColumns = computed(() => [
  { title: t('Date'), dataIndex: 'started_at', key: 'started_at', width: 150 },
  { title: t('Version'), key: 'versions' },
  { title: t('Updated_By'), key: 'user' },
  { title: t('Duration'), key: 'duration', width: 90 },
  { title: t('Status'), key: 'status', width: 110 },
]);
const backupColumns = computed(() => [
  { title: t('Backup'), key: 'backup' },
  { title: '', key: 'actions', width: 90 },
]);

// ----------------------------------------------------------------- status

function applyStatus(data) {
  if (!data) return;
  currentVersion.value = data.current_version || currentVersion.value;
  state.value = data.state || null;
  packageInfo.value = data.package || null;
  report.value = data.package && data.package.report ? data.package.report : null;
  history.value = data.history || [];
  backups.value = data.backups || [];
  if (typeof data.retention === 'number') retention.value = data.retention;
}

async function refreshStatus() {
  try {
    applyStatus(await http.get('system-update/status'));
  } catch (e) { /* keep the page usable */ }
}

// ----------------------------------------------------------------- upload

async function sha256OfFile(file) {
  if (!window.crypto || !window.crypto.subtle || file.size > 64 * 1024 * 1024) return null;
  try {
    const buf = await file.arrayBuffer();
    const digest = await window.crypto.subtle.digest('SHA-256', buf);
    return Array.from(new Uint8Array(digest)).map(b => b.toString(16).padStart(2, '0')).join('');
  } catch (e) {
    return null;
  }
}

async function onUploadRequest({ file }) {
  if (!/\.zip$/i.test(file.name)) {
    message.error(t('Only_Zip_Allowed'));
    return;
  }
  uploading.value = true;
  uploadPercent.value = 0;
  report.value = null;
  try {
    const total = Math.max(1, Math.ceil(file.size / CHUNK_SIZE));
    const uploadId = 'up-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
    const hash = await sha256OfFile(file);
    for (let index = 0; index < total; index++) {
      const blob = file.slice(index * CHUNK_SIZE, Math.min(file.size, (index + 1) * CHUNK_SIZE));
      const form = new FormData();
      form.append('chunk', blob, 'chunk.bin');
      form.append('index', String(index));
      form.append('total', String(total));
      form.append('upload_id', uploadId);
      form.append('filename', file.name);
      if (hash && index === total - 1) form.append('sha256', hash);
      await http.postForm('system-update/upload', form);
      uploadPercent.value = Math.round(((index + 1) / total) * 100);
    }
    message.success(t('Package_Uploaded'));
    await refreshStatus();
    await validatePackage();
  } catch (e) {
    message.error(e?.data?.message || t('Upload_Failed'));
  } finally {
    uploading.value = false;
  }
}

async function validatePackage() {
  validating.value = true;
  try {
    report.value = await http.post('system-update/validate');
    await refreshStatus();
    if (!report.value.ok) message.warning(t('Package_Cannot_Be_Installed'));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    validating.value = false;
  }
}

async function removePackage() {
  try {
    applyStatus(await http.delete('system-update/package'));
    report.value = null;
    message.success(t('Removed_successfully'));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  }
}

// -------------------------------------------------------------------- run

function confirmStart() {
  Modal.confirm({
    title: t('Are_you_sure'),
    content: t('Start_Update_Confirm_Text'),
    okText: t('Yes_update'),
    cancelText: t('Cancel'),
    okType: 'danger',
    onOk: startUpdate,
  });
}

async function startUpdate() {
  try {
    applyStatus(await http.post('system-update/start'));
    runStepLoop();
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  }
}

function runStepLoop() {
  if (stepping.value) return;
  stepping.value = true;
  stepErrors = 0;
  const next = async () => {
    try {
      applyStatus(await http.post('system-update/step'));
      stepErrors = 0;
    } catch (e) {
      // Transient failures are expected around the file swap (new code is
      // being loaded) — retry with backoff before giving up.
      stepErrors += 1;
      if (stepErrors > 10) {
        stepping.value = false;
        message.error(t('Update_Connection_Lost'));
        await refreshStatus();
        return;
      }
      setTimeout(next, 2500);
      return;
    }
    if (state.value && state.value.status === 'running') {
      setTimeout(next, 500);
    } else {
      stepping.value = false;
      if (state.value && state.value.status === 'completed') {
        message.success(state.value.type === 'restore' ? t('Backup_Restored_Successfully') : t('Update_Successful'));
      }
    }
  };
  next();
}

async function resumeRun() {
  try {
    applyStatus(await http.post('system-update/resume'));
    runStepLoop();
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  }
}

function confirmRollback() {
  Modal.confirm({
    title: t('Are_you_sure'),
    content: t('Rollback_Confirm_Text'),
    okText: t('Rollback_Now'),
    cancelText: t('Cancel'),
    okType: 'danger',
    onOk: async () => {
      try {
        applyStatus(await http.post('system-update/rollback'));
        runStepLoop();
      } catch (e) {
        message.error(e?.data?.message || t('InvalidData'));
      }
    },
  });
}

async function discardRun() {
  try {
    applyStatus(await http.post('system-update/discard'));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  }
}

// The finished run's state lives server-side, so a plain reload would show the
// "Update successful — reload" banner again. Acknowledge (clear) it first;
// history and backups are kept separately.
async function reloadApp() {
  try {
    await http.post('system-update/discard');
  } catch (e) { /* reload anyway */ }
  window.location.reload();
}

function openRecoveryConsole() {
  window.open(origin + '/system-update/recovery', '_blank');
}

// ---------------------------------------------------------------- backups

function confirmRestore(backup) {
  Modal.confirm({
    title: t('Are_you_sure'),
    content: t('Upd_Restore_Backup_Confirm', { version: backup.version || '?', date: backup.created_at }),
    okText: t('Restore'),
    cancelText: t('Cancel'),
    okType: 'danger',
    onOk: async () => {
      try {
        applyStatus(await http.post('system-update/restore-backup', { id: backup.id }));
        runStepLoop();
      } catch (e) {
        message.error(e?.data?.message || t('InvalidData'));
      }
    },
  });
}

function confirmDeleteBackup(backup) {
  Modal.confirm({
    title: t('Are_you_sure'),
    content: t('Delete_Backup_Confirm_Text'),
    okText: t('Del'),
    cancelText: t('Cancel'),
    okType: 'danger',
    onOk: async () => {
      try {
        applyStatus(await http.delete('system-update/backups/' + encodeURIComponent(backup.id)));
        message.success(t('Deleted_in_successfully'));
      } catch (e) {
        message.error(e?.data?.message || t('InvalidData'));
      }
    },
  });
}

async function saveRetention() {
  try {
    applyStatus(await http.post('system-update/retention', { retention: retention.value }));
    message.success(t('Successfully_Saved'));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  }
}

// ---------------------------------------------------------------- helpers

function formatBytes(bytes) {
  if (!bytes) return '0 B';
  const k = 1024; const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.min(sizes.length - 1, Math.floor(Math.log(bytes) / Math.log(k)));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}
function formatDuration(seconds) {
  if (seconds < 60) return seconds + 's';
  const m = Math.floor(seconds / 60); const s = seconds % 60;
  return m + 'm ' + s + 's';
}
function historyStatusColor(status) {
  return { completed: 'success', running: 'processing', failed: 'error', rolled_back: 'warning', recovery_required: 'error' }[status] || 'default';
}
function historyStatusLabel(status) {
  const map = {
    completed: t('Successful'), running: t('Running'), failed: t('Failed'),
    rolled_back: t('Rolled_Back'), recovery_required: t('Recovery_Required'),
  };
  return map[status] || status;
}

onMounted(async () => {
  await refreshStatus();
  // A run left "running" by a closed browser can simply be continued.
  if (state.value && state.value.status === 'running' && !state.value.stalled) {
    runStepLoop();
  }
  pollTimer = setInterval(() => {
    if (!stepping.value && !uploading.value) refreshStatus();
  }, 10000);
});
onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>

<style scoped>
.manual-steps li {
  margin-bottom: 10px;
}
.manual-steps ul {
  margin-top: 6px;
}
.guide {
  max-width: 900px;
}
.guide h3 {
  font-size: 16px;
  font-weight: 600;
  margin: 18px 0 10px;
}
.guide h3:first-child {
  margin-top: 4px;
}
.guide code {
  background: rgba(0, 0, 0, 0.06);
  border-radius: 4px;
  padding: 1px 5px;
  font-size: 12px;
}
.update-log {
  background: #101418;
  color: #c9d1d9;
  font-size: 12px;
  border-radius: 8px;
  padding: 12px;
  max-height: 260px;
  overflow: auto;
  white-space: pre-wrap;
  margin: 0;
}
</style>
