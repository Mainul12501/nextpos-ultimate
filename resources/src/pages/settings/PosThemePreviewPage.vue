<template>
  <!-- Standalone mock of the POS shell for System Settings → POS Theme.
       Rendered inside an <iframe> sized to a real desktop/tablet/phone width
       so the theme stylesheet's breakpoints behave exactly as on a till.
       Same class names + inline styles as PosPage.vue's shell; sample data
       only — never loads stock, customers or the register. -->
  <div class="pos-scope ptp-root" :class="rootClasses" :data-pos-theme="theme" :dir="dir">
    <div class="pos-codecanyon ptp-shell" style="background: var(--pos-bg); color: var(--pos-text); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">

      <!-- Header -->
      <nav class="pos-shell-header ptp-header" style="border-bottom: 1px solid var(--pos-border); background: var(--pos-surface);">
        <div class="ptp-brand" style="color: var(--pos-text);">S</div>
        <div class="ptp-register" style="color: var(--pos-text);">
          {{ $t('Register') }} #1
          <span class="ptp-pill" style="background: var(--pos-success-soft); color: var(--pos-success-text);">● {{ $t('PosTheme_Preview_Open') }}</span>
        </div>
        <div class="ptp-trigger" style="border: 1px solid var(--pos-dk-line, #cfeaf2); background: var(--pos-dk-cyan-soft, #f0fbff);">
          <Warehouse class="ptp-ico" style="color: #0891b2" />
          <span class="ptp-trigger-body">
            <span class="ptp-eyebrow" style="color: var(--pos-dk-cyan-text, #0e7490);">{{ $t('Warehouse') }}</span>
            <span class="ptp-trigger-label" style="color: var(--pos-text);">{{ $t('PosTheme_Preview_Main_Store') }}</span>
          </span>
        </div>
        <div class="ptp-trigger" style="border: 1px solid var(--pos-dk-line, #fbe4ed); background: var(--pos-dk-rose-soft, #fff5f8);">
          <span class="ptp-avatar" style="background: #f43f5e; color: #fff;">W</span>
          <span class="ptp-trigger-body">
            <span class="ptp-eyebrow" style="color: var(--pos-dk-rose-text, #be185d);">{{ $t('Customer') }}</span>
            <span class="ptp-trigger-label" style="color: var(--pos-text);">{{ $t('PosTheme_Preview_Walk_In') }}</span>
          </span>
        </div>
        <span style="flex: 1"></span>
        <button v-for="(ic, i) in headerIcons" :key="i" type="button" class="pos-shell-icon-btn ptp-icon-btn" style="border: 1px solid var(--pos-border); background: var(--pos-surface); color: var(--pos-text-2);">
          <component :is="ic" class="ptp-ico" />
        </button>
      </nav>

      <div class="pos-shell-main ptp-main">
        <!-- Cart -->
        <aside class="pos-shell-cart-aside ptp-cart" style="border-right: 1px solid var(--pos-border); background: var(--pos-surface);">
          <div class="pos-shell-cart-scroll ptp-cart-scroll">
            <div class="ptp-cart-head" style="border-bottom: 1px solid var(--pos-border-soft);">
              <span class="ptp-cart-head-icon" style="background: var(--pos-accent-soft-2); color: var(--pos-accent);"><ShoppingCart class="ptp-ico" /></span>
              <span style="flex: 1; font-weight: 600; font-size: 13px;">{{ $t('Current_Cart') }}</span>
              <span style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--pos-accent);">{{ money(total) }}</span>
            </div>
            <div
              v-for="item in cart"
              :key="item.code"
              class="pos-shell-cart-row"
              style="display: grid; grid-template-columns: 32px 1fr auto; gap: 8px; padding: 6px 8px; border-radius: 8px; margin-bottom: 2px;">
              <div
                class="pos-shell-cart-thumb"
                style="width: 32px; height: 32px; border-radius: 6px; align-self: center; display: grid; place-items: center; font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 10px; color: rgba(31,31,44,0.55);"
                :style="item.image
                  ? { background: 'var(--pos-surface)', backgroundImage: imageLayers(item), backgroundPosition: 'center', backgroundSize: 'contain', backgroundRepeat: 'no-repeat', border: '1px solid var(--pos-border)' }
                  : { background: swatch(6) }">
                <span v-if="!item.image">{{ initials(item.name) }}</span>
              </div>
              <div style="min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 2px;">
                <div class="pos-shell-cart-name" style="font-size: 13px; font-weight: 600; color: var(--pos-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ item.name }}</div>
                <div style="font-size: 10px; color: var(--pos-text-muted); font-family: 'JetBrains Mono', monospace;">{{ $t('SKU') }} {{ item.code }}</div>
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                  <div class="pos-shell-qty" style="display: inline-flex; align-items: center; border: 1px solid var(--pos-border); border-radius: 5px; height: 24px; background: var(--pos-surface);">
                    <button type="button" class="pos-shell-qty-btn" style="width: 22px; height: 22px; background: transparent; border: 0; color: var(--pos-text-2); font-size: 14px; padding: 0;">−</button>
                    <input class="pos-shell-qty-input" :value="item.qty" readonly style="width: 32px; height: 100%; border: 0; text-align: center; font-size: 12px; font-family: 'JetBrains Mono', monospace; background: transparent; color: var(--pos-text);" />
                    <button type="button" class="pos-shell-qty-btn" style="width: 22px; height: 22px; background: transparent; border: 0; color: var(--pos-text-2); font-size: 14px; padding: 0;">+</button>
                  </div>
                  <span style="font-size: 12px; color: var(--pos-text-2); font-family: 'JetBrains Mono', monospace; white-space: nowrap;">× {{ money(item.price) }}</span>
                </div>
              </div>
              <div style="display: flex; flex-direction: column; align-items: flex-end; justify-content: space-between; gap: 4px;">
                <div style="display: inline-flex; gap: 2px;">
                  <button type="button" class="pos-shell-line-btn" style="background: transparent; border: 0; padding: 4px; border-radius: 4px; color: var(--pos-text-muted); display: inline-flex;"><Pencil style="width: 13px; height: 13px;" /></button>
                  <button type="button" class="pos-shell-line-btn" style="background: transparent; border: 0; padding: 4px; border-radius: 4px; color: var(--pos-text-muted); display: inline-flex;"><X style="width: 14px; height: 14px;" /></button>
                </div>
                <div class="pos-shell-line-total" style="font-size: 13px; font-weight: 600; font-family: 'JetBrains Mono', monospace; color: var(--pos-text);">{{ money(item.price * item.qty) }}</div>
              </div>
            </div>
          </div>

          <div class="pos-shell-totals" style="border-top: 1px solid var(--pos-border); padding: 8px 12px; background: var(--pos-bg);">
            <div class="pos-shell-charges-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-bottom: 8px;">
              <label v-for="c in charges" :key="c" style="display: block; margin: 0;">
                <div style="font-size: 9px; font-weight: 700; letter-spacing: 0.08em; color: var(--pos-text-muted); margin-bottom: 4px;">{{ c }}</div>
                <div style="display: flex; align-items: center; height: 28px; border: 1px solid var(--pos-border); border-radius: 6px; background: var(--pos-surface);">
                  <input value="0" readonly style="width: 100%; border: 0; padding: 0 8px; background: transparent; font-size: 12px; font-family: 'JetBrains Mono', monospace; color: var(--pos-text); outline: none;" />
                </div>
              </label>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--pos-text-2);">
              <span>{{ $t('Total') }}</span>
              <span style="font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 16px; color: var(--pos-text);">{{ money(total) }}</span>
            </div>
            <button type="button" class="ptp-mobile-pay" style="background: var(--pos-accent); color: #fff;">
              <span>{{ $t('pos.Pay_Now') }}</span><span>{{ money(total) }}</span>
            </button>
          </div>
        </aside>

        <!-- Products -->
        <section class="pos-shell-section ptp-section" style="background: var(--pos-surface);">
          <div class="pos-shell-search-row" style="padding: 4px 6px; display: flex; gap: 8px; align-items: center;">
            <div style="flex: 1; position: relative;">
              <Search style="position: absolute; inset-inline-start: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--pos-text-muted);" />
              <input
                class="pos-shell-search-input"
                readonly
                :placeholder="$t('Scan_Search_Product_by_Code_Name')"
                style="width: 100%; height: 36px; padding: 0 12px 0 36px; padding-inline-start: 36px; background: var(--pos-surface); border: 1px solid var(--pos-border); border-radius: 8px; font-size: 13px; color: var(--pos-text); outline: none; font-family: inherit;" />
            </div>
            <button type="button" class="pos-shell-action-btn" style="height: 36px; padding: 0 12px; background: transparent; color: var(--pos-text); border: 1px solid var(--pos-border); border-radius: 8px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
              <ScanBarcode style="width: 16px; height: 16px;" /><span>{{ $t('Scan') }}</span>
            </button>
          </div>

          <div class="pos-shell-products-scroll ptp-products-scroll">
            <div class="pos-shell-products-grid ptp-grid">
              <button
                v-for="p in products"
                :key="p.code"
                type="button"
                class="pos-shell-product-card"
                style="background: var(--pos-surface); border: 1px solid var(--pos-border); border-radius: 12px; padding: 10px; text-align: start; position: relative; font-family: inherit; color: inherit;">
                <div class="pos-shell-card-media" style="margin: -10px -10px 8px; position: relative;">
                  <div
                    class="pos-shell-card-swatch"
                    :style="{
                      width: '100%', height: '58px', borderRadius: '12px 12px 0 0', overflow: 'hidden', position: 'relative',
                      background: p.image ? 'var(--pos-surface)' : swatch(8),
                      backgroundImage: p.image ? imageLayers(p) : null,
                      backgroundRepeat: 'no-repeat', backgroundPosition: 'center', backgroundSize: 'contain'
                    }">
                    <span v-if="!p.image" class="pos-shell-card-initials" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 16px; color: rgba(31,31,44,0.6); text-transform: uppercase;">{{ initials(p.name) }}</span>
                  </div>
                  <div v-if="p.discount" class="pos-shell-card-discount" style="position: absolute; top: 6px; left: 6px; background: rgba(255,255,255,0.9); border-radius: 6px; padding: 2px 6px; color: #d64545; font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 700;">-{{ p.discount }}%</div>
                </div>
                <div class="pos-shell-card-name" style="font-size: 13px; font-weight: 500; margin-bottom: 2px; line-height: 1.2; color: var(--pos-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ p.name }}</div>
                <div class="pos-shell-card-meta" style="font-size: 10px; color: var(--pos-text-muted); font-family: 'JetBrains Mono', monospace; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $t('SKU') }} {{ p.code }} · {{ p.stock }}</div>
                <div class="pos-shell-card-foot" style="display: flex; align-items: center; justify-content: space-between;">
                  <div class="pos-shell-card-price" style="font-size: 14px; font-weight: 700; font-family: 'JetBrains Mono', monospace; color: var(--pos-accent);">{{ money(p.price) }}</div>
                  <span class="pos-shell-add-btn" style="width: 24px; height: 24px; background: var(--pos-accent-soft-3); color: var(--pos-accent); border-radius: 6px; display: grid; place-items: center;">
                    <Plus style="width: 14px; height: 14px;" />
                  </span>
                </div>
              </button>
            </div>
          </div>
        </section>
      </div>

      <!-- Pay bar (desktop) -->
      <div class="pos-shell-pay-bar ptp-paybar" style="background: var(--pos-surface); border-top: 1px solid var(--pos-border);">
        <span class="ptp-online" style="color: var(--pos-success-text);"><span class="ptp-dot" style="background: #2fae5e;"></span>{{ $t('PosTheme_Preview_Online') }}</span>
        <span style="flex: 1"></span>
        <button v-for="a in payActions" :key="a.label" type="button" class="pos-shell-action-btn" style="height: 36px; padding: 0 14px; background: transparent; color: var(--pos-text); border: 1px solid var(--pos-border); border-radius: 8px; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px;">
          <component :is="a.icon" style="width: 16px; height: 16px;" /><span>{{ a.label }}</span>
        </button>
        <button type="button" class="pos-shell-pay-btn" style="height: 48px; padding: 0 28px; background: var(--pos-accent); color: #fff; border: 0; border-radius: 10px; font-size: 15px; font-weight: 600; display: inline-flex; align-items: center; gap: 10px;">
          {{ $t('pos.Pay_Now') }} · {{ money(total) }}
        </button>
      </div>

      <!-- Bottom tab bar (tablet / phone) -->
      <nav class="ptp-tabbar" style="background: var(--pos-surface); border-top: 1px solid var(--pos-border);">
        <span v-for="(tab, i) in tabs" :key="tab.label" class="ptp-tab" :style="{ color: i === 0 ? 'var(--pos-accent)' : 'var(--pos-text-muted)' }">
          <component :is="tab.icon" class="ptp-ico" /><span>{{ tab.label }}</span>
        </span>
      </nav>
    </div>
  </div>
