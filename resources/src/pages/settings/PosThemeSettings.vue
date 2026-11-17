<template>
  <div class="page">
    <div v-if="loading" style="display: flex; justify-content: center; padding: 96px 0">
      <a-spin size="large" />
    </div>

    <a-form v-else layout="vertical">
      <a-card size="small" :title="$t('PosTheme_Layout')" style="margin-bottom: 16px">
        <div class="pt-grid" role="radiogroup" :aria-label="$t('PosTheme_Layout')">
          <button
            v-for="th in POS_THEMES"
            :key="th.key"
            type="button"
            role="radio"
            class="pt-card"
            :class="{ 'is-active': form.pos_theme === th.key }"
            :aria-checked="form.pos_theme === th.key ? 'true' : 'false'"
            @click="form.pos_theme = th.key"
          >
            <!-- Wireframe thumbnail of the layout (decorative). -->
            <span class="pt-thumb" :class="['pt-thumb-' + th.key, { 'is-dark': thumbDark }]" aria-hidden="true">
              <span class="pt-thumb-bar"></span>
              <span class="pt-thumb-body">
                <span class="pt-thumb-cart">
                  <span class="pt-thumb-line"></span>
                  <span class="pt-thumb-line"></span>
                  <span class="pt-thumb-line"></span>
                  <span class="pt-thumb-pay"></span>
                </span>
                <span class="pt-thumb-tiles">
                  <span v-for="n in thumbTileCount(th.key)" :key="n" class="pt-thumb-tile"><span class="pt-thumb-img"></span></span>
                </span>
              </span>
            </span>
            <span class="pt-card-text">
              <span class="pt-card-title">
                {{ $t(th.label) }}
                <CheckCircleFilled v-if="form.pos_theme === th.key" class="pt-card-check" />
              </span>
              <span class="pt-card-desc">{{ $t(th.desc) }}</span>
            </span>
          </button>
        </div>
        <a-typography-paragraph type="secondary" style="margin: 12px 0 0; font-size: 12.5px">
          {{ $t('PosTheme_Phones_Note') }}
        </a-typography-paragraph>
      </a-card>

      <a-card size="small" :title="$t('PosTheme_Color_Mode')" style="margin-bottom: 16px">
        <a-radio-group v-model:value="form.pos_color_mode" button-style="solid">
          <a-radio-button v-for="md in POS_COLOR_MODES" :key="md.key" :value="md.key">
            {{ $t(md.label) }}
          </a-radio-button>
        </a-radio-group>
      </a-card>

      <a-card size="small" :title="$t('PosTheme_Preview')" style="margin-bottom: 16px">
        <template #extra>
          <div class="pt-preview-tools">
            <a-segmented v-model:value="device" size="small" :options="deviceOptions" />
            <a-segmented v-model:value="previewDir" size="small" :options="dirOptions" />
          </div>
        </template>
        <div ref="stageEl" class="pt-stage">
          <div class="pt-frame-box" :style="frameBoxStyle">
            <iframe
              v-if="frameSrc"
              ref="frameEl"
              class="pt-frame"
              :src="frameSrc"
              :title="$t('PosTheme_Preview')"
              :style="frameStyle"
              tabindex="-1"
            ></iframe>
            <div v-if="!frameReady" class="pt-frame-loading"><a-spin /></div>
          </div>
        </div>
        <a-typography-paragraph type="secondary" style="margin: 10px 0 0; font-size: 12.5px">
          {{ $t('PosTheme_Preview_Help') }}
        </a-typography-paragraph>
      </a-card>

      <a-card size="small" style="margin-bottom: 16px">
        <div class="pt-switch-row">
          <div>
            <div class="pt-switch-label">{{ $t('PosTheme_Device_Override') }}</div>
            <div class="pt-switch-help">{{ $t('PosTheme_Device_Override_Help') }}</div>
          </div>
          <a-switch v-model:checked="form.pos_theme_device_override" />
        </div>
      </a-card>

      <div class="pt-actions">
        <a-button @click="openPos">
          <template #icon><ExportOutlined /></template>
          {{ $t('PosTheme_Open_Pos') }}
        </a-button>
        <a-button type="primary" :loading="saving" :disabled="loadFailed" @click="save">{{ $t('Save') }}</a-button>
      </div>
      <a-typography-paragraph type="secondary" style="margin: 8px 0 0; font-size: 12.5px; text-align: end">
        {{ $t('PosTheme_Open_Pos_Help') }}
      </a-typography-paragraph>
    </a-form>
  </div>
