<template>
  <div class="page">
    <PageHeader title="WhatsApp Business" :breadcrumb="[$t('Integrations'), 'WhatsApp']">
      <template #actions>
        <a-tag v-if="!isLoading" :color="statusColor" style="font-size: 13px; padding: 4px 12px">
          {{ statusLabel }}
        </a-tag>
      </template>
    </PageHeader>

    <div v-if="isLoading" style="display: flex; justify-content: center; padding: 96px 0">
      <a-spin size="large" />
    </div>

    <template v-else>
      <a-card size="small" style="margin-bottom: 16px">
        <div class="hero">
          <a-avatar shape="square" :size="48" :style="{ background: 'rgba(37,211,102,0.16)', color: '#128c7e' }">
            <template #icon><WhatsAppOutlined /></template>
          </a-avatar>
          <div style="flex: 1">
            <div class="hero-title">WhatsApp Business (Meta Cloud API)</div>
            <div class="muted">{{ $t('WhatsApp_Hero_Desc') }}</div>
          </div>
          <a-space wrap>
            <a-button :loading="testing" :disabled="!state.configured" @click="testConnection">
              <template #icon><ApiOutlined /></template> {{ $t('Test_Connection') }}
            </a-button>
          </a-space>
        </div>

        <a-steps size="small" :current="setupStep" :items="setupItems" style="margin-top: 4px" />
      </a-card>

      <a-tabs v-model:activeKey="tab">
        <!-- ===================================================== CONNECTION -->
        <a-tab-pane key="connection" :tab="$t('Connection')">
          <a-row :gutter="16">
            <a-col :xs="24" :xl="13">
              <a-card size="small" :title="$t('WhatsApp_Card_Credentials')" style="margin-bottom: 16px">
                <a-form layout="vertical">
                  <a-row :gutter="12">
                    <a-col :xs="24" :md="12">
                      <a-form-item>
                        <template #label>
                          {{ $t('Phone_Number_ID') }} <span class="req">*</span>
                          <a-tooltip :title="$t('WhatsApp_Tip_Phone_Number_ID')">
                            <QuestionCircleOutlined class="help" />
                          </a-tooltip>
                        </template>
                        <a-input v-model:value="form.phone_number_id" :placeholder="$t('WhatsApp_Example', { value: '104857362919382' })" />
                      </a-form-item>
                    </a-col>
                    <a-col :xs="24" :md="12">
                      <a-form-item>
                        <template #label>
                          {{ $t('WhatsApp_Business_Account_ID') }}
                          <a-tooltip :title="$t('WhatsApp_Tip_Business_Account_ID')">
                            <QuestionCircleOutlined class="help" />
                          </a-tooltip>
                        </template>
                        <a-input v-model:value="form.business_account_id" :placeholder="$t('WhatsApp_Example', { value: '118273645512345' })" />
                      </a-form-item>
                    </a-col>
                  </a-row>

                  <a-form-item>
                    <template #label>
                      {{ $t('WhatsApp_Access_Token') }} <span class="req">*</span>
                      <a-tooltip :title="$t('WhatsApp_Tip_Access_Token', { perm1: 'whatsapp_business_messaging', perm2: 'whatsapp_business_management' })">
                        <QuestionCircleOutlined class="help" />
                      </a-tooltip>
                    </template>
                    <a-input-password
                      v-model:value="form.access_token"
                      autocomplete="off"
                      :placeholder="state.access_token_hint ? $t('Integ_Saved_Token_Hint', { hint: state.access_token_hint }) : 'EAAG…'"
                    />
                    <div class="muted" style="margin-top: 4px">
                      {{ $t('WhatsApp_Stored_Write_Only') }}
                      <a v-if="state.access_token_hint" @click="clearSecret('access_token')">{{ $t('WhatsApp_Remove_Saved_Token') }}</a>
                    </div>
                  </a-form-item>

                  <a-form-item>
                    <template #label>
                      {{ $t('WhatsApp_App_Secret') }}
                      <a-tooltip :title="$t('WhatsApp_Tip_App_Secret')">
                        <QuestionCircleOutlined class="help" />
                      </a-tooltip>
                    </template>
                    <a-input-password
                      v-model:value="form.app_secret"
                      autocomplete="off"
                      :placeholder="state.app_secret_hint ? $t('WhatsApp_Saved_Secret_Hint', { hint: state.app_secret_hint }) : $t('WhatsApp_Hex_String')"
                    />
                    <div class="muted" style="margin-top: 4px">
                      {{ $t('WhatsApp_App_Secret_Help') }}
                      <a v-if="state.app_secret_hint" @click="clearSecret('app_secret')">{{ $t('WhatsApp_Remove_Saved_Secret') }}</a>
                    </div>
                  </a-form-item>

                  <a-row :gutter="12">
                    <a-col :xs="24" :md="12">
                      <a-form-item>
                        <template #label>
                          {{ $t('WhatsApp_Default_Country_Code') }}
                          <a-tooltip :title="$t('WhatsApp_Tip_Country_Code')">
                            <QuestionCircleOutlined class="help" />
                          </a-tooltip>
                        </template>
                        <a-input v-model:value="form.default_country_code" :placeholder="$t('WhatsApp_Example', { value: '92' })" style="max-width: 160px" />
                      </a-form-item>
                    </a-col>
                    <a-col :xs="24" :md="12">
                      <a-form-item :label="$t('WhatsApp_Graph_API_Version')">
                        <a-input v-model:value="form.api_version" placeholder="v21.0" style="max-width: 160px" />
                      </a-form-item>
                    </a-col>
                  </a-row>

                  <a-form-item>
                    <a-space align="center">
                      <a-switch v-model:checked="form.enabled" :disabled="!state.configured && !(form.phone_number_id && form.access_token)" />
                      <span>{{ $t('WhatsApp_Enable_Sending') }}</span>
                    </a-space>
                    <div class="muted" style="margin-top: 4px">
                      {{ $t('WhatsApp_Enable_Help') }}
                    </div>
                  </a-form-item>

                  <a-space wrap>
                    <a-button type="primary" :loading="saving" @click="save()">{{ $t('Save') }}</a-button>
                    <a-button :loading="testing" :disabled="!state.configured && !(form.phone_number_id && form.access_token)" @click="testConnection">
                      {{ $t('Test_Connection') }}
                    </a-button>
                  </a-space>
                </a-form>

                <a-alert
                  v-if="testResult"
                  style="margin-top: 16px"
                  :type="testResult.ok ? 'success' : 'error'"
                  show-icon
                >
                  <template #message>
                    <span v-if="testResult.ok">{{ $t('WhatsApp_Connected_Success') }}</span>
                    <span v-else>{{ testResult.error }}</span>
                  </template>
                  <template #description>
                    <div v-if="testResult.ok">
                      <div><strong>{{ $t('Number') }}:</strong> {{ testResult.phone?.display_phone_number || '—' }}</div>
                      <div><strong>{{ $t('WhatsApp_Verified_Name') }}:</strong> {{ testResult.phone?.verified_name || '—' }}</div>
                      <div><strong>{{ $t('WhatsApp_Quality_Rating') }}:</strong> {{ testResult.phone?.quality_rating || '—' }}</div>
                      <div v-if="testResult.phone?.code_verification_status"><strong>{{ $t('WhatsApp_Number_Status') }}:</strong> {{ testResult.phone.code_verification_status }}</div>
                      <div v-if="testResult.business_account"><strong>{{ $t('WhatsApp_Business_Account') }}:</strong> {{ testResult.business_account.name }}</div>
                      <div v-if="testResult.business_account_error" style="color: #d48806">
                        <strong>{{ $t('WhatsApp_Business_Account_ID_Short') }}:</strong> {{ testResult.business_account_error }}
                      </div>
                      <div style="margin-top: 6px">
                        <strong>{{ $t('Webhook') }}:</strong>
                        <span v-if="testResult.webhook_seen">{{ $t('WhatsApp_Verified_By_Meta_On', { at: testResult.last_webhook_at }) }}</span>
                        <span v-else>{{ $t('WhatsApp_Not_Verified_Step2') }}</span>
                      </div>
                    </div>
                    <div v-else>
                      <div v-if="testResult.hint">{{ testResult.hint }}</div>
                      <div v-if="testResult.code" class="muted" style="margin-top: 4px">
                        {{ $t('WhatsApp_Meta_Error_Code', { code: testResult.code }) }}<span v-if="testResult.meta_message">: {{ testResult.meta_message }}</span>
                      </div>
                    </div>
                  </template>
                </a-alert>
              </a-card>

              <a-card size="small" :title="$t('WhatsApp_Card_Send_Test')">
                <a-form layout="vertical">
                  <a-row :gutter="12">
                    <a-col :xs="24" :md="10">
                      <a-form-item :label="$t('WhatsApp_Recipient_Number')">
                        <a-input v-model:value="testPhone" placeholder="+923001234567" />
                      </a-form-item>
                    </a-col>
                    <a-col :xs="24" :md="14">
                      <a-form-item :label="$t('WhatsApp_Message_Optional')">
                        <a-input v-model:value="testMessage" :placeholder="$t('WhatsApp_Message_Placeholder')" />
                      </a-form-item>
                    </a-col>
                  </a-row>
                  <a-space>
                    <a-button type="primary" :loading="sendingTest" :disabled="!state.configured || !testPhone" @click="sendTest">
                      <template #icon><SendOutlined /></template> {{ $t('Integ_Send_Test_Message') }}
                    </a-button>
                  </a-space>
                  <i18n-t keypath="WhatsApp_Dev_Mode_Note" tag="div" class="muted" style="margin-top: 8px">
                    <template #devMode><strong>{{ $t('WhatsApp_Development_Mode') }}</strong></template>
                  </i18n-t>
                </a-form>
                <a-alert
                  v-if="sendResult"
                  style="margin-top: 12px"
                  :type="sendResult.ok ? 'success' : 'error'"
                  show-icon
                  :message="sendResult.ok ? $t('WhatsApp_Message_Accepted') : sendResult.error"
                  :description="sendResult.ok ? $t('WhatsApp_Message_Id_Value', { id: sendResult.message_id }) : (sendResult.code ? $t('WhatsApp_Error_Code_Value', { code: sendResult.code }) : null)"
                />
              </a-card>
            </a-col>

            <a-col :xs="24" :xl="11">
              <a-card size="small" :title="$t('WhatsApp_Card_Webhook')" style="margin-bottom: 16px">
                <i18n-t keypath="WhatsApp_Webhook_Intro" tag="div" class="muted" style="margin-bottom: 12px">
                  <template #path><strong>WhatsApp → Configuration → Webhook → Edit</strong></template>
                  <template #verify><em>Verify and save</em></template>
                  <template #field><strong>messages</strong></template>
                </i18n-t>

                <a-form layout="vertical">
                  <a-form-item :label="$t('WhatsApp_Callback_URL')">
                    <a-input-group compact style="display: flex">
                      <a-input :value="state.webhook_url" readonly style="flex: 1" />
                      <a-button @click="copy(state.webhook_url)"><template #icon><CopyOutlined /></template></a-button>
                    </a-input-group>
                    <i18n-t v-if="!isHttps" keypath="WhatsApp_Https_Warning" tag="div" style="color: #d4380d; margin-top: 4px; font-size: 13px">
                      <template #https><strong>https://</strong></template>
                    </i18n-t>
                  </a-form-item>

                  <a-form-item :label="$t('WhatsApp_Verify_Token')">
                    <a-input-group compact style="display: flex">
                      <a-input :value="state.verify_token" readonly style="flex: 1; font-family: monospace" />
                      <a-button @click="copy(state.verify_token)"><template #icon><CopyOutlined /></template></a-button>
                      <a-popconfirm
                        :title="$t('WhatsApp_Regenerate_Confirm')"
                        :ok-text="$t('Yes')" :cancel-text="$t('No')" @confirm="regenerateToken"
                      >
                        <a-button><template #icon><ReloadOutlined /></template></a-button>
                      </a-popconfirm>
                    </a-input-group>
                  </a-form-item>

                  <a-form-item :label="$t('WhatsApp_Webhook_Fields')">
                    <a-tag color="green">messages</a-tag>
                    <span class="muted">{{ $t('WhatsApp_Messages_Field_Help') }}</span>
                  </a-form-item>
                </a-form>

                <a-descriptions size="small" bordered :column="1">
                  <a-descriptions-item :label="$t('WhatsApp_Last_Webhook_Call')">
                    <span v-if="state.last_webhook_at">{{ state.last_webhook_at }}</span>
                    <span v-else class="muted">{{ $t('WhatsApp_Never_Verified') }}</span>
                  </a-descriptions-item>
                  <a-descriptions-item :label="$t('WhatsApp_Signature_Check')">
                    <a-tag v-if="state.has_app_secret" color="success">{{ $t('WhatsApp_Signature_On') }}</a-tag>
                    <a-tag v-else color="warning">{{ $t('WhatsApp_Signature_Off') }}</a-tag>
                  </a-descriptions-item>
                </a-descriptions>
              </a-card>

              <a-card size="small" :title="$t('WhatsApp_Delivery_Summary')">
                <a-row :gutter="8">
                  <a-col :span="6" v-for="s in statTiles" :key="s.key">
                    <div class="stat">
                      <div class="stat-val" :style="{ color: s.color }">{{ state.stats?.[s.key] ?? 0 }}</div>
                      <div class="stat-lbl">{{ s.label }}</div>
                    </div>
                  </a-col>
                </a-row>
                <a-button type="link" style="padding: 0; margin-top: 8px" @click="tab = 'log'; loadLogs(1)">{{ $t('WhatsApp_Open_Delivery_Log') }}</a-button>
              </a-card>
            </a-col>
          </a-row>
        </a-tab-pane>

        <!-- ===================================================== AUTOMATIONS -->
        <a-tab-pane key="automations" :tab="$t('WhatsApp_Automations')">
          <a-alert
            v-if="!state.enabled"
            type="warning" show-icon style="margin-bottom: 16px"
            :message="$t('WhatsApp_Disabled_Alert')"
          />
          <a-row :gutter="16">
            <a-col :xs="24" :xl="12" v-for="def in state.automation_definitions" :key="def.key" style="margin-bottom: 16px">
              <a-card size="small">
                <template #title>
                  <a-space>
                    <a-switch v-model:checked="form.automations[def.key].enabled" size="small" />
                    <span>{{ def.label }}</span>
                  </a-space>
                </template>
                <template #extra>
                  <a @click="form.automations[def.key].template = def.default_template">{{ $t('Reset_to_Default') }}</a>
                </template>
                <div class="muted" style="margin-bottom: 8px">{{ def.hint }}</div>
                <a-textarea v-model:value="form.automations[def.key].template" :rows="6" />
                <div style="margin-top: 8px">
                  <a-tag
                    v-for="p in placeholdersFor(def.key)" :key="p"
                    style="cursor: pointer; margin-bottom: 4px"
                    @click="insertPlaceholder(def.key, p)"
                  >{{ p }}</a-tag>
                </div>
              </a-card>
            </a-col>
          </a-row>
          <a-button type="primary" :loading="saving" @click="save()">{{ $t('Save') }}</a-button>
          <div class="muted" style="margin-top: 10px">{{ $t('WhatsApp_Automations_Note', { code: '131047' }) }}</div>
        </a-tab-pane>

        <!-- ===================================================== DELIVERY LOG -->
        <a-tab-pane key="log" :tab="$t('WhatsApp_Delivery_Log')">
          <a-card size="small">
            <a-row :gutter="8" style="margin-bottom: 12px">
              <a-col :span="6" v-for="s in statTiles" :key="s.key">
                <div class="stat" :class="{ active: logFilter.status === s.status }" @click="s.status ? (logFilter.status = logFilter.status === s.status ? '' : s.status, loadLogs(1)) : null">
                  <div class="stat-val" :style="{ color: s.color }">{{ logs.stats?.[s.key] ?? state.stats?.[s.key] ?? 0 }}</div>
                  <div class="stat-lbl">{{ s.label }}</div>
                </div>
              </a-col>
            </a-row>

            <a-space wrap style="margin-bottom: 12px">
              <a-input-search v-model:value="logFilter.search" :placeholder="$t('WhatsApp_Log_Search_Placeholder')" allow-clear style="width: 260px" @search="loadLogs(1)" />
              <a-select v-model:value="logFilter.status" style="width: 140px" allow-clear :placeholder="$t('Status')" @change="loadLogs(1)"
                :options="statusOptions" />
              <a-select v-model:value="logFilter.direction" style="width: 130px" allow-clear :placeholder="$t('Direction')" @change="loadLogs(1)"
                :options="[{ value: 'out', label: $t('WhatsApp_Outgoing') }, { value: 'in', label: $t('WhatsApp_Incoming') }]" />
              <a-button :loading="logsLoading" @click="loadLogs(logs.page)"><template #icon><ReloadOutlined /></template></a-button>
              <a-popconfirm :title="$t('WhatsApp_Clear_Failed_Confirm')" :ok-text="$t('Yes')" :cancel-text="$t('No')" @confirm="clearLogs('failed')">
                <a-button size="small">{{ $t('WhatsApp_Clear_Failed') }}</a-button>
              </a-popconfirm>
              <a-popconfirm :title="$t('WhatsApp_Clear_All_Confirm')" :ok-text="$t('Yes')" :cancel-text="$t('No')" @confirm="clearLogs('all')">
                <a-button size="small" danger>{{ $t('Clear_all') }}</a-button>
              </a-popconfirm>
            </a-space>

            <a-table
              size="small" row-key="id" :loading="logsLoading"
              :data-source="logs.rows" :columns="logColumns"
              :pagination="{ current: logs.page, pageSize: logs.perPage, total: logs.total, showSizeChanger: false }"
              :scroll="{ x: 900 }"
              @change="p => loadLogs(p.current)"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'direction'">
                  <a-tag :color="record.direction === 'in' ? 'blue' : 'default'">{{ record.direction === 'in' ? $t('In') : $t('Out') }}</a-tag>
                </template>
                <template v-else-if="column.key === 'to'">
                  <div>{{ record.recipient_name || '—' }}</div>
                  <div class="muted" style="font-family: monospace">{{ record.phone }}</div>
                </template>
                <template v-else-if="column.key === 'status'">
                  <a-tag :color="statusTag(record.status)">{{ logStatusLabel(record.status) }}</a-tag>
                  <div v-if="record.error_message" class="err">{{ record.error_message }}</div>
                </template>
                <template v-else-if="column.key === 'body'">
                  <div class="clamp">{{ record.body }}</div>
                </template>
                <template v-else-if="column.key === 'actions'">
                  <a-space>
                    <a-button size="small" @click="viewLog(record)">{{ $t('View') }}</a-button>
                    <a-button v-if="record.direction === 'out' && ['failed', 'queued'].includes(record.status)" size="small" type="primary" :loading="retrying === record.id" @click="retry(record)">{{ $t('Zatca_Retry') }}</a-button>
                  </a-space>
                </template>
              </template>
            </a-table>
          </a-card>
        </a-tab-pane>

        <!-- ===================================================== SETUP GUIDE -->
        <a-tab-pane key="guide" :tab="$t('WhatsApp_Setup_Guide')">
          <a-card size="small">
            <ol class="guide">
              <i18n-t keypath="WhatsApp_G1" tag="li">
                <template #title><strong>{{ $t('WhatsApp_G1_Title') }}</strong></template>
                <template #link><a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com/apps</a></template>
                <template #business><em>Business</em></template>
                <template #product><strong>WhatsApp</strong></template>
              </i18n-t>
              <i18n-t keypath="WhatsApp_G2" tag="li">
                <template #title><strong>{{ $t('WhatsApp_G2_Title') }}</strong></template>
                <template #apiSetup><em>API Setup</em></template>
                <template #phoneId><strong>Phone number ID</strong></template>
                <template #wabaId><strong>WhatsApp Business Account ID</strong></template>
              </i18n-t>
              <i18n-t keypath="WhatsApp_G3" tag="li">
                <template #title><strong>{{ $t('WhatsApp_G3_Title') }}</strong></template>
                <template #link><a href="https://business.facebook.com/settings/system-users" target="_blank" rel="noopener">Business Settings → System users</a></template>
                <template #addAssets><em>Add assets</em></template>
                <template #generate><em>Generate new token</em></template>
                <template #perm1><code>whatsapp_business_messaging</code></template>
                <template #perm2><code>whatsapp_business_management</code></template>
                <template #field><strong>{{ $t('WhatsApp_Access_Token') }}</strong></template>
              </i18n-t>
              <i18n-t keypath="WhatsApp_G4" tag="li">
                <template #title><strong>{{ $t('WhatsApp_G4_Title') }}</strong></template>
                <template #appSecret><em>App secret</em></template>
                <template #field><strong>{{ $t('WhatsApp_App_Secret') }}</strong></template>
              </i18n-t>
              <i18n-t keypath="WhatsApp_G5" tag="li">
                <template #title><strong>{{ $t('WhatsApp_G5_Title') }}</strong></template>
              </i18n-t>
              <i18n-t keypath="WhatsApp_G6" tag="li">
                <template #title><strong>{{ $t('WhatsApp_G6_Title') }}</strong></template>
                <template #configuration><em>Configuration</em></template>
                <template #edit><em>Edit</em></template>
                <template #callback><strong>{{ $t('WhatsApp_Callback_URL') }}</strong></template>
                <template #verifyToken><strong>{{ $t('WhatsApp_Verify_Token') }}</strong></template>
                <template #verifySave><em>Verify and save</em></template>
                <template #manage><em>Manage</em></template>
                <template #messages><strong>messages</strong></template>
                <template #lastCall>{{ $t('WhatsApp_Last_Webhook_Call') }}</template>
              </i18n-t>
              <i18n-t keypath="WhatsApp_G7" tag="li">
                <template #title><strong>{{ $t('WhatsApp_G7_Title') }}</strong></template>
              </i18n-t>
              <i18n-t keypath="WhatsApp_G8" tag="li">
                <template #title><strong>{{ $t('WhatsApp_G8_Title') }}</strong></template>
                <template #enable><em>{{ $t('WhatsApp_Enable_Sending') }}</em></template>
              </i18n-t>
            </ol>
            <a-alert type="info" show-icon style="margin-top: 12px"
              :message="$t('WhatsApp_Queue_Worker')"
              :description="$t('WhatsApp_Queue_Worker_Desc', { queue: 'webhooks', cmd: 'php artisan queue:work --queue=webhooks,default' })" />
          </a-card>
        </a-tab-pane>
      </a-tabs>
    </template>

    <a-modal v-model:open="logModal.open" :title="$t('WhatsApp_Message_No', { id: logModal.row?.id || '' })" :footer="null" width="720px">
      <template v-if="logModal.row">
        <a-descriptions size="small" bordered :column="1">
          <a-descriptions-item :label="$t('Direction')">{{ logModal.row.direction === 'in' ? $t('WhatsApp_Incoming') : $t('WhatsApp_Outgoing') }}</a-descriptions-item>
          <a-descriptions-item :label="$t('Phone')">{{ logModal.row.phone }} <span v-if="logModal.row.recipient_name">({{ logModal.row.recipient_name }})</span></a-descriptions-item>
          <a-descriptions-item :label="$t('Event')">{{ logModal.row.event }}<span v-if="logModal.row.reference_type"> · {{ logModal.row.reference_type }} #{{ logModal.row.reference_id }}</span></a-descriptions-item>
          <a-descriptions-item :label="$t('Status')"><a-tag :color="statusTag(logModal.row.status)">{{ logStatusLabel(logModal.row.status) }}</a-tag></a-descriptions-item>
          <a-descriptions-item v-if="logModal.row.error_message" :label="$t('WhatsApp_Error')">
            <div class="err">{{ logModal.row.error_message }}</div>
            <div class="muted">{{ $t('WhatsApp_Code_Value', { code: logModal.row.error_code }) }}</div>
          </a-descriptions-item>
          <a-descriptions-item :label="$t('WhatsApp_Timeline')">
            <div>{{ $t('WhatsApp_Created_At', { at: logModal.row.created_at }) }}</div>
            <div v-if="logModal.row.sent_at">{{ $t('WhatsApp_Sent_At', { at: logModal.row.sent_at }) }}</div>
            <div v-if="logModal.row.delivered_at">{{ $t('WhatsApp_Delivered_At', { at: logModal.row.delivered_at }) }}</div>
            <div v-if="logModal.row.read_at">{{ $t('WhatsApp_Read_At', { at: logModal.row.read_at }) }}</div>
          </a-descriptions-item>
          <a-descriptions-item :label="$t('WhatsApp_Meta_Message_Id')"><code>{{ logModal.row.wa_message_id || '—' }}</code></a-descriptions-item>
          <a-descriptions-item :label="$t('Text')"><pre class="pre">{{ logModal.row.body }}</pre></a-descriptions-item>
          <a-descriptions-item v-if="logModal.row.response" :label="$t('WhatsApp_Raw_Response')"><pre class="pre">{{ JSON.stringify(logModal.row.response, null, 2) }}</pre></a-descriptions-item>
        </a-descriptions>
      </template>
    </a-modal>
  </div>
