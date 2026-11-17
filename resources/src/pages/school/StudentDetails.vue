<template>
  <div class="page">
    <PageHeader
      :title="student ? student.name : $t('Student')"
      :subtitle="student ? subtitle : ''"
      :breadcrumb="[$t('School'), $t('Students'), student ? student.admission_number : '']"
    >
      <template #actions>
        <a-button @click="$router.push('/school/students')">
          <template #icon><ArrowLeftOutlined /></template>
          {{ $t('Back') }}
        </a-button>
        <a-button v-if="canEdit" type="primary" @click="$router.push(`/school/students/${id}/edit`)">
          <template #icon><EditOutlined /></template>
          {{ $t('Edit') }}
        </a-button>
      </template>
    </PageHeader>

    <div v-if="loading" class="loading"><a-spin size="large" /></div>

    <template v-else-if="student">
      <a-alert
        v-if="student.medical_notes" type="warning" show-icon class="alert"
        :message="$t('School_Medical_Note', { notes: student.medical_notes })"
      />
      <a-alert
        v-if="!student.class_name" type="info" show-icon class="alert"
        :message="$t('School_No_Class_This_Year')"
      />

      <a-row :gutter="16">
        <a-col :xs="24" :lg="8">
          <a-card size="small" style="margin-bottom: 16px">
            <div class="id-head">
              <a-avatar :size="64" :src="student.image_url" class="id-avatar">
                {{ initials(student.name) }}
              </a-avatar>
              <div class="id-text">
                <div class="id-adm">{{ student.admission_number }}</div>
                <a-tag :color="optionOf(STUDENT_STATUSES, student.status).color">
                  {{ labelOf(STUDENT_STATUSES, student.status) }}
                </a-tag>
                <a-tag v-if="student.class_name" color="purple">
                  {{ student.class_name }}{{ student.section_name ? ' — ' + student.section_name : '' }}
                </a-tag>
              </div>
            </div>

            <a-descriptions :column="1" size="small" class="id-desc">
              <a-descriptions-item :label="$t('Age')">{{ student.age !== null ? student.age : '—' }}</a-descriptions-item>
              <a-descriptions-item :label="$t('Gender')">{{ labelOf(GENDERS, student.gender) }}</a-descriptions-item>
              <a-descriptions-item :label="$t('School_Born')">{{ student.date_of_birth ? date(student.date_of_birth) : '—' }}</a-descriptions-item>
              <a-descriptions-item :label="$t('School_Admitted')">{{ student.admission_date ? date(student.admission_date) : '—' }}</a-descriptions-item>
              <a-descriptions-item :label="$t('School_Roll_No')">{{ student.roll_number || '—' }}</a-descriptions-item>
              <a-descriptions-item :label="$t('School_Blood')">{{ student.blood_group || '—' }}</a-descriptions-item>
              <a-descriptions-item :label="$t('Phone')">{{ student.phone || '—' }}</a-descriptions-item>
              <a-descriptions-item :label="$t('Address')">
                {{ [student.address, student.city].filter(Boolean).join(', ') || '—' }}
              </a-descriptions-item>
            </a-descriptions>
          </a-card>

          <a-card size="small" :title="$t('Guardian')" style="margin-bottom: 16px">
            <a-descriptions v-if="student.guardian_name || student.guardian_phone" :column="1" size="small">
              <a-descriptions-item :label="$t('Name')">{{ student.guardian_name || '—' }}</a-descriptions-item>
              <a-descriptions-item :label="$t('School_Relation')">{{ student.guardian_relation || '—' }}</a-descriptions-item>
              <a-descriptions-item :label="$t('Phone')">{{ student.guardian_phone || '—' }}</a-descriptions-item>
              <a-descriptions-item :label="$t('Email')">{{ student.guardian_email || '—' }}</a-descriptions-item>
              <a-descriptions-item :label="$t('Rental_Occupation')">{{ student.guardian_occupation || '—' }}</a-descriptions-item>
            </a-descriptions>
            <a-empty v-else :image="simpleEmptyImage" :description="$t('School_No_Guardian')" />
          </a-card>

          <a-card size="small" :title="$t('School_Attendance_This_Year')">
            <div v-if="summary.rate !== null && summary.rate !== undefined" class="att-rate">
              <a-progress
                type="dashboard" :percent="summary.rate" :width="120"
                :stroke-color="rateStroke(summary.rate)"
              />
            </div>
            <a-descriptions :column="2" size="small">
              <a-descriptions-item :label="$t('Present')">{{ summary.present || 0 }}</a-descriptions-item>
              <a-descriptions-item :label="$t('Absent')">{{ summary.absent || 0 }}</a-descriptions-item>
              <a-descriptions-item :label="$t('Late')">{{ summary.late || 0 }}</a-descriptions-item>
              <a-descriptions-item :label="$t('Excused')">{{ summary.excused || 0 }}</a-descriptions-item>
            </a-descriptions>
          </a-card>
        </a-col>

        <a-col :xs="24" :lg="16">
          <div class="stats">
            <div class="stat">
              <span class="stat-label">{{ $t('Attendance') }}</span>
              <span class="stat-value">{{ stats.attendance_rate !== null ? stats.attendance_rate + '%' : '—' }}</span>
            </div>
            <div class="stat">
              <span class="stat-label">{{ $t('School_Years_Enrolled') }}</span>
              <span class="stat-value">{{ stats.enrollments || 0 }}</span>
            </div>
            <div class="stat">
              <span class="stat-label">{{ $t('School_Fees_Billed') }}</span>
              <span class="stat-value">{{ money(stats.billed || 0) }}</span>
            </div>
            <div class="stat" :class="{ 'stat--danger': (stats.due || 0) > 0 }">
              <span class="stat-label">{{ $t('Outstanding') }}</span>
              <span class="stat-value">{{ money(stats.due || 0) }}</span>
            </div>
          </div>

          <a-card size="small">
            <a-tabs v-model:activeKey="tab">
              <a-tab-pane key="results" :tab="$t('School_Results')">
                <a-table
                  :columns="resultColumns" :data-source="timeline.results || []" :loading="tabLoading"
                  :row-key="r => r.id" :pagination="false" :scroll="{ x: 'max-content' }" size="small"
                >
                  <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'marks'">
                      <span v-if="record.is_absent" class="muted">{{ $t('Absent') }}</span>
                      <span v-else-if="record.marks !== null">{{ record.marks }} / {{ record.max_marks }}</span>
                      <span v-else class="muted">—</span>
                    </template>
                    <template v-else-if="column.key === 'percentage'">
                      <span v-if="record.percentage !== null">{{ record.percentage }}%</span>
                      <span v-else class="muted">—</span>
                    </template>
                    <template v-else-if="column.key === 'grade'">
                      <a-tag v-if="record.grade" :color="gradeColor(record.grade)">{{ record.grade }}</a-tag>
                      <span v-else class="muted">—</span>
                    </template>
                    <template v-else-if="column.key === 'passed'">
                      <a-tag v-if="record.passed === true" color="success">{{ $t('School_Pass') }}</a-tag>
                      <a-tag v-else-if="record.passed === false" color="error">{{ $t('School_Fail') }}</a-tag>
                      <span v-else class="muted">—</span>
                    </template>
                    <template v-else-if="column.key === 'exam_date'">
                      {{ record.exam_date ? date(record.exam_date) : '—' }}
                    </template>
                  </template>
                  <template #emptyText><a-empty :image="simpleEmptyImage" :description="$t('School_No_Results')" /></template>
                </a-table>
              </a-tab-pane>

              <a-tab-pane key="attendance" :tab="$t('Attendance')">
                <a-table
                  :columns="attendanceColumns" :data-source="timeline.attendance || []" :loading="tabLoading"
                  :row-key="r => r.id" :pagination="{ pageSize: 15 }" size="small"
                >
                  <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'attendance_date'">{{ date(record.attendance_date) }}</template>
                    <template v-else-if="column.key === 'status'">
                      <a-tag :color="optionOf(ATTENDANCE_STATUSES, record.status).color">
                        {{ labelOf(ATTENDANCE_STATUSES, record.status) }}
                      </a-tag>
                    </template>
                  </template>
                  <template #emptyText><a-empty :image="simpleEmptyImage" :description="$t('School_Nothing_Recorded')" /></template>
                </a-table>
              </a-tab-pane>

              <a-tab-pane key="enrollments" :tab="$t('School_Enrolment_History')">
                <a-table
                  :columns="enrollmentColumns" :data-source="timeline.enrollments || []" :loading="tabLoading"
                  :row-key="r => r.id" :pagination="false" size="small"
                >
                  <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'status'">
                      <a-tag :color="optionOf(ENROLLMENT_STATUSES, record.status).color">
                        {{ labelOf(ENROLLMENT_STATUSES, record.status) }}
                      </a-tag>
                    </template>
                    <template v-else-if="column.key === 'enrolled_on'">
                      {{ record.enrolled_on ? date(record.enrolled_on) : '—' }}
                    </template>
                  </template>
                  <template #emptyText><a-empty :image="simpleEmptyImage" :description="$t('School_Never_Enrolled')" /></template>
                </a-table>
              </a-tab-pane>

              <a-tab-pane key="fees" :tab="$t('Fees')">
                <a-table
                  :columns="invoiceColumns" :data-source="timeline.invoices || []" :loading="tabLoading"
                  :row-key="r => r.id" :pagination="false" :scroll="{ x: 'max-content' }" size="small"
                >
                  <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'invoice_date'">{{ date(record.invoice_date) }}</template>
                    <template v-else-if="column.key === 'total'">{{ money(record.total) }}</template>
                    <template v-else-if="column.key === 'paid'">{{ money(record.paid) }}</template>
                    <template v-else-if="column.key === 'due'">
                      <span :class="{ danger: record.due > 0 }">{{ money(record.due) }}</span>
                    </template>
                    <template v-else-if="column.key === 'status'">
                      <a-tag :color="optionOf(INVOICE_STATUSES, record.status).color">
                        {{ labelOf(INVOICE_STATUSES, record.status) }}
                      </a-tag>
                    </template>
                  </template>
                  <template #emptyText><a-empty :image="simpleEmptyImage" :description="$t('School_Nothing_Billed')" /></template>
                </a-table>
              </a-tab-pane>
            </a-tabs>
          </a-card>
        </a-col>
      </a-row>
    </template>

    <a-empty v-else :description="$t('School_Student_Not_Found')" style="padding: 64px 0" />
  </div>
