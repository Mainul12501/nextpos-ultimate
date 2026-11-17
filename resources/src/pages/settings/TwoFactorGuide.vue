<template>
  <div class="guide">
    <p class="lead">
      {{ tr('Tfa_Guide_Lead', 'Two-factor authentication (2FA) adds a second step to sign-in: after the password, staff type a 6-digit code from an authenticator app on their phone. A stolen or guessed password is no longer enough to get in.') }}
    </p>

    <!-- Live state of this install, so the guide answers "how is it set up here?" -->
    <div class="state-box">
      <div class="state-row">
        <span class="muted">{{ tr('Tfa_Guide_State_Required', 'Required') }}</span>
        <a-tag :color="required ? 'processing' : 'default'" style="margin: 0">
          {{ required
            ? (roleNames.length ? roleNames.join(', ') : tr('Sec_2fa_All_Users', 'All users'))
            : tr('Tfa_Guide_State_Optional', 'No — optional for everyone') }}
        </a-tag>
      </div>
      <div class="state-row">
        <span class="muted">{{ tr('Tfa_Guide_State_Trust', 'Trusted devices') }}</span>
        <span>{{ trustDays > 0 ? tr('Sec_2fa_Trust_Days', ':days days').replace(':days', trustDays) : tr('Sec_2fa_Trust_Off', 'Always ask for a code') }}</span>
      </div>
      <div v-if="stats.migrated" class="state-row">
        <span class="muted">{{ tr('Tfa_Guide_State_Users', 'Users with 2FA on') }}</span>
        <span>{{ stats.enabled }} / {{ stats.total }}</span>
      </div>
    </div>

    <!-- ---------------------------------------------- staff -->
    <h3 class="heading">{{ tr('Tfa_Guide_Staff_Title', 'For each staff member') }}</h3>

    <section class="step">
      <h4><span class="num">1</span> {{ tr('Tfa_Guide_App_Title', 'Install an authenticator app') }}</h4>
      <p>{{ tr('Tfa_Guide_App_Text', 'Any app that supports standard 6-digit codes (TOTP) works. They are free and work offline:') }}</p>
      <ul class="plain">
        <li>Google Authenticator</li>
        <li>Microsoft Authenticator</li>
        <li>Authy, 1Password, Bitwarden</li>
      </ul>
    </section>

    <section class="step">
      <h4><span class="num">2</span> {{ tr('Tfa_Guide_Enable_Title', 'Turn it on from the profile') }}</h4>
      <ol class="plain">
        <li>{{ tr('Tfa_Guide_Enable_1', 'Open Profile (avatar menu at the top right) and find the "Two-factor authentication" card.') }}</li>
        <li>{{ tr('Tfa_Guide_Enable_2', 'Click "Turn on" and confirm with the current password.') }}</li>
        <li>{{ tr('Tfa_Guide_Enable_3', 'Scan the QR code with the app, or type the key shown under it.') }}</li>
        <li>{{ tr('Tfa_Guide_Enable_4', 'Enter the 6-digit code the app shows to finish.') }}</li>
      </ol>
      <a-button size="small" style="margin-top: 8px" @click="go('/profile')">
        <UserOutlined /> {{ tr('Tfa_Guide_Open_Profile', 'Open my profile') }}
      </a-button>
    </section>

    <section class="step">
      <h4><span class="num">3</span> {{ tr('Tfa_Guide_Codes_Title', 'Save the recovery codes') }}</h4>
      <p>{{ tr('Tfa_Guide_Codes_Text', 'Right after setup, 8 recovery codes are shown once. Each one replaces a phone code a single time — for a lost, broken or reset phone. Download or copy them and keep them somewhere safe, away from the phone.') }}</p>
      <p class="note">{{ tr('Tfa_Guide_Codes_Note', 'Running low? "New recovery codes" on the profile replaces the whole set; the old ones stop working.') }}</p>
    </section>

    <section class="step">
      <h4><span class="num">4</span> {{ tr('Tfa_Guide_Signin_Title', 'Signing in') }}</h4>
      <p>{{ tr('Tfa_Guide_Signin_Text', 'After the email and password, the login page asks for the current code from the app. Codes change every 30 seconds and each one works once.') }}</p>
      <ul class="plain">
        <li v-if="trustDays > 0">{{ tr('Tfa_Guide_Signin_Trust', 'Ticking "Don’t ask again on this device" skips the code on that browser for :days days.').replace(':days', trustDays) }}</li>
        <li>{{ tr('Tfa_Guide_Signin_Recovery', 'No phone at hand? Click "Use a recovery code" and type one of the saved codes.') }}</li>
      </ul>
    </section>

    <!-- ---------------------------------------------- admins -->
    <h3 class="heading">{{ tr('Tfa_Guide_Admin_Title', 'For administrators') }}</h3>

    <section class="step">
      <h4><span class="num">5</span> {{ tr('Tfa_Guide_Require_Title', 'Make it mandatory') }}</h4>
      <p>{{ tr('Tfa_Guide_Require_Text', 'Turn on "Require two-factor authentication" on this card, then pick the roles that must use it — or leave the list empty for every user. Click Submit to save.') }}</p>
      <p>{{ tr('Tfa_Guide_Require_Next', 'Nobody is signed out. Users who don’t have 2FA yet are walked through setup on the login page at their next sign-in, before they get in, and see their recovery codes there.') }}</p>
      <p class="note">{{ tr('Tfa_Guide_Require_Note', 'Users it is required for cannot turn it off from their profile.') }}</p>
    </section>

    <section class="step">
      <h4><span class="num">6</span> {{ tr('Tfa_Guide_Rollout_Title', 'Roll it out to every user') }}</h4>
      <ol class="plain">
        <li>{{ tr('Tfa_Guide_Rollout_1', 'Turn it on for your own account first (Profile), so you know the steps.') }}</li>
        <li>{{ tr('Tfa_Guide_Rollout_2', 'Tell your team which app to install and that setup takes a minute at sign-in.') }}</li>
        <li>{{ tr('Tfa_Guide_Rollout_3', 'Turn on "Require two-factor authentication", leave "Required for" empty (= every user), and optionally pick a grace period. Click Submit.') }}</li>
        <li>{{ tr('Tfa_Guide_Rollout_4', 'Click "Sign out users without 2FA" so people already signed in set it up now instead of at some later sign-in. You stay signed in.') }}</li>
        <li>{{ tr('Tfa_Guide_Rollout_5', 'Follow progress with the counter on this card, or the "Needs setup" filter on the Users page.') }}</li>
      </ol>
      <p class="note">{{ tr('Tfa_Guide_Rollout_Note', 'With a grace period, the setup screen shows “Remind me later” until the deadline; after it, setup is required to sign in.') }}</p>
    </section>

    <section class="step">
      <h4><span class="num">7</span> {{ tr('Tfa_Guide_Trust_Title', 'Trusted devices') }}</h4>
      <p>{{ tr('Tfa_Guide_Trust_Text', '"Remember trusted devices" sets how long a browser can skip the code after a successful sign-in. Choose "Always ask for a code" for shared or public computers such as a shop-floor POS.') }}</p>
      <p class="note">{{ tr('Tfa_Guide_Trust_Note', 'Lowering the number shortens devices already trusted; turning 2FA off or resetting it forgets them all.') }}</p>
    </section>

    <section class="step">
      <h4><span class="num">8</span> {{ tr('Tfa_Guide_Lost_Title', 'Lost phone and no recovery codes') }}</h4>
      <p>{{ tr('Tfa_Guide_Lost_Text', 'On the Users page, users with 2FA show "On" in the 2FA column. The shield button in Actions resets it: the user signs in with just the password and sets up the app again (or is asked to, if it is required).') }}</p>
      <a-alert
        type="warning" show-icon class="inline-alert"
        :message="tr('Tfa_Guide_Lost_Warn', 'Confirm who is asking before resetting — a reset removes the protection 2FA gives that account.')"
      />
      <a-button size="small" style="margin-top: 10px" @click="go('/users')">
        <TeamOutlined /> {{ tr('Tfa_Guide_Open_Users', 'Open Users') }}
      </a-button>
    </section>

    <section class="step">
      <h4><span class="num">9</span> {{ tr('Tfa_Guide_Mobile_Title', 'Mobile app and API') }}</h4>
      <p>{{ tr('Tfa_Guide_Mobile_Text', 'The mobile app and the API token endpoint ask for the same code. If 2FA is required for a user who hasn’t set it up yet, they must sign in on the web once to do so.') }}</p>
    </section>

    <section class="step">
      <h4><span class="num">10</span> {{ tr('Tfa_Guide_Customers_Title', 'Customer accounts (store and client portal)') }}</h4>
      <p>{{ tr('Tfa_Guide_Customers_Text', 'Customers can protect their own accounts too. It is optional for each customer and never forced: switch it on under Store Settings → Registration Access Control (online store) and Client Portal → Portal Settings (client portal).') }}</p>
      <ul class="plain">
        <li>{{ tr('Tfa_Guide_Customers_1', 'Customers turn it on from My Account (store) or Profile (portal).') }}</li>
        <li>{{ tr('Tfa_Guide_Customers_2', 'They choose a code sent by email — easiest, no app needed — or an authenticator app with recovery codes.') }}</li>
        <li>{{ tr('Tfa_Guide_Customers_3', 'Emailed codes need working mail settings (Settings → Mail Settings). They expire after 10 minutes.') }}</li>
        <li>{{ tr('Tfa_Guide_Customers_4', 'Switching the option off later stops new setups; customers who already use it keep being asked and can turn it off themselves.') }}</li>
      </ul>
    </section>

    <!-- ---------------------------------------------- reference -->
    <h3 class="heading">{{ tr('Tfa_Guide_Protect_Title', 'Built-in protection') }}</h3>
    <ul class="plain">
      <li>{{ tr('Tfa_Guide_Protect_1', '5 wrong codes block code entry for that user for 15 minutes, whatever the lockout settings above.') }}</li>
      <li>{{ tr('Tfa_Guide_Protect_2', 'Each code and each recovery code works only once.') }}</li>
      <li>{{ tr('Tfa_Guide_Protect_3', 'With "Record sign-in activity" on, wrong codes appear in the Sign-in activity list as "Wrong 2FA code".') }}</li>
    </ul>

    <h3 class="heading">{{ tr('Tfa_Guide_Trouble_Title', 'Troubleshooting') }}</h3>
    <a-collapse :bordered="false" ghost class="faq">
      <a-collapse-panel key="clock" :header="tr('Tfa_Guide_Faq_Clock_Q', '“That code is not valid” with a fresh code')">
        <p>{{ tr('Tfa_Guide_Faq_Clock_A', 'The phone clock is probably off. Codes depend on the time: set the phone’s date and time to automatic (network time), then try the next code.') }}</p>
      </a-collapse-panel>
      <a-collapse-panel key="newphone" :header="tr('Tfa_Guide_Faq_Phone_Q', 'Moving to a new phone')">
        <p>{{ tr('Tfa_Guide_Faq_Phone_A', 'Either use the authenticator app’s own transfer feature, or turn 2FA off on the profile and on again with the new phone (if it is required, ask an admin to reset it instead).') }}</p>
      </a-collapse-panel>
      <a-collapse-panel key="locked" :header="tr('Tfa_Guide_Faq_Locked_Q', '“Too many invalid codes”')">
        <p>{{ tr('Tfa_Guide_Faq_Locked_A', 'Wait for the time shown (up to 15 minutes), or have an admin reset the user’s 2FA.') }}</p>
      </a-collapse-panel>
      <a-collapse-panel key="key" :header="tr('Tfa_Guide_Faq_Key_Q', 'Nobody with 2FA can sign in after a server move')">
        <p>{{ tr('Tfa_Guide_Faq_Key_A', 'The 2FA secrets are encrypted with the APP_KEY in the .env file. Keep the same APP_KEY when moving or restoring the app; if it changed, an admin without 2FA must reset the affected users.') }}</p>
      </a-collapse-panel>
    </a-collapse>
  </div>