</template>

<script setup>
/**
 * Integrations → WhatsApp: one dedicated page for the Meta Cloud API.
 *
 *  - Connection: credentials (write-only secrets), Test Connection, the
 *    copyable webhook Callback URL + Verify Token, and a send-test box.
 *  - Automations: per-event toggle + editable template with placeholders.
 *  - Delivery log: every outbound/inbound message with status + error text,
 *    retry for failures.
 *  - Setup guide: the click-path inside Meta.
 *
 * API: GET/PUT whatsapp/settings, POST whatsapp/settings/{test-connection,
 * send-test, regenerate-verify-token, clear-secret}, GET whatsapp/messages,
 * POST whatsapp/messages/{id}/retry, DELETE whatsapp/messages.
 */
import { ref, computed, onMounted } from 'vue';
import { message } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import {
  WhatsAppOutlined, ApiOutlined, CopyOutlined, ReloadOutlined, SendOutlined, QuestionCircleOutlined,
} from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import http from '../../lib/http';

const { t } = useI18n();

const isLoading = ref(true);
const saving = ref(false);
const testing = ref(false);
const sendingTest = ref(false);
const tab = ref('connection');

const state = ref({
  enabled: false, configured: false, automations: {}, automation_definitions: [], stats: {}, placeholders: { sale: [], order: [] },
});
const form = ref({
  enabled: false, phone_number_id: '', business_account_id: '', access_token: '', app_secret: '',
  api_version: 'v21.0', default_country_code: '', automations: {},
});
const testResult = ref(null);
const sendResult = ref(null);
const testPhone = ref('');
const testMessage = ref('');

