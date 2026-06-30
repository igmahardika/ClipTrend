@props(['title' => 'Belum ada data', 'description' => 'Mulai buat project pertama Anda.'])
<div {{ $attributes->merge(['class' => 'rounded-[24px] border border-dashed border-white/10 bg-white/[0.035] p-10 text-center']) }}>
    <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-[22px] border border-lime-300/20 bg-lime-300/10 text-2xl text-lime-300">✦</div>
    <h3 class="text-lg font-black text-white">{{ $title }}</h3>
    <p class="mt-2 text-sm leading-6 text-slate-400">{{ $description }}</p>
    {{ $slot }}
</div>
