{{-- My Account → Two-step verification (StoreTwoFactorController).
     Shown when Store Settings allows it, or when this account already has it
     (so it can always be turned off). States: recovery codes just created,
     on, setup in progress (email code / authenticator QR), off. --}}
@php
  $tfStatus = \App\Support\Security\CustomerTwoFactor::status('store', $me);
  $tfSetup = $tfStatus['enabled'] ? null : \App\Support\Security\CustomerTwoFactor::setupInProgress(request(), 'store', $me);
  $tfCodes = session('two_factor_codes');
@endphp

@if ($tfStatus['migrated'] && ($tfStatus['allowed'] || $tfStatus['enabled']))
<div class="card" id="two-factor">
  <div class="card-body">
    <h5 class="font-semibold mb-2 flex items-center gap-2">
      <x-store.icon name="shield-check" class="w-5 h-5 text-accent-500" />{{ __('messages.TwoFactorTitle') }}
      @if ($tfStatus['enabled'])
        <span class="chip chip-success">{{ __('messages.TwoFactorOn') }}</span>
      @endif
    </h5>

    @if (session('two_factor_status'))
      <div class="alert alert-success flex items-start gap-2 mb-4">
        <x-store.icon name="check-circle" class="w-5 h-5 mt-0.5 shrink-0" /><span>{{ session('two_factor_status') }}</span>
      </div>
    @endif
    @if (session('two_factor_notice'))
      <div class="alert alert-warning mb-4">{{ session('two_factor_notice') }}</div>
    @endif
    @error('two_factor')
      <div class="alert alert-danger mb-4">{{ $message }}</div>
    @enderror

    {{-- Recovery codes: shown once, right after turning on / regenerating. --}}
    @if (is_array($tfCodes) && count($tfCodes))
      <div class="alert alert-warning mb-4" x-data="{ copied: false }">
        <div class="font-semibold mb-1">{{ __('messages.TwoFactorCodesTitle') }}</div>
        <div class="text-sm mb-3">{{ __('messages.TwoFactorCodesHelp') }}</div>
        <div class="grid grid-cols-2 gap-2 font-mono text-center mb-3">
          @foreach ($tfCodes as $code)<span>{{ $code }}</span>@endforeach
        </div>
        <button type="button" class="btn btn-outline btn-sm"
                @click="navigator.clipboard && navigator.clipboard.writeText(@js(implode("\n", $tfCodes))).then(() => copied = true)">
          <x-store.icon name="copy" class="w-4 h-4" />
          <span x-show="!copied">{{ __('messages.TwoFactorCopy') }}</span>
          <span x-show="copied" x-cloak>{{ __('messages.TwoFactorCopied') }}</span>
        </button>
      </div>
    @endif

    @if ($tfStatus['enabled'])
      {{-- ---------------------------------------------- on --}}
      <p class="text-sm text-fg-muted mb-4">
        {{ $tfStatus['method'] === 'email' ? __('messages.TwoFactorOnEmailHelp') : __('messages.TwoFactorOnAppHelp') }}
        @if ($tfStatus['method'] === 'app')
          <br>{{ __('messages.TwoFactorCodesLeft', ['count' => $tfStatus['recovery_codes_left']]) }}
        @endif
      </p>

      <div x-data="{ action: null }">
        <div class="flex gap-2 flex-wrap" x-show="!action">
          @if ($tfStatus['method'] === 'app')
            <button type="button" class="btn btn-outline" @click="action = 'codes'">{{ __('messages.TwoFactorNewCodes') }}</button>
          @endif
          <button type="button" class="btn btn-outline" @click="action = 'disable'">{{ __('messages.TwoFactorTurnOff') }}</button>
        </div>

        <form method="POST" x-show="action" x-cloak class="space-y-3 max-w-md"
              :action="action === 'codes' ? @js(route('account.two_factor.codes')) : @js(route('account.two_factor.disable'))">
          @csrf
          <label class="form-label" for="tf-off-password">{{ __('messages.TwoFactorConfirmPassword') }}</label>
          <input id="tf-off-password" name="password" type="password" class="input" required autocomplete="current-password" placeholder="••••••••">
          <div class="flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary">
              <span x-show="action === 'codes'">{{ __('messages.TwoFactorNewCodes') }}</span>
              <span x-show="action === 'disable'">{{ __('messages.TwoFactorTurnOff') }}</span>
            </button>
            <button type="button" class="btn btn-outline" @click="action = null">{{ __('messages.Cancel') }}</button>
          </div>
        </form>
      </div>

    @elseif ($tfSetup)
      {{-- ---------------------------------------------- setup in progress --}}
      @if ($tfSetup['method'] === 'email')
        <p class="text-sm text-fg-muted mb-4">
          {{ __('messages.TwoFactorEmailPrompt', ['email' => \App\Support\Security\CustomerTwoFactor::maskEmail((string) $me->email)]) }}
        </p>
      @else
        <ol class="text-sm text-fg-muted mb-4 ps-5 list-decimal space-y-1">
          <li>{{ __('messages.TwoFactorStepInstall') }}</li>
          <li>{{ __('messages.TwoFactorStepScan') }}</li>
          <li>{{ __('messages.TwoFactorStepConfirm') }}</li>
        </ol>
        <div class="flex flex-wrap items-center gap-4 mb-4">
          <div id="tf-qr" data-uri="{{ $tfSetup['uri'] }}"
               style="width: 172px; height: 172px; padding: 6px; background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center;"></div>
          <div class="text-sm">
            <div class="text-fg-muted text-xs mb-1">{{ __('messages.TwoFactorManualKey') }}</div>
            <code class="font-mono break-all">{{ trim(chunk_split($tfSetup['secret'], 4, ' ')) }}</code>
          </div>
        </div>
        <script>
          (function () {
            var el = document.getElementById('tf-qr');
            var sources = ['/vendor/qrcode/qrcode.min.js?v=full-3', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'];
            function draw() { try { new window.QRCode(el, { text: el.dataset.uri, width: 160, height: 160 }); } catch (e) {} }
            function load(i) {
              if (window.QRCode) return draw();
              if (i >= sources.length) return;
              var s = document.createElement('script');
              s.src = sources[i];
              s.onload = function () { window.QRCode ? draw() : load(i + 1); };
              s.onerror = function () { load(i + 1); };
              document.head.appendChild(s);
            }
            load(0);
          })();
        </script>
      @endif

      <form method="POST" action="{{ route('account.two_factor.confirm') }}" class="space-y-3 max-w-md">
        @csrf
        <label class="form-label" for="tf-setup-code">{{ __('messages.TwoFactorCodeLabel') }}</label>
        <input id="tf-setup-code" name="code" type="text" class="input text-center" required maxlength="6"
               inputmode="numeric" autocomplete="one-time-code" placeholder="000000" style="letter-spacing: .3em;">
        <div class="flex gap-2 flex-wrap">
          <button type="submit" class="btn btn-primary">
            <x-store.icon name="check" class="w-4 h-4" />{{ __('messages.TwoFactorTurnOn') }}
          </button>
        </div>
      </form>
      <div class="flex gap-4 flex-wrap mt-3 text-sm">
        @if ($tfSetup['method'] === 'email')
          <form method="POST" action="{{ route('account.two_factor.resend') }}">
            @csrf
            <button type="submit" class="text-accent-500 hover:underline">{{ __('messages.TwoFactorResend') }}</button>
          </form>
        @endif
        <form method="POST" action="{{ route('account.two_factor.cancel') }}">
          @csrf
          <button type="submit" class="text-fg-muted hover:underline">{{ __('messages.Cancel') }}</button>
        </form>
      </div>

    @else
      {{-- ---------------------------------------------- off --}}
      <p class="text-sm text-fg-muted mb-4">{{ __('messages.TwoFactorOffHelp') }}</p>
      <form method="POST" action="{{ route('account.two_factor.start') }}" class="space-y-4 max-w-md">
        @csrf
        <div class="space-y-2">
          <label class="flex items-start gap-2 text-sm cursor-pointer">
            <input type="radio" name="method" value="email" class="mt-1" checked>
            <span>
              <span class="font-semibold flex items-center gap-1"><x-store.icon name="mail" class="w-4 h-4" />{{ __('messages.TwoFactorMethodEmail') }}</span>
              <span class="text-fg-muted block">{{ __('messages.TwoFactorMethodEmailHelp') }}</span>
            </span>
          </label>
          <label class="flex items-start gap-2 text-sm cursor-pointer">
            <input type="radio" name="method" value="app" class="mt-1">
            <span>
              <span class="font-semibold flex items-center gap-1"><x-store.icon name="smartphone" class="w-4 h-4" />{{ __('messages.TwoFactorMethodApp') }}</span>
              <span class="text-fg-muted block">{{ __('messages.TwoFactorMethodAppHelp') }}</span>
            </span>
          </label>
        </div>
        <div>
          <label class="form-label" for="tf-on-password">{{ __('messages.TwoFactorConfirmPassword') }}</label>
          <input id="tf-on-password" name="password" type="password" class="input" required autocomplete="current-password" placeholder="••••••••">
        </div>
        <button type="submit" class="btn btn-primary">
          <x-store.icon name="shield-check" class="w-4 h-4" />{{ __('messages.TwoFactorContinue') }}
        </button>
      </form>
    @endif
  </div>
</div>
@endif
