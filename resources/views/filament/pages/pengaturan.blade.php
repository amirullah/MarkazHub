<x-filament-panels::page>
    @php
        $card = 'border:1px solid #e5e7eb;border-radius:.75rem;padding:1.25rem;background:#fff';
        $dropship = (bool) ($org?->uses_dropship ?? true);
        $svcPct = rtrim(rtrim(number_format((float) ($org?->fee_shopee_service_pct ?? 10), 2, '.', ''), '0'), '.');
        $svcCap = (int) ($org?->fee_shopee_service_cap ?? 10000);
        $dynPct = rtrim(rtrim(number_format((float) ($org?->fee_tokotiktok_dynamic_pct ?? 6.5), 2, '.', ''), '0'), '.');
        $row = 'display:flex;justify-content:space-between;gap:1rem;font-size:.85rem;padding:.35rem 0;border-top:1px solid #f1f5f9';
        $calibrated = ((float) ($org?->fee_shopee_service_pct ?? 10) == 0.0 && (float) ($org?->fee_tokotiktok_dynamic_pct ?? 6.5) == 0.0);
    @endphp

    <div style="display:flex;flex-direction:column;gap:1rem;max-width:640px">
        <div style="{{ $card }}">
            <div style="font-weight:700;color:#1e293b;margin-bottom:.25rem">🏪 {{ $org?->name ?? 'Organisasi' }}</div>
            <div style="font-size:.85rem;color:#64748b">Atur fitur yang sesuai dengan cara Anda berjualan.</div>
        </div>

        <div style="{{ $card }};display:flex;align-items:center;justify-content:space-between;gap:1rem">
            <div>
                <div style="font-weight:600;color:#1e293b">Berjualan Dropship</div>
                <div style="font-size:.82rem;color:#64748b;margin-top:.25rem;max-width:46ch">
                    Aktifkan bila sebagian/semua pesanan Anda dropship — dari sumber mana pun (supplier/perusahaan lain, atau manual dari seller lain). Aktif: kolom & biaya dropship + pemenuhan tampil pada produk & pesanan. Nonaktif: tampilan dropship disembunyikan & laba dihitung sebagai packing sendiri.
                </div>
            </div>
            <div style="flex-shrink:0">
                @if ($dropship)
                    <span style="display:inline-block;background:#dcfce7;color:#15803d;font-weight:700;font-size:.82rem;padding:.4rem .9rem;border-radius:999px">● Aktif</span>
                @else
                    <span style="display:inline-block;background:#f1f5f9;color:#64748b;font-weight:700;font-size:.82rem;padding:.4rem .9rem;border-radius:999px">○ Nonaktif</span>
                @endif
            </div>
        </div>

        <div style="{{ $card }}">
            <div style="font-weight:700;color:#1e293b">💸 Biaya Marketplace (untuk estimasi laba)</div>
            <div style="font-size:.82rem;color:#64748b;margin:.25rem 0 .6rem;max-width:62ch">
                Dipakai saat <strong>Laporan Penghasilan belum diimpor</strong>. Saat laporan resmi masuk, biaya ASLI menggantikan estimasi.
            </div>

            @if ($calibrated)
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.5rem;padding:.6rem .7rem;font-size:.82rem;color:#15803d;margin-bottom:.6rem">
                    ✅ <strong>Sudah dikalibrasi dari Laporan Penghasilan Anda.</strong> Tarif per kategori (menu <strong>Kategori</strong>) kini = tarif EFEKTIF nyata, sudah termasuk komisi + biaya layanan + komisi dinamis.
                </div>
                <div style="{{ $row }}"><span>Tarif efektif rata-rata — Shopee</span><strong>{{ $avgShopee }}%</strong></div>
                <div style="{{ $row }}"><span>Tarif efektif rata-rata — Tokopedia/TikTok</span><strong>{{ $avgToko }}%</strong></div>
                <div style="{{ $row }}"><span>Biaya Proses Pesanan (kedua platform)</span><strong>Rp1.250</strong></div>
                <div style="font-size:.78rem;color:#94a3b8;margin-top:.6rem">Rumus: (tarif kategori % × subtotal) + Rp1.250. Tarif bisa beda tiap kategori — lihat menu Kategori.</div>
            @else
                <div style="font-size:.82rem;color:#64748b;margin-bottom:.4rem">Komisi per kategori diatur di menu <strong>Kategori</strong>; komponen berikut biaya TAMBAHAN (default struktur marketplace):</div>
                <div style="{{ $row }}"><span>Shopee — Biaya Layanan</span><strong>{{ $svcPct }}%{{ $svcCap > 0 ? ' (maks Rp' . number_format($svcCap, 0, ',', '.') . ')' : '' }}</strong></div>
                <div style="{{ $row }}"><span>Tokopedia/TikTok — Komisi Dinamis</span><strong>{{ $dynPct }}%</strong></div>
                <div style="{{ $row }}"><span>Biaya Proses Pesanan (kedua platform)</span><strong>Rp1.250</strong></div>
                <div style="font-size:.8rem;color:#2563eb;background:#eff6ff;border:1px solid #bfdbfe;border-radius:.5rem;padding:.55rem .7rem;margin-top:.6rem">
                    💡 Agar PALING akurat, klik <strong>“Kalibrasi Tarif dari Laporan”</strong> di kanan atas — sistem menghitung tarif nyata dari pesanan Anda yang sudah ada Laporan Penghasilannya.
                </div>
            @endif
        </div>

        <p style="font-size:.8rem;color:#94a3b8"><strong>Kalibrasi Tarif dari Laporan</strong> = paling akurat (pakai data Anda). <strong>Ubah Pengaturan</strong> = atur manual (dropship & biaya tambahan).</p>
    </div>
</x-filament-panels::page>
