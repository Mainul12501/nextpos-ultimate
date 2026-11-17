<template>
  <a-modal
    :open="open"
    :title="isEdit ? $t('Shopify_Edit_Name', { name: store.name }) : $t('Shopify_Connect_Shopify_Store')"
    :width="720"
    :confirm-loading="saving"
    :ok-text="isEdit ? $t('submit') : $t('Connect')"
    :cancel-text="$t('Cancel')"
    @ok="submit"
    @cancel="close"
  >
    <a-form ref="formRef" :model="form" :rules="rules" layout="vertical">
      <a-tabs v-model:activeKey="tab">
        <!-- ------------------------------------------------ connection -->
        <a-tab-pane key="connection" :tab="$t('Connection')">
          <a-row :gutter="16">
            <a-col :xs="24" :md="12">
              <a-form-item :label="$t('Name') + ' *'" name="name" :extra="$t('Shopify_Name_Hint')">
                <a-input v-model:value="form.name" :placeholder="$t('Shopify_Name_Placeholder')" allow-clear />
              </a-form-item>
            </a-col>
            <a-col :xs="24" :md="12">
              <a-form-item :label="$t('Shopify_Shop_Domain') + ' *'" name="shop_domain" :extra="$t('Shopify_Shop_Domain_Hint', { example: 'acme.myshopify.com' })">
                <a-input
                  v-model:value="form.shop_domain" placeholder="acme.myshopify.com"
                  :disabled="isEdit && !allowRebind" allow-clear
                />
              </a-form-item>
            </a-col>
            <a-col :span="24">
              <a-form-item :label="$t('Shopify_App_Credentials')" name="auth_mode" style="margin-bottom: 8px">
                <a-radio-group v-model:value="form.auth_mode" @change="onAuthModeChange">
                  <a-radio value="client_credentials">{{ $t('Shopify_Auth_Client') }}</a-radio>
                  <a-radio value="token">{{ $t('Shopify_Auth_Token') }}</a-radio>
                </a-radio-group>
              </a-form-item>
            </a-col>

            <template v-if="form.auth_mode === 'client_credentials'">
              <a-col :span="24">
                <a-alert type="info" show-icon style="margin-bottom: 12px">
                  <template #message>
                    <i18n-t keypath="Shopify_Client_Info" tag="span">
                      <template #dashboard><strong>Dev Dashboard</strong></template>
                      <template #settings><em>{{ $t('Settings') }}</em></template>
                    </i18n-t>
                  </template>
                </a-alert>
              </a-col>
              <a-col :xs="24" :md="12">
                <a-form-item
                  :label="isEdit && clientPairStored ? $t('Client_ID') : $t('Client_ID') + ' *'" name="client_id"
                  :extra="isEdit && clientPairStored ? $t('Shopify_Leave_Blank_Keep') : ''"
                >
                  <a-input
                    v-model:value="form.client_id"
                    :placeholder="isEdit && store?.client_id ? store.client_id : $t('Shopify_From_App_Settings')"
                    autocomplete="off" allow-clear
                  />
                </a-form-item>
              </a-col>
              <a-col :xs="24" :md="12">
                <a-form-item
                  :label="isEdit && clientPairStored ? $t('Shopify_Client_Secret') : $t('Shopify_Client_Secret') + ' *'" name="client_secret"
                  :extra="isEdit && clientPairStored ? $t('Shopify_Leave_Blank_Keep') : $t('Shopify_Client_Secret_Hint')"
                >
                  <a-input-password
                    v-model:value="form.client_secret"
                    :placeholder="isEdit && store?.has_client_secret ? $t('Shopify_Masked_Unchanged') : 'shpss_…'"
                    autocomplete="new-password"
                  />
                </a-form-item>
              </a-col>
            </template>

            <a-col v-else :span="24">
              <a-form-item
                :label="isEdit && store?.auth_mode === 'token' ? $t('Shopify_Admin_Token') : $t('Shopify_Admin_Token') + ' *'"
                name="access_token"
                :extra="isEdit && store?.auth_mode === 'token'
                  ? $t('Shopify_Token_Keep')
                  : $t('Shopify_Token_Hint')"
              >
                <a-input-password
                  v-model:value="form.access_token"
                  :placeholder="isEdit && store?.auth_mode === 'token' && store.has_token ? $t('Shopify_Masked_Unchanged') : 'shpat_…'"
                  autocomplete="new-password"
                />
              </a-form-item>
            </a-col>
            <a-col :xs="24" :md="12">
              <a-form-item :label="$t('Shopify_API_Version')" name="api_version">
                <a-input v-model:value="form.api_version" placeholder="2024-10" allow-clear />
              </a-form-item>
            </a-col>
            <a-col :xs="24" :md="12">
              <a-form-item
                :label="$t('Shopify_Webhook_Secret')" name="webhook_secret"
                :extra="form.auth_mode === 'client_credentials'
                  ? $t('Shopify_Webhook_Secret_Optional')
                  : $t('Shopify_Webhook_Secret_Required')"
              >
                <a-input-password
                  v-model:value="form.webhook_secret"
                  :placeholder="form.clear_webhook_secret ? $t('Shopify_Cleared_On_Save') : (store?.has_explicit_webhook_secret ? $t('Shopify_Masked_Unchanged') : $t('Optional'))"
                  :disabled="form.clear_webhook_secret"
                  autocomplete="new-password"
                />
                <template v-if="isEdit && store?.has_explicit_webhook_secret">
                  <a-button
                    v-if="!form.clear_webhook_secret" type="link" size="small" danger
                    style="padding: 0; margin-top: 4px" @click="form.clear_webhook_secret = true"
                  >
                    {{ $t('Shopify_Clear_Stored_Secret') }}
                  </a-button>
                  <a-button
                    v-else type="link" size="small" style="padding: 0; margin-top: 4px"
                    @click="form.clear_webhook_secret = false"
                  >
                    {{ $t('Shopify_Keep_After_All') }}
                  </a-button>
                </template>
              </a-form-item>
            </a-col>

            <a-col v-if="isEdit && !allowRebind" :span="24">
              <a-alert type="info" show-icon banner style="margin-bottom: 12px">
                <template #message>
                  {{ $t('Shopify_Domain_Locked') }}
                  <a class="link" @click="allowRebind = true">{{ $t('Shopify_Change_Anyway') }}</a>
                </template>
              </a-alert>
            </a-col>
            <a-col v-else-if="isEdit && allowRebind" :span="24">
              <a-alert
                type="warning" show-icon banner style="margin-bottom: 12px"
                :message="$t('Shopify_Domain_Change_Warning')"
              />
            </a-col>
          </a-row>
        </a-tab-pane>

        <!-- ------------------------------------------------ targets -->
        <a-tab-pane key="targets" :tab="$t('Shopify_Tab_Where_Data')">
          <a-row :gutter="16">
            <a-col :xs="24" :md="12">
              <a-form-item
                :label="$t('Shopify_ERP_Warehouse')" name="warehouse_id"
                :extra="$t('Shopify_ERP_Warehouse_Hint')"
              >
                <a-select
                  v-model:value="form.warehouse_id" show-search option-filter-prop="label"
                  :options="warehouseOptions" allow-clear :placeholder="$t('Mfg_Select_A_Warehouse')"
                />
              </a-form-item>
            </a-col>
            <a-col :xs="24" :md="12">
              <a-form-item :label="$t('Shopify_Location')" name="location_id">
                <a-select
                  v-model:value="form.location_id" :options="locationOptions" allow-clear
                  :loading="loadingLocations"
                  :placeholder="isEdit ? $t('Shopify_Load_Locations_Ph') : $t('Shopify_Available_After_Connect')"
                  :disabled="!isEdit"
                />
                <a-button
                  v-if="isEdit" type="link" size="small" style="padding: 0; margin-top: 4px"
                  :loading="loadingLocations" @click="loadLocations"
                >
                  {{ $t('Shopify_Fetch_Locations') }}
                </a-button>
              </a-form-item>
            </a-col>
            <a-col :xs="24" :md="12">
              <a-form-item :label="$t('Shopify_Price_Sent')" name="price_field">
                <a-select v-model:value="form.price_field" :options="priceFields" />
              </a-form-item>
            </a-col>
            <a-col :span="24">
              <a-form-item style="margin-bottom: 8px">
                <a-checkbox v-model:checked="form.create_missing_products">
                  {{ $t('Shopify_Create_Missing_Products') }}
                </a-checkbox>
              </a-form-item>
              <a-form-item style="margin-bottom: 0">
                <a-checkbox v-model:checked="form.create_missing_customers">
                  {{ $t('Shopify_Create_Missing_Customers') }}
                </a-checkbox>
                <div class="hint">{{ $t('Shopify_Unknown_Customer_Hint') }}</div>
              </a-form-item>
            </a-col>
          </a-row>
        </a-tab-pane>

        <!-- ------------------------------------------------ entities -->
        <a-tab-pane key="entities" :tab="$t('Shopify_Tab_What_To_Sync')">
          <p class="lead">
            {{ $t('Shopify_Entities_Lead') }}
          </p>
          <div class="ent-grid">
            <label v-for="entity in entities" :key="entity.value" class="ent-card">
              <a-checkbox v-model:checked="form[`sync_${entity.value}`]" />
              <span class="ent-body">
                <span class="ent-title">
                  <component :is="entity.icon" :size="15" />
                  {{ entity.label }}
                </span>
                <span class="ent-hint">{{ entity.hint }}</span>
                <span class="ent-dirs">
                  <a-tag v-for="d in entity.directions" :key="d" :color="optionOf(DIRECTIONS, d).color" class="dir-tag">
                    {{ labelOf(DIRECTIONS, d) }}
                  </a-tag>
                </span>
              </span>
            </label>
          </div>
        </a-tab-pane>

        <!-- ------------------------------------------------ automatic -->
        <a-tab-pane key="auto" :tab="$t('Shopify_Tab_Automatic_Sync')">
          <a-form-item style="margin-bottom: 10px">
            <a-checkbox v-model:checked="form.auto_sync">
              {{ $t('Shopify_Auto_Schedule') }}
            </a-checkbox>
            <i18n-t keypath="Shopify_Auto_Schedule_Hint" tag="div" class="hint">
              <template #cmd><code>php artisan shopify:sync</code></template>
            </i18n-t>
          </a-form-item>

          <a-form-item style="margin-bottom: 10px">
            <a-checkbox v-model:checked="form.instant_stock_push" :disabled="!form.sync_inventory">
              {{ $t('Shopify_Push_Stock_On_Change') }}
            </a-checkbox>
            <div class="hint">
              {{ $t('Shopify_Push_Stock_Hint') }}
              <i18n-t v-if="!form.sync_inventory" keypath="Shopify_Switch_Inventory_First" tag="span">
                <template #tab><em>{{ $t('Shopify_Tab_What_To_Sync') }}</em></template>
              </i18n-t>
            </div>
            <!-- Spelled out, because the consequence of leaving this off is
                 the one people meet later as a mystery: a product sold at the
                 till that the shop keeps selling. -->
            <a-alert
              v-if="form.sync_inventory && !form.instant_stock_push"
              type="info" show-icon style="margin-top: 8px"
              :message="$t('Shopify_Leave_Off_Lag', { n: inventoryEvery })"
            />
          </a-form-item>

          <a-form-item :label="$t('Shopify_Default_Interval')" name="sync_interval_minutes" style="max-width: 260px">
            <a-input-number
              v-model:value="form.sync_interval_minutes" :min="5" :max="1440"
              style="width: 100%" :disabled="!form.auto_sync"
            />
            <div class="hint">{{ $t('Shopify_Default_Interval_Hint') }}</div>
          </a-form-item>

          <a-divider style="margin: 12px 0" />

          <div class="plan-head">
            <div>
              <strong>{{ $t('Shopify_What_Runs_Auto') }}</strong>
              <i18n-t keypath="Shopify_Step_Hint" tag="div" class="hint">
                <template #from><em>{{ $t('Shopify_From') }}</em></template>
              </i18n-t>
            </div>
            <a-button size="small" :disabled="!form.auto_sync || !remainingChoices.length" @click="addStep">
              <template #icon><PlusOutlined /></template>
              {{ $t('Shopify_Add_Step') }}
            </a-button>
          </div>

          <a-alert
            v-if="!form.auto_sync_plan.length"
            type="info" show-icon style="margin-top: 10px"
            :message="$t('Shopify_Using_Default_Plan')"
          >
            <template #description>
              {{ $t('Shopify_Default_Plan_Desc', { n: form.sync_interval_minutes || 60 }) }}
            </template>
          </a-alert>

          <div v-else class="plan-list">
            <div v-for="(step, i) in form.auto_sync_plan" :key="i" class="plan-row">
              <a-select
                v-model:value="step.entity" :options="entityChoices(step)" style="flex: 1 1 150px"
                :disabled="!form.auto_sync" @change="onEntityChange(step)"
              />
              <a-select
                v-model:value="step.direction" :options="directionChoices(step)" style="flex: 1 1 150px"
                :disabled="!form.auto_sync"
              />
              <a-input-number
                v-model:value="step.every" :min="5" :max="10080" style="flex: 0 0 130px"
                :placeholder="$t('Hosp_N_Min', { n: form.sync_interval_minutes || 60 })" :disabled="!form.auto_sync"
              />
              <a-button type="text" danger :disabled="!form.auto_sync" @click="form.auto_sync_plan.splice(i, 1)">
                <template #icon><DeleteOutlined /></template>
              </a-button>
              <div v-if="!form[`sync_${step.entity}`]" class="plan-warn">
                {{ $t('Shopify_Step_Entity_Off', { entity: entityOf(step.entity).label }) }}
              </div>
            </div>
          </div>
        </a-tab-pane>
      </a-tabs>
    </a-form>
  </a-modal>
