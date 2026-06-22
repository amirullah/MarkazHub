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
    public const DEFAULT = 'Hobi, Buku & Lainnya';

    /**
     * Aturan: nama kategori => daftar kata kunci (dicek dari ATAS, spesifik dulu).
     * Kata kunci dicocokkan sebagai substring (huruf kecil).
     */
    private const RULES = [
        'Handphone & Laptop' => [
            'laptop', 'notebook', 'macbook', 'tablet', ' ipad', 'iphone', 'smartphone',
            ' hp ', 'handphone', 'redmi', 'samsung galaxy', 'realme', 'oppo ', 'vivo ',
            'tempered glass', 'anti gores', 'casing hp', 'case hp', 'softcase', 'hardcase',
        ],
        'Otomotif' => [
            'mobil', 'motor', ' ban ', ' oli ', 'helm', 'spion', 'klakson', ' aki ', 'busi',
            'sparepart', ' velg', 'kampas rem', ' jok ', 'spakbor', 'knalpot', 'setir', 'steering',
            'kaca film', 'wiper', 'karpet mobil', 'cover mobil', 'lampu led motor',
        ],
        'Olahraga & Outdoor' => [
            'sepeda', 'dumbbell', 'barbel', 'matras yoga', 'raket', ' bola ', 'jersey',
            'tenda', 'hiking', 'gunung', 'fitness', ' gym', 'olahraga', 'renang', 'pancing',
            'joran', 'camping', 'carrier', 'skipping', 'treadmill', 'sepatu bola', 'sport',
        ],
        'Kecantikan & Perawatan' => [
            'lipstik', 'lip ', 'bedak', 'foundation', ' serum', 'skincare', 'masker wajah',
            'parfum', 'kosmetik', 'makeup', 'make up', ' toner', 'sunscreen', 'micellar',
            'cushion', 'maskara', 'eyeliner', 'pelembab', 'pemutih', 'cream wajah', 'facial',
            'sampo', 'shampoo', 'kondisioner', 'pomade', 'minyak rambut', 'parfume',
        ],
        'Kesehatan & FMCG' => [
            'vitamin', 'suplemen', ' obat', 'madu', 'masker medis', 'hand sanitizer',
            'sabun', 'pasta gigi', 'sikat gigi', ' tisu', 'popok', ' susu', 'kopi', 'teh ',
            'snack', 'makanan', 'minuman', 'camilan', 'termometer', 'tensimeter', 'p3k',
            'multivitamin', 'herbal', 'jamu',
        ],
        'Elektronik & Gadget' => [
            'charger', ' kabel', 'powerbank', 'power bank', 'earphone', 'headset', 'earbud',
            ' speaker', ' mouse', 'keyboard', 'flashdisk', 'flash disk', 'adaptor', 'adapter',
            ' lampu', ' kipas', 'setrika', 'blender', 'rice cooker', 'kamera', 'tripod',
            'mikrofon', 'microphone', 'bluetooth', ' usb', ' hdmi', ' tv ', 'led strip',
            'stop kontak', 'saklar', 'dispenser', 'mesin cuci', 'kulkas', 'rechargeable', 'pompa',
        ],
        'Rumah Tangga & Dapur' => [
            'panci', 'wajan', 'teflon', 'piring', ' gelas', 'sendok', 'garpu', ' pisau',
            'dapur', ' rak ', 'lemari', 'sprei', 'bantal', 'guling', 'gorden', 'tirai',
            ' ember', ' sapu', ' pel ', 'kontainer', 'toples', ' botol', 'tempat ', 'organizer',
            'gantungan', 'keranjang', 'handuk', 'karpet', 'keset', 'jam dinding', 'vas',
            'talenan', 'spatula', 'saringan', 'cetakan kue', 'tempat sampah',
        ],
        'Fashion & Aksesoris' => [
            ' baju', ' kaos', 'kemeja', 'celana', ' jaket', ' dress', ' rok ', 'hijab', 'jilbab',
            'sepatu', 'sandal', ' tas ', 'dompet', ' topi', 'kacamata', 'jam tangan', 'ikat pinggang',
            'kaos kaki', 'gamis', ' koko', 'batik', 'sweater', 'hoodie', 'pakaian', 'fashion',
            'aksesoris', 'anting', 'kalung', 'gelang', 'cincin', 'bros', 'scarf', 'mukena',
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