const statusColor = computed(() => (state.value.enabled && state.value.configured ? 'success' : state.value.configured ? 'default' : 'warning'));
const statusLabel = computed(() => (state.value.enabled && state.value.configured ? t('Enabled') : state.value.configured ? t('Disabled') : t('Integ_Not_Configured')));
const isHttps = computed(() => String(state.value.webhook_url || '').startsWith('https://'));
const setupItems = computed(() => [
  { title: t('WhatsApp_Credentials'), description: state.value.configured ? t('Saved') : t('WhatsApp_Step_Id_Token') },
  { title: t('Test_Connection'), description: state.value.last_tested_at ? t('WhatsApp_Step_Tested_At', { at: state.value.last_tested_at }) : t('WhatsApp_Not_Tested_Yet') },
  { title: t('Webhook'), description: state.value.last_webhook_at ? t('WhatsApp_Step_Verified_At', { at: state.value.last_webhook_at }) : t('WhatsApp_Step_Paste_In_Meta') },
  { title: t('Enabled'), description: state.value.enabled ? t('WhatsApp_Sending_On') : t('WhatsApp_Turn_On_When_Ready') },
]);
const setupStep = computed(() => {
  if (!state.value.configured) return 0;
  if (!state.value.last_tested_at) return 1;
  if (!state.value.last_webhook_at) return 2;
  return state.value.enabled ? 4 : 3;
});