</template>

<script setup>
/**
 * Connect or edit one shop.
 *
 * The token field is write-only: an existing store shows a masked placeholder
 * and an empty submit keeps whatever is stored. The API never returns the token,
 * so there is nothing here that could leak it back out.
 */
import { ref, reactive, computed, watch } from 'vue';
import { message } from 'ant-design-vue';
import { PlusOutlined, DeleteOutlined } from '@ant-design/icons-vue';
import {
  ENTITIES, PRICE_FIELDS, DIRECTIONS, autoStepChoices, entityOf, stepKey, labelOf, optionOf, tr, tp,
} from './shopifyOptions';
import http from '../../lib/http';
import { t } from '../../i18n';

const props = defineProps({
  open: { type: Boolean, default: false },
  store: { type: Object, default: null },
  warehouses: { type: Array, default: () => [] },
  /** Which tab to open on — lets a page link straight to one setting. */
  initialTab: { type: String, default: 'connection' },
});
const emit = defineEmits(['update:open', 'saved']);

const formRef = ref();
const tab = ref('connection');
const saving = ref(false);
const allowRebind = ref(false);

const isEdit = computed(() => !!props.store?.id);
const warehouseOptions = computed(() => props.warehouses.map(w => ({ value: w.id, label: w.name })));
const entities = computed(() => tr(ENTITIES));
const priceFields = computed(() => tr(PRICE_FIELDS));