</template>

<script setup>
/**
 * One student's complete record: identity, guardian, attendance, results,
 * enrolment history and fees.
 *
 * The timeline arrives in ONE call rather than per tab — the tabs are different
 * views of the same history, and a teacher switching between them should never
 * wait.
 */
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { message, Empty } from 'ant-design-vue';
import { EditOutlined, ArrowLeftOutlined } from '@ant-design/icons-vue';
import PageHeader from '../../components/PageHeader.vue';
import { useFormat } from '../../composables/useFormat';
import { useAuthStore } from '../../stores/auth';
import {
  GENDERS, STUDENT_STATUSES, ENROLLMENT_STATUSES, ATTENDANCE_STATUSES,
  INVOICE_STATUSES, labelOf, optionOf, gradeColor, tp,
} from './schoolOptions';
import http from '../../lib/http';
import { t } from '../../i18n';

const route = useRoute();
const auth = useAuthStore();
const { money, date } = useFormat();

const id = computed(() => route.params.id);
const canEdit = computed(() => auth.can('school_students_edit'));
const simpleEmptyImage = Empty.PRESENTED_IMAGE_SIMPLE;

const student = ref(null);
const timeline = ref({});
const loading = ref(false);
const tabLoading = ref(false);
const tab = ref('results');

const stats = computed(() => student.value?.stats || {});
const summary = computed(() => timeline.value.attendance_summary || {});