</template>

<script setup>
/**
 * System Settings → POS Theme. Store-wide POS layout + colour mode, saved to
 * pos_settings (pos_theme / pos_color_mode / pos_theme_device_override) via
 * its own endpoint PUT pos_settings_theme, so it can never overwrite the
 * POS Settings / POS Receipt pages' fields (or be overwritten by them).
 * Themes are presentation only — see lib/posTheme.js.
 */
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { message } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import { CheckCircleFilled, ExportOutlined } from '@ant-design/icons-vue';
import http from '../../lib/http';
import {
  POS_THEMES,
  POS_COLOR_MODES,
  DEFAULT_POS_THEME,
  DEFAULT_POS_COLOR_MODE,
  normalizePosTheme,
  normalizePosColorMode,
  isDeviceOverrideAllowed,
  posModeIsDark,
} from '../../lib/posTheme';

const { t } = useI18n();
const router = useRouter();

const loading = ref(true);
const saving = ref(false);
const loadFailed = ref(false);
const form = ref({
  pos_theme: DEFAULT_POS_THEME,
  pos_color_mode: DEFAULT_POS_COLOR_MODE,
  pos_theme_device_override: false,
});

const thumbDark = computed(() => posModeIsDark(form.value.pos_color_mode));

function thumbTileCount(theme) {
  if (theme === 'touch') return 4;
  if (theme === 'compact') return 12;
  return 6;
}

// ---- Live preview: /pos-theme-preview (mock POS shell, sample data) in an
// iframe rendered at a real device width, then scaled to fit — so the theme
// stylesheet's breakpoints and RTL behave exactly as on a till. The frame
// loads once; later changes go over postMessage (same origin).
const PREVIEW_SOURCE = 'pos-theme-preview';
const DEVICES = {
  desktop: { w: 1366, h: 768 },
  tablet: { w: 820, h: 1180 },
  phone: { w: 390, h: 844 },
};
const MAX_PORTRAIT_HEIGHT = 620;

const device = ref('desktop');
const previewDir = ref(document.documentElement.getAttribute('dir') === 'rtl' ? 'rtl' : 'ltr');
const stageEl = ref(null);
const frameEl = ref(null);
const stageWidth = ref(0);
const frameSrc = ref('');
const frameReady = ref(false);

const deviceOptions = computed(() => [
  { value: 'desktop', label: t('PosTheme_Device_Desktop') },
  { value: 'tablet', label: t('PosTheme_Device_Tablet') },
  { value: 'phone', label: t('PosTheme_Device_Phone') },
]);
const dirOptions = computed(() => [
  { value: 'ltr', label: t('PosTheme_Dir_Ltr') },
  { value: 'rtl', label: t('PosTheme_Dir_Rtl') },
]);

const previewScale = computed(() => {
  const d = DEVICES[device.value];
  if (!stageWidth.value) return 0.5;
  let s = Math.min(1, stageWidth.value / d.w);
  if (device.value !== 'desktop') s = Math.min(s, MAX_PORTRAIT_HEIGHT / d.h);
  return s;
});
const frameBoxStyle = computed(() => {
  const d = DEVICES[device.value];
  return { width: Math.round(d.w * previewScale.value) + 'px', height: Math.round(d.h * previewScale.value) + 'px' };
});
const frameStyle = computed(() => {
  const d = DEVICES[device.value];
  return { width: d.w + 'px', height: d.h + 'px', transform: `scale(${previewScale.value})` };
});