const locations = ref([]);
const loadingLocations = ref(false);
const locationOptions = computed(() => locations.value.map((l) => {
  const name = `${l.name}${l.city ? ' — ' + l.city : ''}`;

  return {
    value: l.id,
    label: l.active ? name : tp('Shopify_Location_Inactive', '{name} (inactive)', { name }),
  };
}));

const form = reactive(emptyForm());

function emptyForm() {
  return {
    name: '',
    shop_domain: '',
    // Dev Dashboard apps are the only kind Shopify still creates, so they are
    // the default; the token option stays for shops connected before 2026.
    auth_mode: 'client_credentials',
    access_token: '',
    client_id: '',
    client_secret: '',
    webhook_secret: '',
    clear_webhook_secret: false,
    api_version: '2024-10',
    warehouse_id: undefined,
    location_id: undefined,
    price_field: 'price',
    auto_sync: false,
    instant_stock_push: false,
    sync_interval_minutes: 60,
    // Empty means "the default plan" — the server reads it the same way.
    auto_sync_plan: [],
    create_missing_products: true,
    create_missing_customers: true,
    sync_products: true,
    sync_inventory: true,
    sync_customers: true,
    sync_orders: true,
    sync_collections: true,
    sync_fulfillments: true,
  };
}

const required = () => [{ required: true, message: t('Field_is_required', 'This field is required') }];

