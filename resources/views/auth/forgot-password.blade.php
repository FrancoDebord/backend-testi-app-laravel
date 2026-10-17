@extends('layouts.guest')
@section('title', __('account.forgot.title'))

@section('content')
<div class="card p-6 sm:p-8">
    <h1 class="text-h3">{{ __('account.forgot.title') }}</h1>
    <p class="text-secondary mt-2">{{ __('account.forgot.intro') }}</p>

    @if(session('status'))
    <div class="alert-success mt-5" role="status"><i class="fa-solid fa-circle-check mt-0.5"></i><p>{{ session('status') }}</p></div>
    @endif
    @if($errors->any())
    <div class="alert-error mt-5" role="alert"><i class="fa-solid fa-circle-exclamation mt-0.5"></i><p>{{ $errors->first() }}</p></div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4" data-loading-label="{{ __('account.forgot.loading') }}">
        @csrf
        <div>
            <label for="email" class="form-label">{{ __('account.fields.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="form-input" placeholder="{{ __('account.fields.email_placeholder') }}">
        </div>
        <button type="submit" class="btn-primary w-full">{{ __('account.forgot.submit') }}</button>
    </form>

    <p class="mt-8 border-t border-slate-200 pt-5 text-center text-sm">
        <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:text-primary-700 hover:underline">
            <i class="fa-solid fa-arrow-left mr-1"></i>{{ __('account.forgot.back') }}
        </a>
    </p>
</div>
@endsection
