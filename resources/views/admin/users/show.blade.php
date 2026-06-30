@extends('layouts.app', ['pageTitle' => 'User Detail'])
@section('content')
<div class="ct-panel p-6"><h2 class="text-2xl font-black text-white">{{ $user->name }}</h2><p class="text-slate-400">{{ $user->email }}</p><div class="mt-4 flex gap-2">@foreach($user->roles as $role)<span class="rounded-full bg-white/10 px-3 py-1 text-xs">{{ $role->name }}</span>@endforeach</div></div>
@endsection
