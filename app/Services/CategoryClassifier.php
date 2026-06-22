<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;

/**
 * Mengklasifikasi produk ke kategori secara otomatis dari NAMA produk (kata kunci),
 * agar setiap produk punya kategori meski user tidak mengisinya. User tetap bisa
 * mengubah hasilnya. Bila kelak nama kategori marketplace ditangkap dari ekspor,
 * itu jadi sinyal yang lebih akurat (lihat classifyName()).
 */
class CategoryClassifier
{
    /** Kategori default bila tak ada kata kunci yang cocok. */
    public const DEFAULT = 'Lainnya';

    /**
     * Aturan: nama kategori => daftar kata kunci (dicek dari ATAS, spesifik dulu).
     * Diurutkan agar kategori BEDA-TARIF (HP/Laptop, Suplemen, Ibu&Bayi, Otomotif,
     * Logam Mulia, Digital) terdeteksi lebih dulu — paling memengaruhi akurasi biaya.
     */
    private const RULES = [
        'Voucher & Digital' => [
            'voucher', ' pulsa', 'token listrik', 'e-money', 'emoney', 'top up', 'topup',
            'saldo', 'kuota', 'paket data', 'gift card', 'diamond ml', 'gemas',
        ],
        'Logam Mulia & Perhiasan' => [
            'logam mulia', ' emas ', 'antam', 'perak murni', 'perhiasan emas', 'emas batangan',
        ],
        'Handphone & Tablet' => [
            ' hp ', 'handphone', 'smartphone', ' tablet', ' ipad', 'iphone', 'redmi',
            'samsung galaxy', 'realme', ' oppo', ' vivo', 'tempered glass', 'anti gores',
            'casing hp', 'case hp', 'softcase', 'hardcase', 'charger hp', 'kabel data',
        ],
        'Komputer & Laptop' => [
            'laptop', 'notebook', 'macbook', ' pc ', 'komputer', 'monitor', ' ssd', ' ram ',
            'motherboard', 'processor', ' vga', 'keyboard', ' mouse', 'flashdisk', 'flash disk',
            'hardisk', 'harddisk', 'webcam', 'router', 'printer',
        ],
        'Otomotif' => [
            ' mobil', ' motor', ' ban ', ' oli ', 'helm', 'spion', 'klakson', ' aki ', 'busi',
            'sparepart', ' velg', 'kampas rem', ' jok ', 'spakbor', 'knalpot', 'setir', 'steering',
            'kaca film', 'wiper', 'karpet mobil', 'cover mobil', 'lampu motor', 'lampu mobil',
        ],
        'Kesehatan & Suplemen' => [
            'vitamin', 'suplemen', ' obat', 'madu', 'multivitamin', 'herbal', 'jamu',
            'masker medis', 'hand sanitizer', 'termometer', 'tensimeter', ' p3k', 'kolagen',
            'collagen', 'minyak kayu putih', 'koyo',
        ],
        'Ibu & Bayi' => [
            ' bayi', ' baby', 'popok', 'pampers', 'susu formula', ' asi ', 'stroller',
            'gendongan', 'bedong', ' dot ', 'botol susu', 'mainan bayi', 'perlengkapan bayi',
        ],
        'Kecantikan & Makeup' => [
            'lipstik', 'lip ', 'bedak', 'foundation', 'kosmetik', 'makeup', 'make up',
            'cushion', 'maskara', 'eyeliner', 'blush', 'concealer', 'lip tint', 'lip cream',
        ],
        'Perawatan Diri' => [
            ' serum', 'skincare', 'masker wajah', 'parfum', ' toner', 'sunscreen', 'micellar',
            'pelembab', 'cream wajah', 'facial', 'sampo', 'shampoo', 'kondisioner', 'pomade',
            'minyak rambut', 'sabun wajah', 'pemutih', 'body lotion', 'deodorant', 'pasta gigi',
        ],
        'Audio, Kamera & Gaming' => [
            ' speaker', 'earphone', 'headset', 'earbud', 'headphone', 'microphone', 'mikrofon',
            ' kamera', 'tripod', 'gaming', 'console', 'joystick', 'gamepad', 'action cam',
        ],
        'Elektronik' => [
            'charger', ' kabel', 'powerbank', 'power bank', 'adaptor', 'adapter', ' lampu',
            ' kipas', 'setrika', 'blender', 'rice cooker', 'dispenser', 'mesin cuci', 'kulkas',
            ' usb', ' hdmi', ' tv ', 'led strip', 'stop kontak', 'saklar', 'pompa', 'rechargeable',
        ],
        'Dapur' => [
            'panci', 'wajan', 'teflon', ' piring', ' gelas', 'sendok', 'garpu', 'pisau dapur',
            ' pisau', 'talenan', 'spatula', 'saringan', 'cetakan kue', ' dapur', 'masak',
        ],
        'Perlengkapan Rumah' => [
            ' rak ', 'lemari', 'sprei', 'bantal', 'guling', 'gorden', 'tirai', ' ember',
            ' sapu', ' pel ', 'kontainer', 'toples', 'organizer', 'gantungan', 'keranjang',
            'handuk', ' karpet', 'keset', 'jam dinding', ' vas', 'tempat sampah', 'rumah tangga',
        ],
        'Makanan & Minuman' => [
            'makanan', 'minuman', 'snack', 'camilan', ' kopi', ' teh ', 'keripik', 'kerupuk',
            'bumbu', ' saus', ' mie ', 'cokelat', 'coklat', 'kacang',
        ],
        'Fashion Muslim' => [
            'gamis', 'hijab', 'jilbab', ' koko', 'mukena', 'kerudung', 'abaya', ' sarung',
        ],
        'Sepatu' => [
            'sepatu', 'sandal', 'sneakers', 'sepatu boots', ' boots',
        ],
        'Tas & Dompet' => [
            ' tas ', 'dompet', 'ransel', 'backpack', ' pouch', 'tas selempang',
        ],
        'Pakaian Wanita' => [
            ' dress', ' rok ', 'blouse', 'baju wanita', 'daster', 'legging', 'baju tidur',
        ],
        'Pakaian Pria' => [
            ' baju', ' kaos', 'kemeja', 'celana', ' jaket', 'hoodie', 'sweater', 'pakaian',
        ],
        'Jam Tangan & Kacamata' => [
            'jam tangan', 'kacamata', 'sunglasses',
        ],
        'Aksesoris Fashion' => [
            'ikat pinggang', 'kaos kaki', ' topi', 'anting', 'kalung', 'gelang', 'cincin',
            ' bros', 'scarf', 'aksesoris',
        ],
        'Mainan & Anak' => [
            'mainan', ' lego', 'puzzle', 'boneka', ' robot', 'slime', 'rubik',
        ],
        'Buku & Alat Tulis' => [
            ' buku', 'novel', 'alat tulis', 'pulpen', 'pensil', 'spidol', ' kertas', 'binder',
        ],
        'Olahraga & Outdoor' => [
            'sepeda', 'dumbbell', 'barbel', 'matras yoga', 'raket', ' bola ', 'jersey',
            'tenda', 'hiking', 'gunung', 'fitness', ' gym', 'olahraga', 'renang', 'pancing',
            'joran', 'camping', 'carrier', 'skipping', 'treadmill', 'sport',
        ],
        'Hobi & Koleksi' => [
            'koleksi', 'action figure', ' gitar', 'alat musik', ' musik', 'lukisan', 'kanvas',
            'cat air', 'kerajinan', 'model kit', 'diecast', ' kartu',
        ],
    ];

