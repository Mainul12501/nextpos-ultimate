{{--
  Login CAPTCHA (System Settings > Security).
  $captcha     = App\Support\Security\Captcha::widget()
  $groupClass  = wrapper class of a form row on the host page
  $labelClass  = class of a label on the host page
  $shellClass  = wrapper class of an input on the host page ('' = none)
  $inputClass  = class of a text input on the host page
  $withIcon    = render the login page's leading .input-icon
--}}
@php
  $groupClass = $groupClass ?? 'form-group';
  $labelClass = $labelClass ?? '';
  $shellClass = $shellClass ?? '';
  $inputClass = $inputClass ?? '';
  $withIcon = $withIcon ?? false;
@endphp

@if ($captcha['provider'] === 'builtin')
<div class="{{ $groupClass }} login-captcha">
  <label class="{{ $labelClass }}" for="captcha">{{ __('Security check') }}</label>
  @if (($captcha['mode'] ?? 'image') === 'image')
  <div class="login-captcha-challenge">
    <img src="{{ $captcha['image_url'] }}" alt="{{ __('Security code') }}" width="170" height="56" class="login-captcha-img" id="login_captcha_img">
    <button type="button" class="login-captcha-refresh" id="login_captcha_refresh" title="{{ __('Show a new code') }}" aria-label="{{ __('Show a new code') }}">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><polyline points="21 3 21 9 15 9"/></svg>
    </button>
  </div>
  @else
  <div class="login-captcha-question">{{ $captcha['question'] }}</div>
  @endif
  @if ($shellClass !== '')<div class="{{ $shellClass }}">@endif
    @if ($withIcon)
    <span class="input-icon">
      <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    </span>
    @endif
    <input id="captcha" type="text" name="captcha" class="{{ $inputClass }}" required autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="10"
      placeholder="{{ ($captcha['mode'] ?? 'image') === 'image' ? __('Type the characters shown') : __('Your answer') }}">
  @if ($shellClass !== '')</div>@endif
</div>
@else
<div class="login-captcha login-captcha-external">
  <div class="{{ $captcha['class'] }}" data-sitekey="{{ $captcha['site_key'] }}"></div>
</div>
<script src="{{ $captcha['script'] }}" async defer></script>
@endif

<style>
  .login-captcha-challenge { display: flex; align-items: center; gap: 10px; }
  .login-captcha-img { border-radius: 8px; border: 1px solid rgba(0, 0, 0, .12); background: #f4f5f7; display: block; }
  .login-captcha-refresh { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; border: 1px solid rgba(0, 0, 0, .15); background: transparent; color: inherit; cursor: pointer; }
  .login-captcha-refresh:hover { background: rgba(0, 0, 0, .05); }
  .login-captcha-question { font-weight: 600; font-size: 18px; letter-spacing: 1px; }
  .login-captcha-external { display: flex; justify-content: center; max-width: 100%; margin: 4px 0; }
</style>
<script>
  (function () {
    var btn = document.getElementById('login_captcha_refresh');
    var img = document.getElementById('login_captcha_img');
    if (!btn || !img) return;
    btn.addEventListener('click', function () {
      img.src = img.src.split('?')[0] + '?t=' + Date.now();
      var input = document.getElementById('captcha');
      if (input) { input.value = ''; input.focus(); }
    });
  })();
</script>
