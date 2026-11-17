{{--
  Two-factor step of the staff login, rendered inside auth/login.blade.php.
  $twoFactor['mode']:
    challenge — enter an authenticator code or a recovery code
    setup     — 2FA is required but not set up yet: scan, then confirm a code
    codes     — the recovery codes just created by setup (shown once)
  The code forms reuse #login_form / #login_submit_btn so the login page's
  script refreshes the CSRF token and shows the busy state on submit.
--}}
<style>
  .tf-code-input { font-size: 1.35rem; letter-spacing: 0.35em; text-align: center; font-variant-numeric: tabular-nums; }
  .tf-code-input.is-recovery { font-size: 1rem; letter-spacing: 0.08em; }
  .tf-link { background: none; border: 0; padding: 0; font: inherit; font-size: 0.875rem; font-weight: 500; color: var(--primary); cursor: pointer; text-decoration: none; }
  .tf-link:hover { color: var(--primary-hover); }
  .tf-links { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
  .tf-steps { display: flex; flex-direction: column; gap: 1rem; counter-reset: tf-step; }
  .tf-step { display: flex; gap: 0.75rem; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5; }
  .tf-step::before {
    counter-increment: tf-step; content: counter(tf-step);
    flex: 0 0 1.6rem; height: 1.6rem; border-radius: var(--radius-full);
    background: var(--primary-light); color: var(--primary-hover);
    font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; justify-content: center;
  }
  .tf-qr {
    width: 196px; height: 196px; margin: 0.25rem auto 0; padding: 10px; background: #fff;
    border: 1px solid var(--border); border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
  }
  .tf-qr img, .tf-qr canvas { width: 174px !important; height: 174px !important; }
  .tf-qr-fallback { font-size: 0.75rem; color: var(--text-muted); text-align: center; }
  .tf-secret {
    display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
    background: var(--bg-input); border: 1px dashed var(--border); border-radius: var(--radius-sm);
    padding: 0.55rem 0.75rem; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.85rem; color: var(--text); word-break: break-all;
  }
  .tf-codes {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.5rem;
    background: var(--bg-input); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.95rem; text-align: center;
  }
  .tf-actions { display: flex; gap: 0.75rem; }
  .tf-actions .tf-secondary {
    flex: 1; padding: 0.7rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border);
    background: var(--surface); color: var(--text); font: inherit; font-size: 0.875rem; font-weight: 600; cursor: pointer;
  }
  .tf-actions .tf-secondary:hover { border-color: var(--primary); color: var(--primary); }
  a.auth-btn { text-decoration: none; }
  .tf-grace {
    margin-top: 1rem; padding: 0.75rem 1rem; border-radius: var(--radius-sm);
    background: var(--primary-light); color: var(--text-secondary); font-size: 0.85rem;
    display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;
  }
</style>