</template>

<script setup>
/**
 * Two-factor guide, shown in a right-hand drawer from System Settings >
 * Security. Read-only: the parent passes the current (unsaved) form state so
 * the summary at the top matches what the admin sees on the card.
 * Strings use Tfa_Guide_* keys with an English fallback.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { UserOutlined, TeamOutlined } from '@ant-design/icons-vue';

const props = defineProps({
  required: { type: Boolean, default: false },
  roleIds: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  trustDays: { type: Number, default: 0 },
  stats: { type: Object, default: () => ({ migrated: false, enabled: 0, total: 0 }) },
});
const emit = defineEmits(['close']);

const { t } = useI18n();
const tr = (key, fallback) => {
  const out = t(key);
  return out === key ? fallback : out;
};
const router = useRouter();

const roleNames = computed(() => props.roles
  .filter(r => props.roleIds.includes(r.id))
  .map(r => r.name));

function go(path) {
  emit('close');
  router.push(path);
}
</script>

<style scoped>
.lead {
  font-size: 13px;
  line-height: 1.6;
  opacity: 0.75;
  margin-bottom: 14px;
}
.state-box {
  border: 1px solid rgba(128, 128, 128, 0.2);
  border-radius: 10px;
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
}
.state-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.muted {
  opacity: 0.6;
}
.heading {
  margin: 22px 0 12px;
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.55;
}
.step {
  padding: 0 0 16px;
  margin-bottom: 16px;
  border-bottom: 1px solid rgba(128, 128, 128, 0.16);
}
.step:last-of-type {
  border-bottom: 0;
  margin-bottom: 0;
}
.step h4 {
  display: flex;
  align-items: center;
  gap: 9px;
  margin: 0 0 8px;
  font-size: 14px;
  font-weight: 600;
}
.num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: rgba(var(--app-primary-rgb, 109, 40, 217), 0.14);
  font-size: 12px;
  flex: none;
}
.step p {
  margin: 0 0 8px;
  font-size: 13px;
  line-height: 1.6;
}
.plain {
  margin: 0;
  padding-inline-start: 18px;
  font-size: 13px;
  line-height: 1.75;
}
.note {
  font-size: 12px;
  opacity: 0.65;
}
.inline-alert {
  font-size: 13px;
}
.faq :deep(.ant-collapse-header) {
  font-size: 13px;
  font-weight: 500;
  padding-inline: 0 !important;
}
.faq :deep(.ant-collapse-content-box) {
  padding-inline: 0 !important;
}
.faq p {
  margin: 0;
  font-size: 13px;
  line-height: 1.6;
  opacity: 0.8;
}
</style>