</template>

<script setup>
/**
 * Route /pos-theme-preview — loaded ONLY inside the System Settings → POS
 * Theme iframe. Theme/mode/direction come from the query string and then
 * live via postMessage({ source: 'pos-theme-preview', theme, mode, dir })
 * from the parent (same origin), so switching never reloads the frame.
 */
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import {
  Warehouse, ShoppingCart, Search, ScanBarcode, Plus, Pencil, X,
  Settings, Maximize, Calculator, House, Trash2, FileText, LayoutGrid, User,
} from 'lucide-vue-next';
import '../pos/pos-themes.scss';
import {
  normalizePosTheme,
  normalizePosColorMode,
  posModeIsDark,
  posThemeClasses,
  onSystemColorSchemeChange,
  DEFAULT_POS_THEME,
  DEFAULT_POS_COLOR_MODE,
} from '../../lib/posTheme';

const MSG_SOURCE = 'pos-theme-preview';
const { t } = useI18n();
const route = useRoute();

const theme = ref(normalizePosTheme(route.query.theme) || DEFAULT_POS_THEME);
const mode = ref(normalizePosColorMode(route.query.mode) || DEFAULT_POS_COLOR_MODE);
const dir = ref(route.query.dir === 'rtl' ? 'rtl' : 'ltr');
const systemTick = ref(0);