const statTiles = computed(() => [
  { key: 'sent', label: t('Sent'), color: '#1677ff', status: 'sent' },
  { key: 'delivered', label: t('Delivered'), color: '#52c41a', status: 'delivered' },
  { key: 'failed', label: t('Failed'), color: '#ff4d4f', status: 'failed' },
  { key: 'received', label: t('Received'), color: '#722ed1', status: 'received' },
]);

// Log status values are API enums; only their label is translated.
const LOG_STATUS_KEYS = {
  queued: 'Crm_Msg_Queued', sent: 'Sent', delivered: 'Delivered', read: 'Read', failed: 'Failed', received: 'Received',
};
const statusOptions = computed(() => Object.keys(LOG_STATUS_KEYS).map(v => ({ value: v, label: t(LOG_STATUS_KEYS[v]) })));
function logStatusLabel(s) {
  return LOG_STATUS_KEYS[s] ? t(LOG_STATUS_KEYS[s]) : s;
}

function hydrate(data) {
  state.value = data;
  form.value.enabled = !!data.enabled;
  form.value.phone_number_id = data.phone_number_id || '';
  form.value.business_account_id = data.business_account_id || '';
  form.value.api_version = data.api_version || 'v21.0';
  form.value.default_country_code = data.default_country_code || '';
  form.value.access_token = '';
  form.value.app_secret = '';
  const auto = {};
  (data.automation_definitions || []).forEach((d) => {
    const row = (data.automations || {})[d.key] || {};
    auto[d.key] = { enabled: !!row.enabled, template: row.template || d.default_template };
  });
  form.value.automations = auto;
}

