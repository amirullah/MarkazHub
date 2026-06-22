<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Backup data Anda</x-slot>
        <x-slot name="description">Unduh seluruh data organisasi Anda sebagai file .sql (cadangan/pemulihan).</x-slot>
        <div style="font-size:.9rem;color:#475569;line-height:1.6">
            <p>Klik <strong>"Unduh Backup Data (.sql)"</strong> di kanan atas untuk menyimpan cadangan ke komputer Anda. Berisi: toko, supplier, produk, pemetaan SKU, pesanan, dan item pesanan milik organisasi Anda.</p>
            <ul style="list-style:disc;margin:.5rem 0 0 1.25rem">
                <li>File hanya berisi <strong>data Anda</strong> (aman, tidak mencampur data seller lain).</li>
                <li>Lakukan backup secara berkala (mis. mingguan) sebagai pengaman.</li>
                <li>Untuk memulihkan, file .sql ini bisa di-import kembali ke database.</li>
            </ul>
        </div>
    </x-filament::section>
</x-filament-panels::page>
