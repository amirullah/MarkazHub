<x-filament-panels::page>
    @php
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $card = 'border:1px solid rgb(229 231 235);border-radius:.75rem;padding:1rem;background:#fff';
        $th = 'text-align:left;padding:.5rem .5rem;font-size:.72rem;color:#64748b;text-transform:uppercase;border-bottom:1px solid #eef2f7';
        $td = 'padding:.5rem .5rem;font-size:.85rem;border-top:1px solid #f1f5f9';
    @endphp

    {{-- Statistik utama --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:1rem">
        <div style="{{ $card }}">
            <div style="font-size:.8rem;color:#64748b">Total Laba (Selesai)</div>
            <div style="font-size:1.6rem;font-weight:800;color:{{ $totalLaba < 0 ? '#dc2626' : '#16a34a' }}">{{ $rp($totalLaba) }}</div>
        </div>
        <div style="{{ $card }}">
            <div style="font-size:.8rem;color:#64748b">Margin Laba</div>
            <div style="font-size:1.6rem;font-weight:800;color:{{ $margin < 0 ? '#dc2626' : ($margin < 10 ? '#d97706' : '#16a34a') }}">{{ $margin }}%</div>
        </div>
        <div style="{{ $card }}">
            <div style="font-size:.8rem;color:#64748b">Pesanan Rugi (Selesai)</div>
            <div style="font-size:1.6rem;font-weight:800;color:#dc2626">{{ number_format($jmlRugi, 0, ',', '.') }}</div>
            <div style="font-size:.7rem;color:#94a3b8">nilai {{ $rp($nilaiRugi) }}</div>
        </div>
        <div style="{{ $card }}">
            <div style="font-size:.8rem;color:#64748b">Produk di Bawah Modal</div>
            <div style="font-size:1.6rem;font-weight:800;color:#dc2626">{{ number_format($jmlProdukRugi, 0, ',', '.') }}</div>
        </div>
        <div style="{{ $card }}">
            <div style="font-size:.8rem;color:#64748b">Rasio Retur</div>
            <div style="font-size:1.6rem;font-weight:800;color:#d97706">{{ $rasioRetur }}%</div>
            <div style="font-size:.7rem;color:#94a3b8">{{ $jmlRetur }} dari {{ number_format($totalPesanan, 0, ',', '.') }} · batal {{ $jmlBatal }}</div>
        </div>
    </div>

    {{-- Produk Merugi (klik untuk detail) --}}
    <x-filament::section>
        <x-slot name="heading">📉 Produk Merugi — dijual di bawah modal</x-slot>
        <x-slot name="description">Diurutkan dari kerugian terbesar. 👆 Klik baris untuk lihat detail produk.</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $th }}">SKU</th><th style="{{ $th }}">Produk</th>
                    <th style="{{ $th }};text-align:right">Avg Jual</th><th style="{{ $th }};text-align:right">Avg Modal</th>
                    <th style="{{ $th }};text-align:right">Qty</th><th style="{{ $th }};text-align:right">Total Rugi</th>
                </tr></thead>
                <tbody>
                @forelse ($bawahModal as $b)
                    <tr wire:click="showDetail(@js($b->sku))" style="cursor:pointer" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                        <td style="{{ $td }};font-family:monospace;font-size:.75rem">{{ $b->sku }}</td>
                        <td style="{{ $td }}">{{ \Illuminate\Support\Str::limit($b->name, 40) }}</td>
                        <td style="{{ $td }};text-align:right">{{ $rp($b->avg_jual) }}</td>
                        <td style="{{ $td }};text-align:right">{{ $rp($b->avg_modal) }}</td>
                        <td style="{{ $td }};text-align:right;color:#64748b">{{ number_format($b->qty_terjual, 0, ',', '.') }}</td>
                        <td style="{{ $td }};text-align:right;color:#dc2626;font-weight:700">{{ $rp($b->total_rugi) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="{{ $td }};text-align:center;color:#94a3b8">Tidak ada produk dijual di bawah modal. 👍</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Produk Paling Untung --}}
    <x-filament::section>
        <x-slot name="heading">💚 Produk Paling Untung</x-slot>
        <x-slot name="description">Penyumbang laba terbesar (pesanan selesai). Pertahankan & promosikan.</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $th }}">SKU</th><th style="{{ $th }}">Produk</th>
                    <th style="{{ $th }};text-align:right">Qty</th><th style="{{ $th }};text-align:right">Total Untung</th>
                </tr></thead>
                <tbody>
                @forelse ($palingUntung as $p)
                    <tr>
                        <td style="{{ $td }};font-family:monospace;font-size:.75rem">{{ $p->sku }}</td>
                        <td style="{{ $td }}">{{ \Illuminate\Support\Str::limit($p->name, 40) }}</td>
                        <td style="{{ $td }};text-align:right;color:#64748b">{{ number_format($p->qty_terjual, 0, ',', '.') }}</td>
                        <td style="{{ $td }};text-align:right;color:#16a34a;font-weight:700">{{ $rp($p->total_untung) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="{{ $td }};text-align:center;color:#94a3b8">Belum ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem">
        <x-filament::section>
            <x-slot name="heading">🔻 Pesanan Rugi Terbesar (Selesai)</x-slot>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead><tr>
                        <th style="{{ $th }}">No. Pesanan</th><th style="{{ $th }}">Tanggal</th>
                        <th style="{{ $th }};text-align:right">Omzet</th><th style="{{ $th }};text-align:right">Laba</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($pesananRugi as $o)
                        <tr>
                            <td style="{{ $td }};font-family:monospace;font-size:.75rem">{{ $o->external_no }}</td>
                            <td style="{{ $td }};color:#64748b">{{ $o->order_date?->format('d M Y') }}</td>
                            <td style="{{ $td }};text-align:right">{{ $rp($o->product_revenue) }}</td>
                            <td style="{{ $td }};text-align:right;color:#dc2626;font-weight:700">{{ $rp($o->profit) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="{{ $td }};text-align:center;color:#94a3b8">Tidak ada pesanan rugi. 🎉</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">🏆 Produk Terlaris (qty)</x-slot>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead><tr>
                        <th style="{{ $th }}">SKU</th><th style="{{ $th }}">Produk</th><th style="{{ $th }};text-align:right">Terjual</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($terlaris as $t)
                        <tr>
                            <td style="{{ $td }};font-family:monospace;font-size:.75rem">{{ $t->sku }}</td>
                            <td style="{{ $td }}">{{ \Illuminate\Support\Str::limit($t->name, 35) }}</td>
                            <td style="{{ $td }};text-align:right;font-weight:700">{{ number_format($t->total_qty, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="{{ $td }};text-align:center;color:#94a3b8">Belum ada data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    {{-- Modal detail produk (muncul saat baris produk merugi diklik) --}}
    @if ($detail)
        <div wire:click.self="closeDetail"
             style="position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:50;display:flex;align-items:center;justify-content:center;padding:1rem">
            <div style="background:#fff;border-radius:1rem;max-width:460px;width:100%;padding:1.5rem;box-shadow:0 24px 70px rgba(0,0,0,.35)">
                <div style="display:flex;justify-content:space-between;align-items:start;gap:1rem;margin-bottom:1rem">
                    <div>
                        <div style="font-size:.72rem;color:#94a3b8;font-family:monospace">{{ $detail['sku'] }}</div>
                        <div style="font-weight:700;font-size:1rem;line-height:1.3">{{ $detail['name'] }}</div>
                        @if ($detail['kategori'])
                            <span style="display:inline-block;margin-top:.4rem;background:#eff6ff;color:#2563eb;font-size:.72rem;padding:.15rem .6rem;border-radius:999px">{{ $detail['kategori'] }}</span>
                        @endif
                    </div>
                    <button wire:click="closeDetail" style="background:#f1f5f9;border:none;border-radius:.5rem;width:2rem;height:2rem;cursor:pointer;font-size:1.1rem;color:#475569">×</button>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                    <div style="{{ $card }}"><div style="font-size:.72rem;color:#64748b">HPP / Modal</div><div style="font-weight:700">{{ $detail['hpp'] !== null ? $rp($detail['hpp']) : '—' }}</div></div>
                    <div style="{{ $card }}"><div style="font-size:.72rem;color:#64748b">Total Terjual</div><div style="font-weight:700">{{ number_format($detail['total_terjual'], 0, ',', '.') }} pcs</div></div>
                    <div style="{{ $card }}"><div style="font-size:.72rem;color:#64748b">Avg Harga Jual</div><div style="font-weight:700">{{ $rp($detail['avg_jual']) }}</div></div>
                    <div style="{{ $card }}"><div style="font-size:.72rem;color:#64748b">Avg Modal</div><div style="font-weight:700">{{ $rp($detail['avg_modal']) }}</div></div>
                    <div style="{{ $card }};grid-column:1 / -1;background:#fef2f2;border-color:#fecaca">
                        <div style="font-size:.72rem;color:#b91c1c">Total Kerugian ({{ $detail['transaksi_rugi'] }} transaksi · {{ $detail['qty_rugi'] }} pcs di bawah modal)</div>
                        <div style="font-weight:800;font-size:1.3rem;color:#dc2626">{{ $rp($detail['total_rugi']) }}</div>
                    </div>
                </div>
                <p style="margin-top:1rem;font-size:.78rem;color:#64748b">💡 Saran: naikkan harga jual minimal di atas HPP, atau perbarui HPP bila modal sudah berubah (menu Produk).</p>
            </div>
        </div>
    @endif
</x-filament-panels::page>