async function load() {
  isLoading.value = true;
  try {
    hydrate(await http.get('whatsapp/settings'));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    isLoading.value = false;
  }
}

async function save(silent = false) {
  saving.value = true;
  try {
    const payload = {
      enabled: !!form.value.enabled,
      phone_number_id: (form.value.phone_number_id || '').trim(),
      business_account_id: (form.value.business_account_id || '').trim(),
      api_version: (form.value.api_version || '').trim() || 'v21.0',
      default_country_code: (form.value.default_country_code || '').trim(),
      automations: form.value.automations,
    };
    // Secrets are write-only: send them only when the admin pasted a value.
    if (form.value.access_token) payload.access_token = form.value.access_token.trim();
    if (form.value.app_secret) payload.app_secret = form.value.app_secret.trim();
    const data = await http.put('whatsapp/settings', payload);
    hydrate(data);
    if (!silent) message.success(t('Successfully_Updated'));
    return true;
  } catch (e) {
    message.error(e?.data?.message || firstValidationError(e) || t('InvalidData'));
    return false;
  } finally {
    saving.value = false;
  }
}

function firstValidationError(e) {
  const errors = e?.data?.errors;
  if (!errors) return null;
  const k = Object.keys(errors)[0];
  return k ? errors[k][0] : null;
}