/** Whether the store already holds a client id + secret the edit may keep. */
const clientPairStored = computed(() => isEdit.value && props.store?.auth_mode === 'client_credentials');

const rules = computed(() => {
  const usingPair = form.auth_mode === 'client_credentials';
  // Credentials are only mandatory when they are new: on edit, blank means
  // "keep the stored one" — unless the kind is being switched, in which case
  // the other kind's credentials must actually be supplied.
  const tokenStored = isEdit.value && props.store?.auth_mode === 'token';

  return {
    name: required(),
    shop_domain: required(),
    access_token: !usingPair && !tokenStored ? required() : [],
    client_id: usingPair && !clientPairStored.value ? required() : [],
    client_secret: usingPair && !clientPairStored.value ? required() : [],
  };
});

function onAuthModeChange() {
  formRef.value?.clearValidate?.(['access_token', 'client_id', 'client_secret']);
}

watch(() => props.open, (isOpen) => {
  if (!isOpen) return;

  tab.value = props.initialTab || 'connection';
  allowRebind.value = false;
  locations.value = [];
  Object.assign(form, emptyForm());

  if (props.store) {
    Object.keys(form).forEach((key) => {
      if (props.store[key] !== undefined && props.store[key] !== null) form[key] = props.store[key];
    });
    // Never prefill secrets — they are not returned by the API anyway. The
    // client id is shown as a placeholder instead so it can be checked
    // against the Dev Dashboard without being resubmitted.
    form.auth_mode = props.store.auth_mode || 'token';
    form.access_token = '';
    form.client_id = '';
    form.client_secret = '';
    form.webhook_secret = '';
    form.clear_webhook_secret = false;
    // Copied, not referenced: the rows are edited in place and the store prop
    // belongs to the list page behind this modal.
    form.auto_sync_plan = (props.store.auto_sync_plan || []).map(s => ({ ...s }));
    if (props.store.location_id) {
      locations.value = [{ id: props.store.location_id, name: tp('Shopify_Location_N', 'Location {id}', { id: props.store.location_id }), active: true }];
    }
  }

  formRef.value?.clearValidate?.();
});