@if ($twoFactor['mode'] === 'codes')
  <div class="auth-form">
    <div class="tf-codes" id="tf_codes">
      @foreach ($twoFactor['codes'] as $code)
        <span>{{ $code }}</span>
      @endforeach
    </div>
    <div class="tf-actions">
      <button type="button" class="tf-secondary" id="tf_copy_codes">{{ tdb('Two_factor_copy', 'Copy') }}</button>
      <button type="button" class="tf-secondary" id="tf_download_codes">{{ tdb('Two_factor_download', 'Download') }}</button>
    </div>
    <a class="auth-btn" href="{{ $twoFactor['continue'] }}">
      <span class="btn-text">{{ tdb('Two_factor_codes_saved', 'I saved them — continue') }}</span>
    </a>
  </div>

  <script>
    (function () {
      var codes = @json($twoFactor['codes']);
      var text = codes.join('\n');
      var copyBtn = document.getElementById('tf_copy_codes');
      copyBtn.addEventListener('click', function () {
        var done = function () { copyBtn.textContent = @json(tdb('Two_factor_copied', 'Copied')); };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(done).catch(function () {});
        } else {
          var ta = document.createElement('textarea');
          ta.value = text; document.body.appendChild(ta); ta.select();
          try { document.execCommand('copy'); done(); } catch (e) {}
          document.body.removeChild(ta);
        }
      });
      document.getElementById('tf_download_codes').addEventListener('click', function () {
        var blob = new Blob([@json(($app_settings->app_name ?? 'Stocky').' recovery codes'), '\n\n', text, '\n'], { type: 'text/plain' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'recovery-codes.txt';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
      });
    })();
  </script>
@else
  <form id="login_form" method="POST" action="{{ url('login/two-factor') }}" class="auth-form" autocomplete="off">
    @csrf

    @if ($twoFactor['mode'] === 'setup')
      <div class="tf-steps">
        <div class="tf-step">{{ tdb('Two_factor_step_install', 'Install an authenticator app on your phone, such as Google Authenticator, Microsoft Authenticator or Authy.') }}</div>
        <div class="tf-step">
          <div style="flex: 1; display: flex; flex-direction: column; gap: 0.6rem;">
            {{ tdb('Two_factor_step_scan', 'Scan this QR code with the app, or type the key below.') }}
            <div class="tf-qr" id="tf_qr" data-uri="{{ $twoFactor['uri'] }}">
              <span class="tf-qr-fallback">{{ tdb('Two_factor_qr_loading', 'Loading QR code…') }}</span>
            </div>
            <div class="tf-secret">
              <span id="tf_secret">{{ trim(chunk_split($twoFactor['secret'], 4, ' ')) }}</span>
              <button type="button" class="tf-link" id="tf_copy_secret">{{ tdb('Two_factor_copy', 'Copy') }}</button>
            </div>
          </div>
        </div>
        <div class="tf-step">{{ tdb('Two_factor_step_confirm', 'Enter the 6-digit code the app shows to finish.') }}</div>
      </div>
    @endif

    <div class="form-group">
      <label class="form-label" for="code" id="tf_code_label">{{ tdb('Two_factor_code_label', 'Authentication code') }}</label>
      <div class="input-wrapper">
        <span class="input-icon">
          <svg viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
        </span>
        <input id="code" class="form-input tf-code-input" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="32" placeholder="000000" required autofocus />
      </div>
    </div>

    @if ($twoFactor['mode'] === 'challenge' && ($twoFactor['trustDays'] ?? 0) > 0)
      <label class="remember-check">
        <input type="checkbox" name="trust_device" value="1">
        {{ tdb('Two_factor_trust_device', 'Don\'t ask again on this device for :days days', ['days' => $twoFactor['trustDays']]) }}
      </label>
    @endif

    <button type="submit" class="auth-btn" id="login_submit_btn">
      <span class="btn-text">{{ $twoFactor['mode'] === 'setup' ? tdb('Two_factor_enable', 'Turn on and sign in') : tdb('Two_factor_verify', 'Verify') }}</span>
      <svg class="btn-arrow btn-text" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      <span class="btn-loading"><span class="spinner"></span> {{ tdb('Two_factor_verifying', 'Verifying...') }}</span>
    </button>

    <div class="tf-links">
      @if ($twoFactor['mode'] === 'challenge')
        <button type="button" class="tf-link" id="tf_toggle_recovery"
          data-recovery="{{ tdb('Two_factor_use_recovery', 'Use a recovery code') }}"
          data-app="{{ tdb('Two_factor_use_app', 'Use the authenticator app') }}">{{ tdb('Two_factor_use_recovery', 'Use a recovery code') }}</button>
      @else
        <span></span>
      @endif
      <a class="forgot-link" href="{{ route('login') }}">{{ tdb('Two_factor_back', 'Back to sign in') }}</a>
    </div>
  </form>

  {{-- Grace period (System Settings > Security): setup can wait until the deadline. --}}
  @if ($twoFactor['mode'] === 'setup' && ! empty($twoFactor['graceUntil']))
    <form method="POST" action="{{ url('login/two-factor/skip') }}" class="tf-grace">
      @csrf
      <span>{{ tdb('Two_factor_grace_notice', 'Required from :date. You can set it up later until then.', ['date' => \Carbon\Carbon::createFromTimestamp($twoFactor['graceUntil'])->translatedFormat('j M Y')]) }}</span>
      <button type="submit" class="tf-link">{{ tdb('Two_factor_remind_later', 'Remind me later') }}</button>
    </form>
  @endif

  <script>
    (function () {
      var input = document.getElementById('code');

      var toggle = document.getElementById('tf_toggle_recovery');
      if (toggle) {
        var appLabel = @json(tdb('Two_factor_code_label', 'Authentication code'));
        var recoveryLabel = @json(tdb('Two_factor_recovery_label', 'Recovery code'));
        toggle.addEventListener('click', function () {
          var recovery = !input.classList.contains('is-recovery');
          input.classList.toggle('is-recovery', recovery);
          input.setAttribute('inputmode', recovery ? 'text' : 'numeric');
          input.setAttribute('autocomplete', recovery ? 'off' : 'one-time-code');
          input.placeholder = recovery ? 'xxxxx-xxxxx' : '000000';
          document.getElementById('tf_code_label').textContent = recovery ? recoveryLabel : appLabel;
          toggle.textContent = recovery ? toggle.dataset.app : toggle.dataset.recovery;
          input.value = '';
          input.focus();
        });
      }

      var copySecret = document.getElementById('tf_copy_secret');
      if (copySecret) {
        copySecret.addEventListener('click', function () {
          var key = document.getElementById('tf_secret').textContent.replace(/\s+/g, '');
          if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(key).then(function () {
              copySecret.textContent = @json(tdb('Two_factor_copied', 'Copied'));
            }).catch(function () {});
          }
        });
      }

      // QR code: the app's own copy of qrcodejs first, then the CDN. The key
      // under it always works if neither loads.
      var qr = document.getElementById('tf_qr');
      if (!qr) return;
      var sources = ['/vendor/qrcode/qrcode.min.js?v=full-3', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'];
      function render() {
        qr.innerHTML = '';
        try {
          new window.QRCode(qr, { text: qr.dataset.uri, width: 174, height: 174, correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.M : 0 });
        } catch (e) {
          qr.innerHTML = '<span class="tf-qr-fallback">' + @json(tdb('Two_factor_qr_failed', 'QR code unavailable — type the key instead.')) + '</span>';
        }
      }
      function load(i) {
        if (window.QRCode) return render();
        if (i >= sources.length) {
          qr.innerHTML = '<span class="tf-qr-fallback">' + @json(tdb('Two_factor_qr_failed', 'QR code unavailable — type the key instead.')) + '</span>';
          return;
        }
        var el = document.createElement('script');
        el.src = sources[i];
        el.onload = function () { window.QRCode ? render() : load(i + 1); };
        el.onerror = function () { load(i + 1); };
        document.head.appendChild(el);
      }
      load(0);
    })();
  </script>
@endif