async function clearSecret(field) {
  try {
    hydrate(await http.post('whatsapp/settings/clear-secret', { field }));
    message.success(t('Successfully_Updated'));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  }
}

async function testConnection() {
  testing.value = true;
  testResult.value = null;
  try {
    // Persist anything typed so the test uses the latest credentials.
    if (form.value.access_token || form.value.app_secret || form.value.phone_number_id !== (state.value.phone_number_id || '') || form.value.business_account_id !== (state.value.business_account_id || '')) {
      if (!(await save(true))) return;
    }
    testResult.value = await http.post('whatsapp/settings/test-connection');
    state.value.last_tested_at = new Date().toISOString().slice(0, 19).replace('T', ' ');
    message.success(t('WhatsApp_Connected_To_Meta'));
  } catch (e) {
    testResult.value = { ok: false, error: e?.data?.error || e?.data?.message || t('Integ_Test_Failed'), hint: e?.data?.hint, code: e?.data?.code, meta_message: e?.data?.meta_message };
  } finally {
    testing.value = false;
  }
}

async function sendTest() {
  sendingTest.value = true;
  sendResult.value = null;
  try {
    sendResult.value = await http.post('whatsapp/settings/send-test', { phone: testPhone.value, message: testMessage.value || null });
    message.success(t('WhatsApp_Test_Message_Sent'));
    state.value.stats = { ...(state.value.stats || {}), sent: (state.value.stats?.sent || 0) + 1 };
  } catch (e) {
    sendResult.value = { ok: false, error: e?.data?.error || firstValidationError(e) || e?.data?.message || t('WhatsApp_Send_Failed'), code: e?.data?.code };
  } finally {
    sendingTest.value = false;
  }
}

