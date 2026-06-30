@extends('layouts.app', ['pageTitle' => 'Edit User'])
@section('content')
<form method="POST" action="{{ route('admin.users.update', $user) }}" class="ct-panel max-w-2xl p-6">@csrf @method('PUT')
    <h2 class="text-xl font-bold text-white">{{ $user->name }}</h2><p class="text-sm text-slate-400">{{ $user->email }}</p>
    <label class="mt-5 block text-sm text-slate-300">Status<select name="status" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white"><option value="active" @selected($user->status==='active')>Active</option><option value="suspended" @selected($user->status==='suspended')>Suspended</option></select></label>
    <label class="mt-4 block text-sm text-slate-300">Upload Limit MB<input name="upload_limit_mb" type="number" value="{{ $user->upload_limit_mb }}" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white"></label>
    <div class="mt-4 text-sm text-slate-300">Roles</div><div class="mt-2 flex flex-wrap gap-3">@foreach($roles as $role)<label class="rounded-2xl border border-white/10 px-4 py-2 text-sm"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains($role))> {{ $role->name }}</label>@endforeach</div>
    <button class="ct-button mt-6">Save User</button>
</form>
@endsection