const rootClasses = computed(() => {
  systemTick.value; // re-evaluate `auto` on OS scheme changes
  return posThemeClasses(theme.value, posModeIsDark(mode.value));
});

// Theme rules key some RTL tweaks off html[dir]; this document is the
// iframe's own, so it never affects the settings page around it.
watch(dir, d => document.documentElement.setAttribute('dir', d), { immediate: true });

function onMessage(e) {
  if (e.origin !== window.location.origin) return;
  const d = e.data || {};
  if (d.source !== MSG_SOURCE) return;
  if (d.theme) theme.value = normalizePosTheme(d.theme) || theme.value;
  if (d.mode) mode.value = normalizePosColorMode(d.mode) || mode.value;
  if (d.dir) dir.value = d.dir === 'rtl' ? 'rtl' : 'ltr';
}

let unsubScheme = null;
onMounted(() => {
  window.addEventListener('message', onMessage);
  unsubScheme = onSystemColorSchemeChange(() => { systemTick.value++; });
  if (window.parent && window.parent !== window) {
    window.parent.postMessage({ source: MSG_SOURCE, ready: true }, window.location.origin);
  }
});
onBeforeUnmount(() => {
  window.removeEventListener('message', onMessage);
  if (unsubScheme) unsubScheme();
});

// ---- Sample data (fixed; nothing is loaded from the server) ----
// Real product photos from Unsplash (the same CDN the grocery demo seeder
// uses), as square crops like typical product shots. Each sits on top of a
// drawn placeholder layer, so the card still looks right offline or when
// images.unsplash.com is blocked.
// Always wrap in cssUrl(): encodeURIComponent leaves ( ) raw, which breaks an unquoted url().
const cssUrl = u => `url("${u}")`;
const unsplash = id => `https://images.unsplash.com/photo-${id}?w=400&h=400&q=80&auto=format&fit=crop`;
const placeholder = (hue, shape) => 'data:image/svg+xml;utf8,' + encodeURIComponent(
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" fill="hsl(${hue},70%,88%)"/>`
  + (shape === 'circle'
    ? `<circle cx="60" cy="62" r="30" fill="hsl(${hue},60%,52%)"/>`
    : `<rect x="34" y="30" width="52" height="64" rx="10" fill="hsl(${hue},60%,52%)"/>`)
  + `<rect x="34" y="30" width="52" height="12" rx="6" fill="hsl(${hue},60%,40%)" opacity=".35"/></svg>`,
);
const imageLayers = item => cssUrl(item.image) + ', ' + cssUrl(item.fallback);