const subtitle = computed(() => {
  const s = student.value;
  if (!s) return '';
  return [
    s.class_name ? `${s.class_name}${s.section_name ? ' — ' + s.section_name : ''}` : null,
    s.roll_number ? tp('School_Roll_N', 'Roll {n}', { n: s.roll_number }) : null,
    s.age !== null ? tp('School_Age_Years', '{n} yrs', { n: s.age }) : null,
    s.guardian_phone,
  ].filter(Boolean).join(' · ');
});

function rateStroke(rate) {
  if (rate >= 90) return '#16a34a';
  if (rate >= 75) return '#faad14';
  return '#ff4d4f';
}

const resultColumns = computed(() => [
  { title: t('School_Exam', 'Exam'), dataIndex: 'exam_name', key: 'exam_name' },
  { title: t('Subject', 'Subject'), dataIndex: 'subject_name', key: 'subject_name' },
  { title: t('Date', 'Date'), key: 'exam_date', dataIndex: 'exam_date', width: 130 },
  { title: t('School_Marks', 'Marks'), key: 'marks', dataIndex: 'marks', width: 130 },
  { title: '%', key: 'percentage', dataIndex: 'percentage', width: 90 },
  { title: t('School_Grade', 'Grade'), key: 'grade', dataIndex: 'grade', width: 90 },
  { title: t('Zatca_Result', 'Result'), key: 'passed', dataIndex: 'passed', width: 100 },
]);
const attendanceColumns = computed(() => [
  { title: t('Date', 'Date'), key: 'attendance_date', dataIndex: 'attendance_date', width: 150 },
  { title: t('Status', 'Status'), key: 'status', dataIndex: 'status', width: 130 },
  { title: t('School_Remarks', 'Remarks'), dataIndex: 'remarks', key: 'remarks' },
]);
const enrollmentColumns = computed(() => [
  { title: t('Year', 'Year'), dataIndex: 'year_name', key: 'year_name' },
  { title: t('Class', 'Class'), dataIndex: 'class_name', key: 'class_name' },
  { title: t('Section', 'Section'), dataIndex: 'section_name', key: 'section_name', width: 110 },
  { title: t('School_Roll', 'Roll'), dataIndex: 'roll_number', key: 'roll_number', width: 90 },
  { title: t('School_Enrolled', 'Enrolled'), key: 'enrolled_on', dataIndex: 'enrolled_on', width: 130 },
  { title: t('Status', 'Status'), key: 'status', dataIndex: 'status', width: 130 },
]);
const invoiceColumns = computed(() => [
  { title: t('Date', 'Date'), key: 'invoice_date', dataIndex: 'invoice_date', width: 130 },
  { title: t('Reference', 'Reference'), dataIndex: 'reference', key: 'reference', width: 160 },
  { title: t('Total', 'Total'), key: 'total', dataIndex: 'total', width: 120 },
  { title: t('Paid', 'Paid'), key: 'paid', dataIndex: 'paid', width: 120 },
  { title: t('Due', 'Due'), key: 'due', dataIndex: 'due', width: 120 },
  { title: t('Status', 'Status'), key: 'status', dataIndex: 'status', width: 130 },
]);

