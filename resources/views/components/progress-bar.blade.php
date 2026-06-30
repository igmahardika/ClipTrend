@props(['value' => 0])
<div {{ $attributes->merge(['class' => 'h-2 w-full overflow-hidden rounded-full bg-white/10']) }}>
    <div class="h-full rounded-full transition-all duration-500" style="width: {{ min(100, max(0, (int) $value)) }}%; background: linear-gradient(135deg, #b6ff4a, #7cf7ff);"></div>
</div>