const products = [
  { name: 'Caffè Latte', code: 'CL-01', price: 4.5, stock: 42, image: unsplash('1541167760496-1628856ab772'), fallback: placeholder(28, 'rect') },
  { name: 'Red Apple', code: 'AP-12', price: 0.9, stock: 130, image: unsplash('1568702846914-96b305d2aaeb'), fallback: placeholder(0, 'circle'), discount: 10 },
  { name: 'Mineral Water', code: 'MW-07', price: 1.2, stock: 88, image: unsplash('1548839140-29a749e1cf4d'), fallback: placeholder(195, 'rect') },
  { name: 'Black Forest Cake', code: 'BF-33', price: 5.75, stock: 12, image: unsplash('1582716401301-b2407dc7563d'), fallback: placeholder(340, 'rect') },
  { name: 'Berry Smoothie', code: 'BS-04', price: 4.1, stock: 26, image: unsplash('1553530666-ba11a7da3888'), fallback: placeholder(320, 'rect') },
  { name: 'Orange Juice', code: 'OJ-10', price: 3.6, stock: 37, image: unsplash('1600271886742-f049cd451bba'), fallback: placeholder(35, 'rect'), discount: 15 },
  { name: 'Butter Croissant', code: 'CR-02', price: 2.2, stock: 20, image: unsplash('1555507036-ab1f4038808a'), fallback: placeholder(40, 'circle') },
  { name: 'Rustic Sourdough', code: 'RS-21', price: 4.9, stock: 14, image: unsplash('1549931319-a545dcf3bc73'), fallback: placeholder(30, 'rect') },
];
const cart = [
  { ...products[0], qty: 2 },
  { ...products[1], price: 0.81, qty: 6 },
  { ...products[6], qty: 1 },
];
const total = cart.reduce((s, i) => s + i.price * i.qty, 0);