function postPreviewState() {
  const win = frameEl.value && frameEl.value.contentWindow;
  if (!win) return;
  win.postMessage({
    source: PREVIEW_SOURCE,
    theme: form.value.pos_theme,
    mode: form.value.pos_color_mode,
    dir: previewDir.value,
  }, window.location.origin);
}

function onPreviewMessage(e) {
  if (e.origin !== window.location.origin) return;
  if (!e.data || e.data.source !== PREVIEW_SOURCE || !e.data.ready) return;
  frameReady.value = true;
  postPreviewState();
}

watch(() => [form.value.pos_theme, form.value.pos_color_mode, previewDir.value], postPreviewState);

let resizeObs = null;
function startPreview() {
  frameSrc.value = router.resolve({
    name: 'pos-theme-preview',
    query: { theme: form.value.pos_theme, mode: form.value.pos_color_mode, dir: previewDir.value },
  }).href;
  if (stageEl.value && typeof ResizeObserver !== 'undefined') {
    resizeObs = new ResizeObserver(entries => {
      stageWidth.value = entries[0].contentRect.width;
    });
    resizeObs.observe(stageEl.value);
  } else if (stageEl.value) {
    stageWidth.value = stageEl.value.clientWidth;
  }
}

window.addEventListener('message', onPreviewMessage);
onBeforeUnmount(() => {
  window.removeEventListener('message', onPreviewMessage);
  if (resizeObs) resizeObs.disconnect();
});

// Preview in a new tab via ?preview_theme= — applies to that tab only.
function openPos() {
  const href = router.resolve({
    name: 'pos',
    query: { preview_theme: form.value.pos_theme, preview_mode: form.value.pos_color_mode },
  }).href;
  window.open(href, '_blank', 'noopener');
}

async function save() {
  if (loadFailed.value) return;
  saving.value = true;
  try {
    await http.put('pos_settings_theme', {
      pos_theme: form.value.pos_theme,
      pos_color_mode: form.value.pos_color_mode,
      pos_theme_device_override: !!form.value.pos_theme_device_override,
    });
    message.success(t('Successfully_Updated'));
  } catch (e) {
    message.error(e?.data?.message || t('InvalidData'));
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    const data = await http.get('get_pos_Settings_api');
    const ps = data.pos_settings || {};
    form.value = {
      pos_theme: normalizePosTheme(ps.pos_theme) || DEFAULT_POS_THEME,
      pos_color_mode: normalizePosColorMode(ps.pos_color_mode) || DEFAULT_POS_COLOR_MODE,
      pos_theme_device_override: isDeviceOverrideAllowed(ps),
    };
  } catch (e) {
    // Don't let Save push the Classic/Light placeholders over the store's
    // real theme when we never saw it.
    loadFailed.value = true;
    message.error(t('InvalidData'));
  } finally {
    loading.value = false;
  }
  // The stage lives inside the v-else form — start once it has rendered.
  await nextTick();
  startPreview();
});
</script>