// ---------------------------------------------------------- automatic plan --
// One step per entity+direction: two "products push" steps would race each
// other over the same mappings, so a pair already in the list is never offered
// again.

const usedKeys = computed(() => new Set(form.auto_sync_plan.map(stepKey)));

/** The lag a POS sale would have without instant push. */
const inventoryEvery = computed(() => {
  const step = form.auto_sync_plan.find(s => s.entity === 'inventory' && s.direction === 'push');

  return step?.every || form.sync_interval_minutes || 60;
});

const remainingChoices = computed(() => autoStepChoices().filter(c => !usedKeys.value.has(stepKey(c))));

function takenByAnother(step, entity, direction) {
  return form.auto_sync_plan.some(s => s !== step && s.entity === entity && s.direction === direction);
}

function entityChoices(step) {
  return ENTITIES.map(e => ({
    value: e.value,
    label: entityOf(e.value).label,
    disabled: e.directions.every(d => takenByAnother(step, e.value, d)),
  }));
}

function directionChoices(step) {
  return entityOf(step.entity).directions.map(d => ({
    value: d,
    label: optionOf(DIRECTIONS, d).label,
    disabled: takenByAnother(step, step.entity, d),
  }));
}

/** Keep the direction legal: orders cannot be pushed, and pairs cannot repeat. */
function onEntityChange(step) {
  const allowed = entityOf(step.entity).directions.filter(d => !takenByAnother(step, step.entity, d));
  if (!allowed.includes(step.direction)) step.direction = allowed[0];
}

