@extends('layouts.app')
@section('content')
<div class="relative flex min-h-screen items-center justify-center px-4">
    <form method="POST" action="{{ route('register') }}" class="ct-panel w-full max-w-md p-8">@csrf
        <h1 class="text-2xl font-black text-white">Buat Akun Creator</h1><p class="mt-2 text-sm text-slate-400">Mulai ubah long-form video menjadi short-form content.</p>
        <label class="mt-6 block text-sm text-slate-300">Nama<input name="name" required class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white"></label>
        <label class="mt-4 block text-sm text-slate-300">Email<input name="email" type="email" required class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white"></label>
        <label class="mt-4 block text-sm text-slate-300">Password<input name="password" type="password" required class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white"></label>
        <label class="mt-4 block text-sm text-slate-300">Konfirmasi Password<input name="password_confirmation" type="password" required class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white"></label>
        <button class="ct-button mt-6 w-full justify-center">Create Account</button>
        <p class="mt-5 text-center text-sm text-slate-400">Sudah punya akun? <a href="{{ route('login') }}" class="text-cyan-300">Login</a></p>
    </form>
</div>
@endsection
