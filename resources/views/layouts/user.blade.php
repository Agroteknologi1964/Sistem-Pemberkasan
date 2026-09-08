@extends('layouts.app')

@section('content')
    {{-- Warning jika user wajib ganti password --}}
    @if(auth()->check() && auth()->user()->force_change_password)
        <div class="mb-4 flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <span class="text-lg">⚠️</span>
            <span>
                Anda wajib <a href="{{ route('user.profile.password') }}" class="font-semibold underline hover:text-amber-900">mengganti password</a>
                sebelum dapat menggunakan sistem sepenuhnya.
            </span>
        </div>
    @endif

    {{-- Session warning --}}
    @if(session('warning'))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ session('warning') }}
        </div>
    @endif
@endsection