    /** Tentukan NAMA kategori dari nama produk (heuristik kata kunci). */
    public function classifyName(string $productName): string
    {
        $haystack = ' ' . mb_strtolower($productName) . ' ';
        foreach (self::RULES as $category => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    return $category;
                }
            }
        }
        return self::DEFAULT;
    }

    /** Resolusi nama kategori → id Category milik org (fallback ke DEFAULT). */
    public function categoryIdFor(string $productName, int $orgId): ?int
    {
        $name = $this->classifyName($productName);
        $id = Category::withoutGlobalScopes()->where('organization_id', $orgId)->where('name', $name)->value('id');
        return $id
            ?? Category::withoutGlobalScopes()->where('organization_id', $orgId)->where('name', self::DEFAULT)->value('id')
            ?? Category::withoutGlobalScopes()->where('organization_id', $orgId)->value('id');
    }

    /** Pasang kategori ke SEMUA produk org yang belum berkategori. */
    public function applyToOrg(int $orgId): int
    {
        $cats = Category::withoutGlobalScopes()->where('organization_id', $orgId)->pluck('id', 'name');
        if ($cats->isEmpty()) {
            return 0;
        }
        $default = $cats[self::DEFAULT] ?? $cats->first();
        $count = 0;

        Product::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->whereNull('category_id')
            ->chunkById(1000, function ($products) use ($cats, $default, &$count) {
                foreach ($products as $product) {
                    $name = $this->classifyName((string) $product->name);
                    $product->category_id = $cats[$name] ?? $default;
                    $product->saveQuietly();
                    $count++;
                }
            });

        return $count;
    }
}