const charges = computed(() => [t('pos.Tax') + ' %', t('Discount'), t('Shipping')]);
const headerIcons = [Calculator, Maximize, Settings];
const payActions = computed(() => [
  { label: t('pos.Home'), icon: House },
  { label: t('Reset'), icon: Trash2 },
  { label: t('pos.Drafts_list'), icon: FileText },
]);
const tabs = computed(() => [
  { label: t('Products'), icon: LayoutGrid },
  { label: t('pos.Cart'), icon: ShoppingCart },
  { label: t('Customer'), icon: User },
  { label: t('Settings'), icon: Settings },
]);

const money = v => '$' + Number(v).toFixed(2);
const initials = name => (name || 'P').split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('');
const swatch = step => `repeating-linear-gradient(135deg, var(--pos-accent-soft-2), var(--pos-accent-soft-2) ${step}px, var(--pos-accent-soft) ${step}px, var(--pos-accent-soft) ${step * 2}px)`;
</script>

<style scoped>
/* Base shell — mirrors PosPage's layout rules with LOW specificity so the
   theme stylesheet (pos-themes.scss) always wins, exactly as on the POS. */
.ptp-root { min-height: 100vh; }
.ptp-shell {
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
  font-size: 13px;
  -webkit-font-smoothing: antialiased;
}
.ptp-shell button { cursor: default; font-family: inherit; }
.ptp-ico { width: 16px; height: 16px; flex-shrink: 0; }

