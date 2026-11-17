@extends('layouts.store')

{{-- Code step after a correct password, for accounts with two-step
     verification (StoreTwoFactorController). $method: 'email' | 'app'. --}}
@section('content')
<section class="border-b border-line-subtle"
         style="background: linear-gradient(135deg, rgb(var(--color-accent-500) / .04), rgb(var(--color-bg-surface)));">
  <div class="container py-6">
    <h1 class="section-title">{{ __('messages.TwoFactorTitle') }}</h1>
  </div>
</section>

<div class="container py-10">
  <div class="max-w-md mx-auto space-y-3" x-data="{ recovery: false }">

    @if (session('two_factor_sent'))
      <div class="alert alert-success flex items-start gap-2">
        <x-store.icon name="check-circle" class="w-5 h-5 shrink-0 mt-0.5" />
        <div>{{ __('messages.TwoFactorCodeSent', ['email' => $maskedEmail]) }}</div>
      </div>
    @endif
    @if ($emailFailed && ! session('two_factor_sent'))
      <div class="alert alert-warning">{{ __('messages.TwoFactorEmailFailed') }}</div>
    @endif
    @if (session('two_factor_notice'))
      <div class="alert alert-warning">{{ session('two_factor_notice') }}</div>
    @endif
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="m-0 list-disc ps-5">
          @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
      </div>
    @endif

    <div class="card">
      <div class="card-body p-6 md:p-8">
        <div class="text-center mb-6">
          <div class="inline-flex w-14 h-14 rounded-full items-center justify-center text-accent-500 mb-3"
               style="background: rgb(var(--color-accent-500) / .1);">
            <x-store.icon name="shield-check" class="w-7 h-7" />
          </div>
          <h2 class="text-xl font-bold m-0">{{ __('messages.TwoFactorTitle') }}</h2>
          <div class="text-fg-muted text-sm mt-1">
            @if ($method === 'email')
              {{ __('messages.TwoFactorEmailPrompt', ['email' => $maskedEmail]) }}
            @else
              <span x-show="!recovery">{{ __('messages.TwoFactorAppPrompt') }}</span>
              <span x-show="recovery" x-cloak>{{ __('messages.TwoFactorRecoveryPrompt') }}</span>
            @endif
          </div>
        </div>

        <form method="POST" action="{{ route('store.login.two-factor.verify') }}" novalidate class="space-y-4">
          @csrf
          <div>
            <label class="form-label" for="tf-code">
              <span x-show="!recovery">{{ __('messages.TwoFactorCodeLabel') }}</span>
              <span x-show="recovery" x-cloak>{{ __('messages.TwoFactorRecoveryLabel') }}</span>
            </label>
            <input id="tf-code" type="text" name="code" class="input text-center" required autofocus maxlength="32"
                   autocomplete="one-time-code" :inputmode="recovery ? 'text' : 'numeric'"
                   :placeholder="recovery ? 'xxxxx-xxxxx' : '000000'" placeholder="000000"
                   style="font-size: 1.25rem; letter-spacing: .3em;">
          </div>

          <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
            <input type="checkbox" name="trust_device" value="1" class="checkbox">
            <span>{{ __('messages.TwoFactorTrustDevice', ['days' => $trustDays]) }}</span>
          </label>

          <button class="btn btn-primary btn-block btn-lg" type="submit">
            <x-store.icon name="check" class="w-5 h-5" />{{ __('messages.TwoFactorVerify') }}
          </button>
        </form>

        <div class="flex items-center justify-between gap-3 flex-wrap mt-5 text-sm">
          @if ($method === 'email')
            <form method="POST" action="{{ route('store.login.two-factor.resend') }}">
              @csrf
              <button type="submit" class="text-accent-500 hover:underline">{{ __('messages.TwoFactorResend') }}</button>
            </form>
          @else
            <button type="button" class="text-accent-500 hover:underline"
                    @click="recovery = !recovery; $nextTick(() => document.getElementById('tf-code').focus())">
              <span x-show="!recovery">{{ __('messages.TwoFactorUseRecovery') }}</span>
              <span x-show="recovery" x-cloak>{{ __('messages.TwoFactorUseApp') }}</span>
            </button>
          @endif
          <a href="{{ route('store.login.show') }}" class="text-fg-muted hover:underline">{{ __('messages.TwoFactorBack') }}</a>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
