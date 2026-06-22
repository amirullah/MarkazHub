<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Backup & pemulihan data</x-slot>
        <x-slot name="description">Simpan cadangan data Anda, atau pulihkan dari cadangan sebelumnya.</x-slot>
        <div style="font-size:.9rem;color:#475569;line-height:1.7">
            <p><strong>📥 Unduh Backup</strong> — menyimpan seluruh data Anda (toko, supplier, produk, pemetaan SKU, pesanan, dan item) ke satu file <code>.sql</code> di komputer Anda.</p>
            <p style="margin-top:.5rem"><strong>📤 Pulihkan dari Backup</strong> — mengganti data Anda saat ini dengan isi file backup. Berguna jika ingin mengembalikan kondisi sebelumnya.</p>
            <ul style="list-style:disc;margin:.6rem 0 0 1.25rem">
                <li>File berisi <strong>seluruh data Anda</strong> — aman disimpan sebagai pengaman.</li>
                <li>Lakukan backup secara berkala (mis. mingguan).</li>
                <li>Pemulihan <strong>mengganti</strong> data saat ini, jadi pastikan file yang dipilih benar.</li>
            </ul>
        </div>
    </x-filament::section>
</x-filament-panels::page>