function initials(name) {
  return String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2)
    .map(part => part[0].toUpperCase()).join('');
}

async function load() {
  loading.value = true;
  tabLoading.value = true;
  try {
    const [record, history] = await Promise.all([
      http.get(`school/students/${id.value}`),
      http.get(`school/students/${id.value}/timeline`).catch(() => ({})),
    ]);
    student.value = record?.student || null;
    timeline.value = history || {};
  } catch (e) {
    message.error(t('School_Err_Load_Student', 'Could not load this student'));
  } finally {
    loading.value = false;
    tabLoading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.muted {
  color: rgba(128, 128, 128, 0.7);
}
.danger {
  color: #ff4d4f;
}
.loading {
  display: flex;
  justify-content: center;
  padding: 96px 0;
}
.alert {
  margin-bottom: 12px;
}
.id-head {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 14px;
}
.id-avatar {
  flex: none;
  background: rgba(var(--app-primary-rgb, 109, 40, 217), 0.15);
  color: var(--app-primary, #6d28d9);
  font-size: 20px;
}
.id-adm {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-weight: 600;
  margin-bottom: 6px;
}
.id-desc :deep(.ant-descriptions-item) {
  padding-bottom: 6px !important;
}
.att-rate {
  display: flex;
  justify-content: center;
  margin-bottom: 10px;
}
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 14px 16px;
  border: 1px solid rgba(128, 128, 128, 0.2);
  border-radius: 14px;
}
.stat--danger {
  border-color: rgba(255, 77, 79, 0.4);
  background: rgba(255, 77, 79, 0.05);
}
.stat-label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.55;
}
.stat-value {
  font-size: 18px;
  font-weight: 600;
}
</style>