<style scoped>
.pt-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 12px;
}
.pt-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 10px;
  text-align: start;
  font: inherit;
  color: inherit;
  background: transparent;
  border: 1px solid rgba(128, 128, 128, 0.25);
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 120ms ease, box-shadow 120ms ease;
}
.pt-card:hover { border-color: var(--app-primary, #6d28d9); }
.pt-card:focus-visible { outline: 2px solid var(--app-primary, #6d28d9); outline-offset: 2px; }
.pt-card.is-active {
  border-color: var(--app-primary, #6d28d9);
  box-shadow: 0 0 0 3px rgba(var(--app-primary-rgb, 109, 40, 217), 0.15);
}
.pt-card-text { display: flex; flex-direction: column; gap: 2px; }
.pt-card-title { display: flex; align-items: center; justify-content: space-between; font-weight: 600; font-size: 13.5px; }
.pt-card-check { color: var(--app-primary, #6d28d9); }
.pt-card-desc { font-size: 12px; opacity: 0.65; line-height: 1.4; }

/* ---- Wireframe thumbnails: a miniature POS (header, cart, product grid).
   Colours here depict the POS palette, not the admin surface. ---- */
.pt-thumb {
  --pt-bg: #f7f7fb; --pt-surface: #ffffff; --pt-line: #e6e6ec;
  --pt-ink: #d9d9e3; --pt-accent: #6f53d9; --pt-img: #ece9fb;
  display: flex;
  flex-direction: column;
  height: 104px;
  border-radius: 6px;
  overflow: hidden;
  background: var(--pt-bg);
  border: 1px solid var(--pt-line);
}
.pt-thumb.is-dark {
  --pt-bg: #0f1220; --pt-surface: #171b2e; --pt-line: #262b45;
  --pt-ink: #33395a; --pt-accent: #8b74f0; --pt-img: #2a2650;
}
.pt-thumb-bar { height: 10px; flex-shrink: 0; background: var(--pt-surface); border-bottom: 1px solid var(--pt-line); }
.pt-thumb-body { flex: 1; display: flex; gap: 4px; padding: 4px; min-height: 0; }
.pt-thumb-cart {
  width: 34%;
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 4px;
  border-radius: 3px;
  background: var(--pt-surface);
}
.pt-thumb-line { height: 5px; border-radius: 2px; background: var(--pt-ink); }
.pt-thumb-pay { margin-top: auto; height: 9px; border-radius: 2px; background: var(--pt-accent); }
.pt-thumb-tiles {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  grid-auto-rows: 1fr;
  gap: 3px;
  min-width: 0;
}
.pt-thumb-tile { border-radius: 3px; background: var(--pt-surface); border: 1px solid var(--pt-line); overflow: hidden; }
.pt-thumb-img { display: block; height: 35%; background: var(--pt-img); }

.pt-thumb-touch .pt-thumb-tiles { grid-template-columns: repeat(2, 1fr); gap: 4px; }
.pt-thumb-touch .pt-thumb-line { height: 8px; }
.pt-thumb-touch .pt-thumb-pay { height: 13px; }
.pt-thumb-compact .pt-thumb-cart { width: 28%; gap: 2px; }
.pt-thumb-compact .pt-thumb-line { height: 3px; }
.pt-thumb-compact .pt-thumb-tiles { grid-template-columns: repeat(4, 1fr); gap: 2px; }
.pt-thumb-compact .pt-thumb-img { display: none; }
.pt-thumb-split .pt-thumb-body { flex-direction: row-reverse; }
.pt-thumb-cards .pt-thumb-img { height: 72%; }
.pt-thumb-cards .pt-thumb-tile { border-radius: 5px; }

/* ---- Live preview ---- */
.pt-preview-tools { display: flex; gap: 8px; flex-wrap: wrap; }
.pt-stage {
  display: flex;
  justify-content: center;
  padding: 12px;
  border-radius: 10px;
  background: rgba(128, 128, 128, 0.08);
  overflow: hidden;
}
.pt-frame-box {
  position: relative;
  overflow: hidden;
  border-radius: 8px;
  box-shadow: 0 0 0 1px rgba(128, 128, 128, 0.25), 0 8px 24px -12px rgba(0, 0, 0, 0.35);
  transition: width 160ms ease, height 160ms ease;
}
.pt-frame {
  position: absolute;
  top: 0;
  left: 0;
  border: 0;
  transform-origin: 0 0; /* scrollable mock; it has no click handlers */
}
.pt-frame-loading {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(128, 128, 128, 0.08);
}

.pt-switch-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.pt-switch-label { font-weight: 600; }
.pt-switch-help { font-size: 12.5px; opacity: 0.65; margin-top: 2px; }
.pt-actions { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
</style>
