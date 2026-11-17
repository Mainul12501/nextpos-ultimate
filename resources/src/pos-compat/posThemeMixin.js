/**
 * POS Theme Mixin
 * --------------------------------------------------------------------
 * Resolves the active POS theme (layout + colour mode) and exposes the
 * root classes for PosPage. Presentation only: it reads `pos_settings`
 * and the route query, never touches sale state, so switching a theme
 * keeps the cart, drafts and offline queue untouched (no remount).
 *
 * The last store theme seen is cached per device so an offline refresh —
 * or the moment before pos_settings arrives — doesn't flash Classic Light.
 * See lib/posTheme.js for the resolution order.
 */
import {
  POS_THEMES,
  POS_COLOR_MODES,
  resolvePosTheme,
  isDeviceOverrideAllowed,
  getDevicePosTheme,
  setDevicePosTheme,
  clearDevicePosTheme,
  posModeIsDark,
  posThemeClasses,
  onSystemColorSchemeChange,
} from "../lib/posTheme";

const LAST_STORE_THEME_KEY = "pos_ui_theme_store_last";

function readLastStoreTheme() {
  try {
    const v = JSON.parse(localStorage.getItem(LAST_STORE_THEME_KEY) || "null");
    return v && typeof v === "object" ? v : null;
  } catch (e) {
    return null;
  }
}

export default {
  data() {
    return {
      posThemeList: POS_THEMES,
      posColorModeList: POS_COLOR_MODES,
      posThemeDevice: getDevicePosTheme(),
      posThemeLastStore: readLastStoreTheme(),
      // Bumped on OS light/dark changes so `auto` re-evaluates.
      posThemeSystemTick: 0,
    };
  },

  computed: {
    // pos_settings row, or the last-known store theme until it has loaded.
    posThemeSource() {
      const ps = this.pos_settings || {};
      if (Object.prototype.hasOwnProperty.call(ps, "pos_theme")) return ps;
      return this.posThemeLastStore || ps;
    },
    posThemeState() {
      return resolvePosTheme(this.posThemeSource, {
        query: this.$route ? this.$route.query : null,
        device: this.posThemeDevice,
      });
    },
    posThemeDark() {
      // eslint-disable-next-line no-unused-expressions
      this.posThemeSystemTick;
      return posModeIsDark(this.posThemeState.mode);
    },
    posThemeRootClasses() {
      return posThemeClasses(this.posThemeState.theme, this.posThemeDark);
    },
    posThemeOverrideAllowed() {
      return isDeviceOverrideAllowed(this.posThemeSource);
    },
    posThemeUsingDevice() {
      return this.posThemeState.source === "device";
    },
    // Product-grid images: the "show product images" setting, or always in
    // Modern Cards (an image-led layout). Display only — the cart thumbnails
    // keep following the setting.
    posCardImages() {
      const ps = this.pos_settings || {};
      return !!ps.show_product_images || this.posThemeState.theme === "cards";
    },
  },

  watch: {
    "pos_settings.pos_theme": "rememberPosStoreTheme",
    "pos_settings.pos_color_mode": "rememberPosStoreTheme",
    "pos_settings.pos_theme_device_override": "rememberPosStoreTheme",
  },

  mounted() {
    this._posThemeUnsub = onSystemColorSchemeChange(() => {
      this.posThemeSystemTick++;
    });
  },

  beforeUnmount() {
    if (this._posThemeUnsub) this._posThemeUnsub();
  },

  methods: {
    rememberPosStoreTheme() {
      const ps = this.pos_settings || {};
      if (!Object.prototype.hasOwnProperty.call(ps, "pos_theme")) return;
      const snap = {
        pos_theme: ps.pos_theme || null,
        pos_color_mode: ps.pos_color_mode || null,
        pos_theme_device_override: !!ps.pos_theme_device_override,
      };
      this.posThemeLastStore = snap;
      try {
        localStorage.setItem(LAST_STORE_THEME_KEY, JSON.stringify(snap));
      } catch (e) { /* storage blocked — fine, only avoids a flash */ }
    },
    // Gear menu: this till's own pick (only offered when the store allows it).
    setPosDeviceTheme(patch) {
      // Keep only what this till actually picked: choosing a layout must not
      // pin today's colour mode (or a ?preview_mode) and vice versa — the
      // unpicked half keeps following the store default.
      const own = this.posThemeDevice || {};
      setDevicePosTheme({
        theme: (patch && patch.theme) || own.theme || null,
        mode: (patch && patch.mode) || own.mode || null,
      });
      this.posThemeDevice = getDevicePosTheme();
    },
    resetPosDeviceTheme() {
      clearDevicePosTheme();
      this.posThemeDevice = getDevicePosTheme();
    },
  },
};
