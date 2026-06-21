<?php
// Parser CSV laporan pesanan, toleran terhadap variasi nama kolom dari
// Shopee / Tokopedia / TikTok Shop. Menghasilkan struktur pesanan
// ternormalisasi (lepas dari format asal) - mirip lapisan adapter di
// versi Next.js, sehingga mudah ditambah sumber lain (mis. API resmi).

// Normalisasi nama kolom: huruf kecil, buang non-alfanumerik.
function mp_norm_key(string $k): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower($k));
}

// Ambil nilai pertama yang cocok dari daftar kandidat nama kolom.
function mp_pick(array $row, array $candidates): ?string
{
    foreach ($candidates as $c) {
        $key = mp_norm_key($c);
        if (isset($row[$key]) && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return null;
}

// Parse angka gaya Indonesia ("Rp1.250.000,50" -> 1250000.5).
function mp_num(?string $v): float
{
    if ($v === null || $v === '') return 0.0;
    $s = preg_replace('/\s+/', '', str_ireplace('rp', '', $v));
    $hasComma = strpos($s, ',') !== false;
    $hasDot = strpos($s, '.') !== false;
    if ($hasComma && $hasDot) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif ($hasComma) {
        $s = str_replace(',', '.', $s);
    }
    $s = preg_replace('/[^0-9.\-]/', '', $s);
    return is_numeric($s) ? (float) $s : 0.0;
}

function mp_int(?string $v): int
{
    $n = (int) round(mp_num($v));
    return $n > 0 ? $n : 1;
}

const MP_COLUMNS = [
    'externalNo' => ['order_no', 'nomor_pesanan', 'no_pesanan', 'order id', 'order sn', 'ordersn', 'invoice', 'nomor invoice'],
    'orderDate'  => ['order_date', 'tanggal', 'tanggal pesanan', 'waktu pesanan dibuat', 'created time', 'order creation date'],
    'status'     => ['status', 'order status', 'status pesanan'],
    'buyerName'  => ['buyer', 'buyer name', 'pembeli', 'username pembeli', 'nama pembeli'],
    'shippingChargedToBuyer' => ['shipping_charged', 'ongkir dibayar pembeli', 'ongkos kirim dibayar pembeli', 'shipping fee paid by buyer'],
    'adminFee'   => ['admin_fee', 'biaya admin', 'biaya administrasi', 'biaya layanan', 'commission fee', 'transaction fee', 'platform fee'],
    'shippingCostSeller' => ['shipping_cost_seller', 'ongkir ditanggung penjual', 'subsidi ongkir', 'seller shipping fee'],
    'voucherSellerBorne' => ['voucher_seller', 'voucher ditanggung penjual', 'diskon penjual', 'seller discount', 'seller voucher'],
    'otherIncome' => ['other_income', 'pendapatan lain'],
    'otherCost'  => ['other_cost', 'biaya lain'],
    'sku'        => ['sku', 'sku produk', 'seller sku', 'nomor referensi sku'],
    'productName' => ['product_name', 'nama produk', 'product name'],
    'qty'        => ['qty', 'quantity', 'jumlah', 'kuantitas'],
    'unitPrice'  => ['unit_price', 'harga satuan', 'harga awal', 'original price', 'harga jual'],
];

// Baca file CSV menjadi array baris asosiatif (kunci sudah dinormalisasi).
function mp_read_csv(string $path): array
{
    $rows = [];
    if (($h = fopen($path, 'r')) === false) return $rows;
    $header = fgetcsv($h);
    if ($header === false) {
        fclose($h);
        return $rows;
    }
    // BOM cleanup pada kolom pertama
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }
    $keys = array_map('mp_norm_key', $header);
    while (($data = fgetcsv($h)) !== false) {
        if (count($data) === 1 && trim((string) $data[0]) === '') continue;
        $row = [];
        foreach ($keys as $i => $k) {
            $row[$k] = isset($data[$i]) ? trim((string) $data[$i]) : '';
        }
        $rows[] = $row;
    }
    fclose($h);
    return $rows;
}

// Gabungkan baris menjadi pesanan (satu baris per item -> dikelompokkan
// berdasarkan nomor pesanan). Omzet dihitung dari penjumlahan item bila
// harga satuan tersedia (lebih andal pada pesanan multi-item).
function mp_rows_to_orders(array $rows): array
{
    $byOrder = [];
    foreach ($rows as $r) {
        $no = mp_pick($r, MP_COLUMNS['externalNo']);
        if (!$no) continue;
        if (!isset($byOrder[$no])) {
            $byOrder[$no] = [
                'externalNo' => $no,
                'orderDate'  => mp_pick($r, MP_COLUMNS['orderDate']),
                'status'     => mp_pick($r, MP_COLUMNS['status']),
                'buyerName'  => mp_pick($r, MP_COLUMNS['buyerName']),
                'shippingChargedToBuyer' => mp_num(mp_pick($r, MP_COLUMNS['shippingChargedToBuyer'])),
                'adminFee'   => mp_num(mp_pick($r, MP_COLUMNS['adminFee'])),
                'shippingCostSeller' => mp_num(mp_pick($r, MP_COLUMNS['shippingCostSeller'])),
                'voucherSellerBorne' => mp_num(mp_pick($r, MP_COLUMNS['voucherSellerBorne'])),
                'otherIncome' => mp_num(mp_pick($r, MP_COLUMNS['otherIncome'])),
                'otherCost'  => mp_num(mp_pick($r, MP_COLUMNS['otherCost'])),
                'productRevenue' => 0,
                'items'      => [],
            ];
        }
        $name = mp_pick($r, MP_COLUMNS['productName']);
        if ($name) {
            $byOrder[$no]['items'][] = [
                'sku'       => mp_pick($r, MP_COLUMNS['sku']),
                'name'      => $name,
                'qty'       => mp_int(mp_pick($r, MP_COLUMNS['qty'])),
                'unitPrice' => mp_num(mp_pick($r, MP_COLUMNS['unitPrice'])),
            ];
        }
    }
    foreach ($byOrder as &$o) {
        $sum = 0;
        foreach ($o['items'] as $it) {
            $sum += $it['unitPrice'] * $it['qty'];
        }
        if ($sum > 0) $o['productRevenue'] = $sum;
    }
    return array_values($byOrder);
}

// Petakan teks status bebas ke enum internal.
function mp_map_status(?string $raw): string
{
    $s = strtolower((string) $raw);
    if (preg_match('/(batal|cancel)/', $s)) return 'CANCELLED';
    if (preg_match('/(retur|return|refund|kembali)/', $s)) return 'RETURNED';
    if (preg_match('/(selesai|complete|delivered|diterima)/', $s)) return 'COMPLETED';
    if (preg_match('/(kirim|ship|dikirim)/', $s)) return 'SHIPPED';
    if (preg_match('/(bayar|paid|lunas|baru)/', $s)) return 'PAID';
    if (preg_match('/(pending|menunggu|belum)/', $s)) return 'PENDING';
    return 'PAID';
}

function mp_parse_date(?string $raw): string
{
    if (!$raw) return date('Y-m-d H:i:s');
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
}
