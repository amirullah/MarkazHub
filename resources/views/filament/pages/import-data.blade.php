<x-filament-panels::page>
    <div class="space-y-6">
        {{-- ====== SEBELUM IMPORT: panduan ====== --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600">
            <p class="font-semibold text-gray-800 mb-2">📥 Cara import (3 langkah)</p>
            <ol class="list-decimal ms-5 space-y-1">
                <li>Klik <strong>Unggah &amp; Import</strong> di kanan atas.</li>
                <li>Pilih <strong>toko tujuan</strong> sesuai channel file (nama toko + channel ditampilkan, mis. "MarkazMall SBY — Shopee").</li>
                <li>Unggah satu atau beberapa file ekspor sekaligus, lalu klik Import. Sistem mengenali jenis tiap file otomatis.</li>
            </ol>

            <p class="font-semibold text-gray-800 mt-4 mb-2">Jenis file yang didukung</p>
            <div class="grid gap-2 sm:grid-cols-3">
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                    <div class="font-medium text-gray-800">🧾 Laporan Penghasilan</div>
                    <div class="text-xs mt-1">Memberi <strong>biaya admin & laba final</strong> yang akurat. Tanpa ini, laba masih estimasi.</div>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                    <div class="font-medium text-gray-800">📦 File Pesanan</div>
                    <div class="text-xs mt-1">Daftar pesanan, produk, qty, & status (selesai/dikirim/retur/batal).</div>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                    <div class="font-medium text-gray-800">🗂️ Master / Laporan Jakmall</div>
                    <div class="text-xs mt-1">Katalog produk & <strong>HPP/modal</strong> (untuk hitung laba).</div>
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500">💡 File yang channel-nya tidak cocok dengan toko otomatis dilewati (tidak menggagalkan file lain). Aman mengunggah ulang — data yang sama akan diperbarui, bukan dobel.</p>
        </div>

        {{-- ====== SESUDAH IMPORT: hasil ====== --}}
        @if ($report)
            @php
                $ok = collect($report)->where('ok', true)->count();
                $fail = collect($report)->where('ok', false)->count();
                $ringkas = array_filter([$summary['jakmall'] ?? null, $summary['orders'] ?? null, $summary['dropship'] ?? null]);
            @endphp

            {{-- Ringkasan menonjol --}}
            <div class="rounded-xl border p-4 {{ $fail ? 'border-amber-200 bg-amber-50' : 'border-green-200 bg-green-50' }}">
                <p class="font-bold text-base mb-1">
                    {{ $fail ? '⚠️' : '✅' }} Import selesai — {{ $ok }} file berhasil{{ $fail ? ", {$fail} dilewati/gagal" : '' }}
                </p>
                @foreach ($ringkas as $line)
                    <p class="text-sm text-gray-700">• {{ $line }}</p>
                @endforeach
                @if (empty($ringkas))
                    <p class="text-sm text-gray-700">Tidak ada data baru diproses. Cek detail di bawah.</p>
                @endif
            </div>

            {{-- Detail per file --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="font-semibold mb-3">📋 Detail per file</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <tbody>
                        @foreach ($report as $r)
                            <tr class="border-t border-gray-100">
                                <td class="py-2 pe-2 w-6 align-top">{{ $r['ok'] ? '✅' : '⏭️' }}</td>
                                <td class="py-2 pe-3 font-mono text-xs break-all max-w-xs align-top">{{ $r['name'] }}</td>
                                <td class="py-2 text-gray-600">
                                    @if ($r['ok'])
                                        <span class="font-medium text-gray-800">{{ $r['type'] }}</span> — {{ $r['detail'] ?? '' }}
                                    @else
                                        <span class="text-amber-700">Dilewati: {{ $r['reason'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if (!empty($summary['hpp_changes']))
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="font-semibold mb-3">💲 Perubahan harga HPP: {{ count($summary['hpp_changes']) }} SKU</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-gray-500 text-left">
                                <tr><th class="py-1 pe-3">SKU</th><th class="py-1 pe-3">Produk</th><th class="py-1 pe-3 text-right">Lama</th><th class="py-1 pe-3 text-right">Baru</th></tr>
                            </thead>
                            <tbody>
                            @foreach (array_slice($summary['hpp_changes'], 0, 50) as $c)
                                <tr class="border-t border-gray-100">
                                    <td class="py-1 pe-3 font-mono text-xs">{{ $c['sku'] }}</td>
                                    <td class="py-1 pe-3">{{ \Illuminate\Support\Str::limit($c['name'], 40) }}</td>
                                    <td class="py-1 pe-3 text-right">Rp {{ number_format($c['old'], 0, ',', '.') }}</td>
                                    <td class="py-1 pe-3 text-right">Rp {{ number_format($c['new'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Pesanan lama tidak berubah kecuali Anda mencentang "Perbarui HPP pesanan lama".</p>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
