<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Apa yang baru di MarkazHub</x-slot>
        <x-slot name="description">Daftar perubahan & fitur baru pada tiap versi (terbaru di atas).</x-slot>

        <div style="display:flex;flex-direction:column;gap:1.1rem">
            @foreach ($changelog as $rel)
                <div style="border-left:3px solid #2563eb;padding:.1rem 0 .1rem 1rem">
                    <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                        <span style="background:#2563eb;color:#fff;font-size:.72rem;font-weight:700;padding:.12rem .5rem;border-radius:999px">v{{ $rel['version'] }}</span>
                        <strong style="font-size:.95rem;color:#0f172a">{{ $rel['title'] }}</strong>
                        <span style="font-size:.78rem;color:#94a3b8">{{ $rel['date'] }}</span>
                    </div>
                    <ul style="list-style:disc;margin:.45rem 0 0 1.25rem;color:#475569;font-size:.86rem;line-height:1.6">
                        @foreach ($rel['changes'] as $c)
                            <li>{{ $c }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