.ptp-header { display: flex; align-items: center; gap: 8px; padding: 0 16px; height: 55px; flex-shrink: 0; }
.ptp-brand {
  width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center;
  font-weight: 700; font-family: 'JetBrains Mono', monospace;
}
.ptp-register { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; white-space: nowrap; }
.ptp-pill { padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
.ptp-trigger { display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 10px 0 6px; border-radius: 10px; min-width: 0; }
.ptp-trigger-body { display: flex; flex-direction: column; line-height: 1.15; min-width: 0; }
.ptp-eyebrow { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; }
.ptp-trigger-label { font-size: 12.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ptp-avatar { width: 28px; height: 28px; border-radius: 99px; display: grid; place-items: center; font-weight: 700; font-size: 12px; }
.ptp-icon-btn { width: 32px; height: 32px; border-radius: 8px; display: grid; place-items: center; padding: 0; }

.ptp-main { flex: 1 1 auto; display: flex; flex-direction: row; min-height: 0; overflow: hidden; }
.ptp-cart { flex: 0 0 50%; width: 50%; display: grid; grid-template-rows: 1fr auto; min-height: 0; overflow: hidden; }
.ptp-cart-scroll { overflow-y: auto; padding: 8px; min-height: 0; }
.ptp-cart-head { display: flex; align-items: center; gap: 10px; padding: 4px 4px 10px; margin-bottom: 6px; }
.ptp-cart-head-icon { width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; }
.ptp-mobile-pay { display: none; }
.ptp-section { flex: 1 1 auto; min-width: 0; display: grid; grid-template-rows: auto 1fr; min-height: 0; overflow: hidden; }
.ptp-products-scroll { overflow-y: auto; padding: 6px; min-height: 0; }
.ptp-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); grid-auto-rows: max-content; gap: 5px; }

.ptp-paybar { display: flex; align-items: center; gap: 8px; padding: 8px 16px 8px 20px; flex-shrink: 0; }
.ptp-online { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; }
.ptp-dot { width: 6px; height: 6px; border-radius: 99px; }
.ptp-tabbar { display: none; }

/* Tablet + phone: the POS's stacked layout with a bottom tab bar. */
@media (max-width: 1024px) {
  .ptp-shell { height: auto; min-height: 100vh; overflow: visible; padding-bottom: 60px; }
  .ptp-header { flex-wrap: wrap; height: auto; padding: 8px 12px; row-gap: 8px; }
  .ptp-main { flex-direction: column; flex: 0 0 auto; overflow: visible; }
  .ptp-section { order: 1; overflow: visible; }
  .ptp-cart { order: 2; flex: 0 0 auto; width: 100%; border-right: 0 !important; border-top: 1px solid var(--pos-border); overflow: visible; }
  .ptp-products-scroll { overflow: visible; }
  .ptp-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
  .ptp-mobile-pay {
    display: flex; justify-content: space-between; width: 100%; margin-top: 10px;
    height: 46px; padding: 0 16px; align-items: center; border: 0; border-radius: 10px;
    font-size: 15px; font-weight: 600;
  }
  .ptp-paybar { display: none; }
  .ptp-tabbar {
    display: flex; position: fixed; inset-inline: 0; bottom: 0; height: 58px; z-index: 10;
    justify-content: space-around; align-items: center;
  }
  .ptp-tab { display: flex; flex-direction: column; align-items: center; gap: 3px; font-size: 10.5px; font-weight: 600; }
}
@media (max-width: 480px) {
  .ptp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .ptp-register { display: none; }
}
</style>