async function regenerateToken() {
  try {
    const r = await http.post('whatsapp/settings/regenerate-verify-token');
    state.value.verify_token = r.verify_token;
    state.value.last_webhook_at = null;
    message.success(t('WhatsApp_New_Verify_Token'));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  }
}

async function copy(text) {
  try {
    await navigator.clipboard.writeText(text || '');
    message.success(t('Copied'));
  } catch (e) {
    message.error(t('InvalidData'));
  }
}

// ---------------- automations ----------------

function placeholdersFor(key) {
  const p = state.value.placeholders || {};
  return key.startsWith('order_') ? (p.order || []) : (p.sale || []);
}

function insertPlaceholder(key, p) {
  const row = form.value.automations[key];
  row.template = (row.template || '') + (row.template && !row.template.endsWith(' ') && !row.template.endsWith('\n') ? ' ' : '') + p;
}

// ---------------- delivery log ----------------

const logs = ref({ rows: [], page: 1, perPage: 25, total: 0, stats: null });
const logsLoading = ref(false);
const logFilter = ref({ search: '', status: '', direction: '' });
const retrying = ref(null);
const logModal = ref({ open: false, row: null });

const logColumns = computed(() => [
  { title: t('Date'), dataIndex: 'created_at', key: 'created_at', width: 160 },
  { title: '', key: 'direction', width: 60 },
  { title: t('WhatsApp_To_From'), key: 'to', width: 180 },
  { title: t('Event'), dataIndex: 'event', key: 'event', width: 130 },
  { title: t('Message'), key: 'body' },
  { title: t('Status'), key: 'status', width: 260 },
  { title: '', key: 'actions', width: 130, fixed: 'right' },
]);