function addStep() {
  const next = remainingChoices.value[0];
  if (next) form.auto_sync_plan.push({ ...next, every: null });
}

async function loadLocations() {
  if (!isEdit.value) return;
  loadingLocations.value = true;
  try {
    const res = await http.get(`shopify/stores/${props.store.id}/locations`);
    locations.value = res?.locations || [];
    if (!locations.value.length) message.info(t('Shopify_No_Locations', 'That shop reports no locations'));
  } catch (e) {
    message.error(e?.data?.error || t('Shopify_Could_Not_Load_Locations', 'Could not load locations — check the connection first'));
  } finally {
    loadingLocations.value = false;
  }
}

async function submit() {
  try {
    await formRef.value.validate();
  } catch (e) {
    tab.value = 'connection';   // the required fields all live there
    return;
  }

  saving.value = true;
  try {
    const payload = { ...form };
    // A half-filled row is dropped rather than sent — the server would discard
    // it anyway, and silently saving nine steps when ten are on screen is worse
    // than saving the nine that are actually complete.
    payload.auto_sync_plan = form.auto_sync_plan
      .filter(s => s.entity && s.direction)
      .map(s => ({ entity: s.entity, direction: s.direction, every: s.every || 0 }));
    // Only the chosen kind of credential travels; blank fields are dropped
    // so the server keeps what it has rather than erasing it.
    if (payload.auth_mode === 'client_credentials') {
      delete payload.access_token;
      if (!payload.client_id) delete payload.client_id;
      if (!payload.client_secret) delete payload.client_secret;
    } else {
      delete payload.client_id;
      delete payload.client_secret;
      if (!payload.access_token) delete payload.access_token;
    }
    if (!payload.webhook_secret) delete payload.webhook_secret;
    if (payload.clear_webhook_secret) delete payload.webhook_secret;
    else delete payload.clear_webhook_secret;

    if (isEdit.value) await http.put(`shopify/stores/${props.store.id}`, payload);
    else await http.post('shopify/stores', payload);

    message.success(isEdit.value ? t('Shopify_Store_Updated', 'Store updated') : t('Shopify_Store_Connected', 'Store connected'));
    emit('saved');
  } catch (e) {
    message.error(firstError(e) || t('Shopify_Could_Not_Save', 'Could not save this store'));
  } finally {
    saving.value = false;
  }
}

function firstError(e) {
  const errors = e?.data?.errors;
  if (errors) {
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first.length) return first[0];
  }
  return e?.data?.message || '';
}

function close() {
  emit('update:open', false);
}
</script>

<style scoped>
.lead {
  margin-bottom: 14px;
  font-size: 13px;
  opacity: 0.7;
}
.hint {
  font-size: 11.5px;
  opacity: 0.55;
  margin-top: 2px;
}
.link {
  color: #5f9e3f;
  cursor: pointer;
}
.ent-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 10px;
}
.ent-card {
  display: flex;
  gap: 10px;
  padding: 12px 14px;
  border: 1px solid rgba(128, 128, 128, 0.2);
  border-radius: 12px;
  cursor: pointer;
}
.ent-card:hover {
  border-color: rgba(95, 158, 63, 0.5);
}
.ent-body {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.ent-title {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 500;
  font-size: 13.5px;
}
.ent-hint {
  font-size: 11.5px;
  opacity: 0.6;
}
.ent-dirs {
  margin-top: 3px;
}
.dir-tag {
  font-size: 10.5px;
  line-height: 17px;
  margin-inline-end: 3px;
}
.plan-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}
.plan-list {
  margin-top: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.plan-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.plan-warn {
  flex: 1 1 100%;
  font-size: 11.5px;
  color: #d48806;
}
</style>
