<template>
  <div class="page">
    <PageHeader :title="$t('Customer_Questions')" :breadcrumb="[$t('Store'), $t('Customer_Questions')]" />

    <a-card size="small" :body-style="{ padding: 0 }">
      <div class="toolbar">
        <a-input-search v-model:value="search" :placeholder="$t('Search')" allow-clear style="max-width: 220px" @search="fetch" @change="fetch" />
        <a-select v-model:value="statusFilter" style="width: 170px" @change="fetch">
          <a-select-option value="">{{ $t('All') }}</a-select-option>
          <a-select-option value="pending">{{ $t('Pending') }}</a-select-option>
          <a-select-option value="published">{{ $t('Published') }}</a-select-option>
          <a-select-option value="rejected">{{ $t('Rejected') }}</a-select-option>
        </a-select>
        <a-checkbox v-model:checked="unansweredOnly" @change="fetch">
          {{ $t('Unanswered') }}<template v-if="pendingCount"> ({{ pendingCount }})</template>
        </a-checkbox>
      </div>

      <a-table
        :columns="columns" :data-source="rows" :loading="isLoading"
        :pagination="{ pageSize: 10, showSizeChanger: true }" size="middle" row-key="id"
        :scroll="{ x: 'max-content' }"
        :locale="{ emptyText: $t('No_Questions_Yet') }"
      >
        <template #bodyCell="{ column, record }">
          <template v-if="column.key === 'product'">
            <a @click="openProduct(record)">{{ record.product_name || ('#' + record.product_id) }}</a>
          </template>
          <template v-else-if="column.key === 'question'">
            <a-tooltip :title="record.question">
              <span class="truncate">{{ record.question }}</span>
            </a-tooltip>
          </template>
          <template v-else-if="column.key === 'answer'">
            <a-tooltip v-if="record.answer" :title="record.answer">
              <span class="truncate">{{ record.answer }}</span>
            </a-tooltip>
            <a-tag v-else color="warning">{{ $t('Unanswered') }}</a-tag>
          </template>
          <template v-else-if="column.key === 'status'">
            <a-tag :color="statusColor(record.status)">{{ statusLabel(record.status) }}</a-tag>
          </template>
          <template v-else-if="column.key === 'created_at'">
            {{ formatDate(record.created_at) }}
          </template>
          <template v-else-if="column.key === 'actions'">
            <a-space>
              <a-button size="small" @click="startAnswer(record)">
                {{ record.answer ? $t('Edit') : $t('Answer') }}
              </a-button>
              <a-button v-if="record.status !== 'rejected'" size="small" danger @click="setStatus(record, 'rejected')">
                {{ $t('Reject') }}
              </a-button>
              <a-button v-else size="small" @click="setStatus(record, 'pending')">{{ $t('Restore') }}</a-button>
              <a-button size="small" danger @click="confirmDelete(record)">
                <template #icon><DeleteOutlined /></template>
              </a-button>
            </a-space>
          </template>
        </template>
      </a-table>
    </a-card>

    <a-modal v-model:open="answerOpen" :title="$t('Answer')" :confirm-loading="answering" @ok="submitAnswer">
      <template v-if="editing">
        <div class="q-preview">
          <div><strong>{{ editing.product_name || ('#' + editing.product_id) }}</strong></div>
          <div>{{ editing.question }}</div>
          <div class="muted">{{ editing.asker_name }} · {{ formatDate(editing.created_at) }}</div>
        </div>
        <a-textarea v-model:value="answerText" :rows="4" :maxlength="2000" :placeholder="$t('Write_an_answer')" />
        <p class="muted" style="margin-top: 8px">{{ $t('Publish_Answer') }}</p>
      </template>
    </a-modal>
  </div>
</template>

<script setup>
/**
 * Every "Ask About This Item" question in one list — the page the notification
 * bell and the sidebar badge link to. Same endpoints the product detail page
 * uses: GET store/questions?search&status&unanswered, POST .../{id}/answer,
 * POST .../{id}/status, DELETE .../{id}. Answering publishes.
 */