async function loadLogs(page = 1) {
  logsLoading.value = true;
  try {
    const params = new URLSearchParams({ page, limit: logs.value.perPage });
    if (logFilter.value.search) params.set('search', logFilter.value.search);
    if (logFilter.value.status) params.set('status', logFilter.value.status);
    if (logFilter.value.direction) params.set('direction', logFilter.value.direction);
    const d = await http.get('whatsapp/messages?' + params.toString());
    logs.value.rows = d.messages || [];
    logs.value.total = d.totalRows || 0;
    logs.value.page = page;
    logs.value.stats = d.stats || null;
    if (d.stats) state.value.stats = d.stats;
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    logsLoading.value = false;
  }
}

async function retry(row) {
  retrying.value = row.id;
  try {
    await http.post(`whatsapp/messages/${row.id}/retry`);
    message.success(t('Crm_Message_Sent'));
  } catch (e) {
    message.error(e?.data?.error || e?.data?.message || t('Zatca_Retry_Failed'));
  } finally {
    retrying.value = null;
    loadLogs(logs.value.page);
  }
}

async function clearLogs(only) {
  try {
    await http.delete('whatsapp/messages' + (only === 'failed' ? '?only=failed' : ''));
    loadLogs(1);
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  }
}

function viewLog(row) {
  logModal.value = { open: true, row };
}

function statusTag(s) {
  return { queued: 'default', sent: 'blue', delivered: 'green', read: 'cyan', failed: 'red', received: 'purple' }[s] || 'default';
}

onMounted(async () => {
  await load();
  loadLogs(1);
});
</script>

<style scoped>
.hero {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid rgba(128, 128, 128, 0.15);
}
.hero-title { font-weight: 600; font-size: 16px; }
.muted { opacity: 0.65; font-size: 13px; }
.req { color: #ff4d4f; }
.help { margin-left: 4px; opacity: 0.5; cursor: help; }
.err { color: #cf1322; font-size: 12px; margin-top: 4px; white-space: normal; }
.clamp {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  white-space: pre-line;
  font-size: 12px;
}
.pre { white-space: pre-wrap; word-break: break-word; margin: 0; font-size: 12px; max-height: 260px; overflow: auto; }
.stat {
  text-align: center;
  padding: 10px 4px;
  border: 1px solid rgba(128, 128, 128, 0.15);
  border-radius: 8px;
  cursor: pointer;
}
.stat.active { border-color: #1677ff; }
.stat-val { font-size: 20px; font-weight: 600; line-height: 1.1; }
.stat-lbl { font-size: 12px; opacity: 0.65; }
.guide li { margin-bottom: 10px; line-height: 1.55; }
</style>