import { ref, computed, onMounted } from 'vue';
import { message, Modal } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { DeleteOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import http from '../../lib/http';
import { usePendingWorkStore } from '../../stores/pendingWork';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const pendingWork = usePendingWorkStore();

const isLoading = ref(true);
const search = ref('');
const statusFilter = ref('');
const unansweredOnly = ref(false);
const rows = ref([]);
const pendingCount = ref(0);

const answerOpen = ref(false);
const answering = ref(false);
const editing = ref(null);
const answerText = ref('');

const columns = computed(() => [
  { title: t('ProductName'), key: 'product' },
  { title: t('Asked_By'), dataIndex: 'asker_name', key: 'asker_name' },
  { title: t('Question'), key: 'question' },
  { title: t('Answer'), key: 'answer' },
  { title: t('Status'), key: 'status', width: 110 },
  { title: t('date'), key: 'created_at', width: 120 },
  { title: t('Actions'), key: 'actions', width: 260 },
]);

function statusLabel(s) {
  return { pending: t('Pending'), published: t('Published'), rejected: t('Rejected') }[s] || s;
}
function statusColor(s) {
  return { pending: 'warning', published: 'success', rejected: 'error' }[s] || 'default';
}
function formatDate(d) { return d ? new Date(d).toLocaleDateString() : ''; }

async function fetch() {
  try {
    const r = await http.get('store/questions', {
      search: search.value,
      status: statusFilter.value,
      unanswered: unansweredOnly.value ? 1 : 0,
      per_page: 100,
    });
    rows.value = r.data || [];
    pendingCount.value = Number(r.meta?.pending_count) || 0;
  } catch (e) {
    message.error(t('Failed'));
  } finally {
    isLoading.value = false;
  }
}

function openProduct(r) {
  if (r.product_id) router.push(`/products/${r.product_id}`);
}

function startAnswer(r) {
  editing.value = r;
  answerText.value = r.answer || '';
  answerOpen.value = true;
}

async function submitAnswer() {
  if (!answerText.value.trim()) return;
  answering.value = true;
  try {
    await http.post(`store/questions/${editing.value.id}/answer`, {
      answer: answerText.value.trim(),
      publish: 1,
    });
    message.success(t('Status_updated'));
    answerOpen.value = false;
    editing.value = null;
    await fetch();
    // The bell counts unanswered questions, so drop the badge right away
    // instead of waiting for the next poll.
    pendingWork.fetch(true);
  } catch (e) {
    message.error(t('Failed'));
  } finally {
    answering.value = false;
  }
}

async function setStatus(r, status) {
  try {
    await http.post(`store/questions/${r.id}/status`, { status });
    message.success(t('Status_updated'));
    fetch();
  } catch (e) {
    message.error(t('Failed'));
  }
}

function confirmDelete(r) {
  Modal.confirm({
    title: t('AreYouSure'),
    okText: t('Yes'),
    okType: 'danger',
    cancelText: t('No'),
    async onOk() {
      try {
        await http.delete(`store/questions/${r.id}`);
        message.success(t('Deleted_in_successfully'));
        await fetch();
        pendingWork.fetch(true);
      } catch (e) {
        message.error(t('Failed'));
      }
    },
  });
}

onMounted(() => {
  // Arriving from the bell means the unanswered ones are what you came for.
  if (route.query.unanswered !== undefined) unansweredOnly.value = true;
  fetch();
});
</script>

<style scoped>
.toolbar {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  align-items: center;
  padding: 16px;
  border-bottom: 1px solid rgba(5, 5, 5, 0.06);
}
.truncate {
  display: inline-block;
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: middle;
}
.q-preview {
  margin-bottom: 12px;
  padding: 10px 12px;
  border-radius: 6px;
  background: rgba(5, 5, 5, 0.03);
}
.muted {
  color: rgba(0, 0, 0, 0.45);
  font-size: 12px;
}
</style>
