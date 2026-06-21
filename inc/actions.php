<?php
// Menangani semua aksi POST (buat/ubah/hapus/import), lalu redirect
// mengikuti pola Post/Redirect/Get agar refresh tidak mengirim ulang.

function handle_post(): void
{
    check_csrf();
    $action = $_POST['action'] ?? '';

    switch ($action) {

        // ---------- TOKO ----------
        case 'create_store':
            $name = trim($_POST['name'] ?? '');
            $mp = $_POST['marketplace'] ?? '';
            if ($name === '' || !in_array($mp, MARKETPLACES, true)) {
                flash('error', 'Nama toko & marketplace wajib diisi.');
                redirect(url('stores'));
            }
            try {
                exec_sql(
                    'INSERT INTO stores (name, marketplace, default_admin_fee_percent, note) VALUES (?,?,?,?)',
                    [$name, $mp, (float) ($_POST['default_admin_fee_percent'] ?? 0), trim($_POST['note'] ?? '') ?: null]
                );
                flash('success', 'Toko ditambahkan.');
            } catch (PDOException $e) {
                flash('error', 'Toko dengan nama itu sudah ada di marketplace ini.');
            }
            redirect(url('stores'));

        case 'update_store':
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $mp = $_POST['marketplace'] ?? '';
            if ($id <= 0 || $name === '' || !in_array($mp, MARKETPLACES, true)) {
                flash('error', 'Nama toko & marketplace wajib diisi.');
                redirect(url('stores'));
            }
            try {
                exec_sql(
                    'UPDATE stores SET name=?, marketplace=?, default_admin_fee_percent=?, note=?, active=? WHERE id=?',
                    [$name, $mp, (float) ($_POST['default_admin_fee_percent'] ?? 0),
                        trim($_POST['note'] ?? '') ?: null, isset($_POST['active']) ? 1 : 0, $id]
                );
                flash('success', 'Toko diperbarui.');
            } catch (PDOException $e) {
                flash('error', 'Gagal: nama toko itu sudah ada di marketplace tersebut.');
            }
            redirect(url('stores'));

        case 'delete_store':
            $id = (int) ($_POST['id'] ?? 0);
            $cnt = (int) scalar('SELECT COUNT(*) FROM orders WHERE store_id = ?', [$id]);
            if ($cnt > 0) {
                flash('error', "Tidak bisa dihapus, toko punya $cnt pesanan.");
            } else {
                exec_sql('DELETE FROM stores WHERE id = ?', [$id]);
                flash('success', 'Toko dihapus.');
            }
            redirect(url('stores'));

        // ---------- SUPPLIER ----------
        case 'create_supplier':
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? '';
            if ($name === '' || !in_array($type, SUPPLIER_TYPES, true)) {
                flash('error', 'Nama & tipe supplier wajib diisi.');
                redirect(url('suppliers'));
            }
            try {
                exec_sql('INSERT INTO suppliers (name, type, note) VALUES (?,?,?)',
                    [$name, $type, trim($_POST['note'] ?? '') ?: null]);
                flash('success', 'Supplier ditambahkan.');
            } catch (PDOException $e) {
                flash('error', 'Nama supplier sudah dipakai.');
            }
            redirect(url('suppliers'));

        case 'delete_supplier':
            $id = (int) ($_POST['id'] ?? 0);
            $cnt = (int) scalar('SELECT COUNT(*) FROM products WHERE supplier_id = ?', [$id]);
            if ($cnt > 0) {
                flash('error', "Tidak bisa dihapus, masih dipakai $cnt produk.");
            } else {
                exec_sql('DELETE FROM suppliers WHERE id = ?', [$id]);
                flash('success', 'Supplier dihapus.');
            }
            redirect(url('suppliers'));

        // ---------- PRODUK ----------
        case 'create_product':
            $sku = trim($_POST['sku'] ?? '');
            $name = trim($_POST['name'] ?? '');
            if ($sku === '' || $name === '') {
                flash('error', 'SKU & nama produk wajib diisi.');
                redirect(url('products'));
            }
            try {
                exec_sql(
                    'INSERT INTO products (sku, name, cost_price, dropship_cost, supplier_id) VALUES (?,?,?,?,?)',
                    [$sku, $name, (float) ($_POST['cost_price'] ?? 0), (float) ($_POST['dropship_cost'] ?? 0),
                        ($_POST['supplier_id'] ?? '') ?: null]
                );
                flash('success', 'Produk ditambahkan.');
            } catch (PDOException $e) {
                flash('error', 'SKU sudah dipakai produk lain.');
            }
            redirect(url('products'));

        case 'delete_product':
            exec_sql('UPDATE products SET active = 0 WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
            flash('success', 'Produk diarsipkan.');
            redirect(url('products'));

        // ---------- PESANAN ----------
        case 'update_order':
            $id = (int) ($_POST['id'] ?? 0);
            $fields = ['status', 'fulfillment', 'product_revenue', 'shipping_charged_to_buyer',
                'other_income', 'cogs', 'admin_fee', 'shipping_cost_seller',
                'voucher_seller_borne', 'dropship_cost', 'other_cost', 'note'];
            $vals = [];
            $vals['status'] = in_array($_POST['status'] ?? '', ORDER_STATUSES, true) ? $_POST['status'] : 'PAID';
            $vals['fulfillment'] = in_array($_POST['fulfillment'] ?? '', FULFILLMENTS, true) ? $_POST['fulfillment'] : 'SELF';
            foreach (['product_revenue', 'shipping_charged_to_buyer', 'other_income', 'cogs', 'admin_fee',
                         'shipping_cost_seller', 'voucher_seller_borne', 'dropship_cost', 'other_cost'] as $m) {
                $vals[$m] = (float) ($_POST[$m] ?? 0);
            }
            $vals['note'] = trim($_POST['note'] ?? '') ?: null;
            exec_sql(
                'UPDATE orders SET status=?, fulfillment=?, product_revenue=?, shipping_charged_to_buyer=?,
                 other_income=?, cogs=?, admin_fee=?, shipping_cost_seller=?, voucher_seller_borne=?,
                 dropship_cost=?, other_cost=?, note=? WHERE id=?',
                [$vals['status'], $vals['fulfillment'], $vals['product_revenue'], $vals['shipping_charged_to_buyer'],
                    $vals['other_income'], $vals['cogs'], $vals['admin_fee'], $vals['shipping_cost_seller'],
                    $vals['voucher_seller_borne'], $vals['dropship_cost'], $vals['other_cost'], $vals['note'], $id]
            );
            flash('success', 'Perubahan pesanan tersimpan.');
            redirect(url('order_detail', ['id' => $id]));

        case 'delete_order':
            exec_sql('DELETE FROM orders WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
            flash('success', 'Pesanan dihapus.');
            redirect(url('orders'));

        // ---------- IMPORT (XLSX/CSV) ----------
        case 'import_orders':
            handle_import();
            redirect(url('import'));

        // ---------- KOSONGKAN DATA (dengan verifikasi) ----------
        case 'clear_data':
            handle_clear_data();
            redirect(url('import'));

        default:
            redirect(url('dashboard'));
    }
}

// Kumpulkan semua file terunggah (mendukung banyak file: files[] atau file tunggal).
// Telusuri struktur $_FILES (bisa rata atau bersarang bila banyak input ber-multiple)
// dan kumpulkan tiap file yang valid.
function mp_walk_files($tmp, $name, $err, array &$out): void
{
    if (is_array($tmp)) {
        foreach ($tmp as $k => $v) {
            mp_walk_files($v, is_array($name) ? ($name[$k] ?? '') : $name,
                is_array($err) ? ($err[$k] ?? UPLOAD_ERR_NO_FILE) : $err, $out);
        }
    } elseif ($tmp && is_uploaded_file($tmp) && (int) $err === UPLOAD_ERR_OK) {
        $out[] = ['tmp_name' => $tmp, 'name' => (string) $name];
    }
}

function mp_collect_uploads(): array
{
    $out = [];
    if (!empty($_FILES['files']['tmp_name'])) {
        mp_walk_files($_FILES['files']['tmp_name'], $_FILES['files']['name'] ?? '', $_FILES['files']['error'] ?? UPLOAD_ERR_OK, $out);
    }
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $out[] = ['tmp_name' => $_FILES['file']['tmp_name'], 'name' => $_FILES['file']['name'] ?? ''];
    }
    return $out;
}

// Upsert katalog produk dari Master Produk Jakmall (SKU -> Harga sebagai modal).
function import_jakmall_products(array $products): array
{
    $pdo = db();
    $supId = scalar("SELECT id FROM suppliers WHERE type='JAKMALL' ORDER BY id LIMIT 1");
    if (!$supId) {
        exec_sql("INSERT INTO suppliers (name, type, note) VALUES ('Jakmall','JAKMALL','Dropship')");
        $supId = (int) $pdo->lastInsertId();
    }
    $st = $pdo->prepare(
        'INSERT INTO products (sku, name, cost_price, dropship_cost, supplier_id, active)
         VALUES (?,?,?,?,?,1)
         ON DUPLICATE KEY UPDATE name=VALUES(name), cost_price=VALUES(cost_price),
            dropship_cost=VALUES(dropship_cost), supplier_id=VALUES(supplier_id), active=1'
    );
    // Pemetaan ID Produk marketplace (Shopee per toko, dll) -> SKU.
    $stId = $pdo->prepare(
        'INSERT INTO product_marketplace_ids (marketplace_product_id, sku) VALUES (?,?)
         ON DUPLICATE KEY UPDATE sku=VALUES(sku)'
    );
    $ins = 0; $upd = 0;
    $pdo->beginTransaction();
    foreach ($products as $p) {
        if (($p['sku'] ?? '') === '') continue;
        $st->execute([$p['sku'], mb_substr((string) $p['name'], 0, 255), (float) $p['cost'], (float) $p['cost'], $supId]);
        if ($st->rowCount() === 1) $ins++; else $upd++; // 1=insert, 2=update (MySQL)
        foreach ($p['mpIds'] ?? [] as $mpId) {
            $stId->execute([mb_substr((string) $mpId, 0, 64), $p['sku']]);
        }
    }
    $pdo->commit();
    return [$ins, $upd];
}

// Ubah baris pesanan DB (+ itemnya) ke bentuk ternormalisasi $o, supaya bisa
// digabung-ulang dengan data import baru lewat mp_merge_orders.
function order_row_to_norm(array $ex, array $exItems): array
{
    $items = [];
    foreach ($exItems as $it) {
        $items[] = [
            'sku'        => $it['sku'] ?: null,
            'name'       => $it['name'],
            'qty'        => (int) $it['qty'],
            'qtyAssumed' => !empty($it['qty_assumed']),
            'unitPrice'  => (float) $it['unit_price'],
        ];
    }
    return [
        'externalNo' => $ex['external_no'],
        'orderDate'  => $ex['order_date'],
        'status'     => $ex['status'],
        'buyerName'  => $ex['buyer_name'],
        'shippingChargedToBuyer' => (float) $ex['shipping_charged_to_buyer'],
        'adminFee'   => (float) $ex['admin_fee'],
        'shippingCostSeller' => (float) $ex['shipping_cost_seller'],
        'voucherSellerBorne' => (float) $ex['voucher_seller_borne'],
        'otherIncome' => (float) $ex['other_income'],
        'otherCost'  => (float) $ex['other_cost'],
        'productRevenue' => (float) $ex['product_revenue'],
        'items'      => $items,
        'note'       => $ex['note'],
        '_hasIncome' => ((int) $ex['income_verified']) === 1,
    ];
}

// Masukkan pesanan ternormalisasi (hasil merge) ke DB. Jenis pemenuhan
// dideteksi per pesanan: bila No. Pesanan ada di Laporan Pesanan Jakmall
// ($dropshipMap) -> DROPSHIP (modal = Total Transaksi Jakmall, termasuk biaya
// mitra); bila laporan diunggah tapi pesanan tak ada -> SELF (HPP via SKU);
// bila laporan tak diunggah -> pakai $defaultFulfillment.
function import_shopee_orders(array $orders, array $store, array $dropshipMap, bool $hasJakmallReport, string $defaultFulfillment): string
{
    if (!$orders) return 'Tidak ada pesanan terbaca dari file pesanan.';
    $storeId = (int) $store['id'];

    // Pass 1: gabungkan tiap pesanan import dengan pesanan lama di DB (yang lebih
    // kaya menang). Kumpulkan ID Produk marketplace dari item yang belum ber-SKU
    // (item Laporan Penghasilan hanya punya ID Produk Shopee + nama).
    $prepared = []; $mpIds = []; $dupOther = 0; $dupList = [];
    foreach ($orders as $o) {
        $ex = q1('SELECT * FROM orders WHERE store_id = ? AND external_no = ?', [$storeId, $o['externalNo']]);
        // PENCEGAH DUPLIKAT: nomor pesanan unik global. Jika sudah ada di toko LAIN,
        // jangan dibuat lagi (cegah satu pesanan tercatat di >1 toko).
        if (!$ex) {
            $other = q1('SELECT o.id, s.name FROM orders o JOIN stores s ON s.id=o.store_id WHERE o.external_no = ? LIMIT 1', [$o['externalNo']]);
            if ($other) { $dupOther++; if (count($dupList) < 5) $dupList[] = $o['externalNo'] . '→' . $other['name']; continue; }
        }
        $exItems = $ex ? q('SELECT * FROM order_items WHERE order_id = ?', [$ex['id']]) : [];
        if ($ex) $o = mp_merge_orders([[order_row_to_norm($ex, $exItems)], [$o]])[0];
        foreach ($o['items'] as $it) {
            if (empty($it['sku']) && !empty($it['shopeeId'])) $mpIds[$it['shopeeId']] = true;
        }
        $prepared[] = ['ex' => $ex, 'exItems' => $exItems, 'o' => $o];
    }

    // Peta ID Produk marketplace -> SKU (dari Master Jakmall), untuk melengkapi
    // SKU item Laporan Penghasilan tanpa butuh file Order Completed.
    $skuByMpId = [];
    if ($mpIds) {
        foreach (array_chunk(array_keys($mpIds), 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            foreach (q("SELECT marketplace_product_id m, sku FROM product_marketplace_ids WHERE marketplace_product_id IN ($in)", $chunk) as $row) {
                $skuByMpId[$row['m']] = $row['sku'];
            }
        }
    }

    // Resolusi SKU item via ID Produk, lalu kumpulkan SKU final.
    $skus = [];
    foreach ($prepared as &$pp) {
        foreach ($pp['o']['items'] as &$it) {
            if (empty($it['sku']) && !empty($it['shopeeId']) && isset($skuByMpId[$it['shopeeId']])) {
                $it['sku'] = $skuByMpId[$it['shopeeId']];
            }
            if (!empty($it['sku'])) $skus[$it['sku']] = true;
        }
        unset($it);
    }
    unset($pp);

    // Peta SKU -> produk (katalog terkini, mis. dari Master Produk Jakmall).
    $productBySku = [];
    if ($skus) {
        foreach (array_chunk(array_keys($skus), 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            foreach (q("SELECT * FROM products WHERE sku IN ($in)", $chunk) as $p) {
                $productBySku[$p['sku']] = $p;
            }
        }
    }

    $created = 0; $updated = 0; $unchanged = 0; $failed = 0;
    $nDrop = 0; $nSelf = 0; $partnerTotal = 0.0; $unmatched = []; $selfNoHpp = 0; $selfNoSku = 0; $qtyAssumedN = 0;
    $pdo = db();
    $r = fn($v) => (int) round((float) $v);

    // Pass 2: hitung & tulis.
    foreach ($prepared as $pp) {
        $ex = $pp['ex']; $exItems = $pp['exItems']; $o = $pp['o'];
        $no = $o['externalNo'];

        // Deteksi pemenuhan per pesanan (jangan turunkan DROPSHIP yang sudah pasti).
        $jak = $dropshipMap[$no] ?? null;
        if ($jak) {
            $ful = 'DROPSHIP';
        } elseif ($ex && $ex['fulfillment'] === 'DROPSHIP') {
            $ful = 'DROPSHIP';
        } elseif ($hasJakmallReport) {
            $ful = 'SELF';
        } elseif ($ex) {
            $ful = $ex['fulfillment'];
        } else {
            $ful = $defaultFulfillment;
        }

        // Bangun item + HPP (cogs) dari katalog terkini.
        $cogs = 0; $items = [];
        foreach ($o['items'] as $it) {
            $product = (!empty($it['sku']) && isset($productBySku[$it['sku']])) ? $productBySku[$it['sku']] : null;
            if (!empty($it['sku']) && !$product) $unmatched[$it['sku']] = true;
            $unitCost = $product ? (float) $product['cost_price'] : 0;
            if ($ful === 'SELF') $cogs += $unitCost * $it['qty'];
            $items[] = [
                'product_id' => $product['id'] ?? null,
                'sku' => $it['sku'] ?: null,
                'name' => $it['name'],
                'qty' => $it['qty'],
                'qty_assumed' => !empty($it['qtyAssumed']) ? 1 : 0,
                'unit_price' => $it['unitPrice'],
                'unit_cost' => $unitCost,
            ];
        }

        // Biaya dropship: dari Jakmall (riil) bila ada; pertahankan yang lama bila
        // sudah dropship tanpa laporan baru; fallback master untuk dropship baru.
        $dropship = 0; $note = $o['note'] ?? null;
        if ($ful === 'DROPSHIP') {
            $cogs = 0;
            if ($jak) {
                $dropship = (float) $jak['total'];
                $partnerTotal += (float) $jak['partnerFee'];
                $note = mb_substr('Dropship Jakmall' . (!empty($jak['jakmallCode']) ? ' #' . $jak['jakmallCode'] : '') .
                    ': produk Rp' . number_format($jak['productCost'], 0, ',', '.') .
                    ' + mitra Rp' . number_format($jak['partnerFee'], 0, ',', '.') .
                    ($jak['additional'] > 0 ? ' + tambahan Rp' . number_format($jak['additional'], 0, ',', '.') : '') .
                    ' = Rp' . number_format($jak['total'], 0, ',', '.'), 0, 500);
            } elseif ($ex && $ex['fulfillment'] === 'DROPSHIP') {
                $dropship = (float) $ex['dropship_cost']; // pertahankan biaya Jakmall sebelumnya
                $note = $ex['note'];
            } else {
                foreach ($o['items'] as $it) {
                    $p = (!empty($it['sku']) && isset($productBySku[$it['sku']])) ? $productBySku[$it['sku']] : null;
                    if ($p) $dropship += (float) $p['dropship_cost'] * $it['qty'];
                }
            }
        }

        $revenue = (float) $o['productRevenue'];
        $verified = !empty($o['_hasIncome']) ? 1 : 0;
        // Biaya admin SELALU dari data (Laporan Penghasilan). Tidak ditebak pakai
        // persen. Bila Laporan Penghasilan belum diimpor -> 0 (laba belum final,
        // ditandai di daftar), dan akan terisi otomatis saat laporan diimpor.
        $adminFee = (float) ($o['adminFee'] ?? 0);
        $status = mp_map_status($o['status'] ?? '');
        // Pesanan DIBATALKAN (tak jadi dikirim) = tak ada uang → laba 0, bukan minus.
        // (Retur tetap pakai nilai riil dari Income, bisa 0 atau minus sesuai fakta.)
        if ($status === 'CANCELLED') {
            $revenue = 0.0; $adminFee = 0.0; $cogs = 0.0; $dropship = 0.0;
            $o['voucherSellerBorne'] = 0; $o['shippingCostSeller'] = 0; $o['otherCost'] = 0; $o['otherIncome'] = 0;
        }
        // Pesanan DIKEMBALIKAN (retur): barang kembali ke penjual → modal TIDAK
        // hilang. HPP & dropship tidak dihitung sebagai rugi; laba = hasil settlement
        // (umumnya 0 utk refund penuh, atau minus bila ada biaya yg tak dikembalikan).
        if ($status === 'RETURNED') {
            $cogs = 0.0; $dropship = 0.0;
        }
        $skuCount = 0; $hasAssumed = false;
        foreach ($items as $x) { if (!empty($x['sku'])) $skuCount++; if ($x['qty_assumed']) $hasAssumed = true; }
        if ($hasAssumed) $qtyAssumedN++;

        // Statistik pemenuhan & HPP (atas keadaan final, semua pesanan diproses).
        if ($ful === 'DROPSHIP') {
            $nDrop++;
        } else {
            $nSelf++;
            if ($cogs == 0 && $items) {
                $selfNoHpp++;
                if ($skuCount === 0) $selfNoSku++;
            }
        }

        // Deteksi perubahan supaya re-import tanpa data baru = tidak diutak-atik.
        if ($ex) {
            $exSkuCount = 0; $exQty = 0; $exAssumed = 0;
            foreach ($exItems as $x) { if (!empty($x['sku'])) $exSkuCount++; $exQty += (int) $x['qty']; $exAssumed += !empty($x['qty_assumed']) ? 1 : 0; }
            $newQty = 0; $newAssumed = 0;
            foreach ($items as $x) { $newQty += (int) $x['qty']; $newAssumed += $x['qty_assumed'] ? 1 : 0; }
            $same = $ex['fulfillment'] === $ful && (int) $ex['income_verified'] === $verified
                && $ex['status'] === $status
                && $r($ex['product_revenue']) === $r($revenue) && $r($ex['admin_fee']) === $r($adminFee)
                && $r($ex['cogs']) === $r($cogs) && $r($ex['dropship_cost']) === $r($dropship)
                && $r($ex['other_cost']) === $r($o['otherCost'] ?? 0) && $r($ex['voucher_seller_borne']) === $r($o['voucherSellerBorne'] ?? 0)
                && count($exItems) === count($items) && $exSkuCount === $skuCount
                && $exQty === $newQty && $exAssumed === $newAssumed;
            if ($same) { $unchanged++; continue; }
        }

        $pdo->beginTransaction();
        try {
            if ($ex) {
                exec_sql(
                    'UPDATE orders SET status=?, fulfillment=?, order_date=?, buyer_name=?, product_revenue=?,
                        shipping_charged_to_buyer=?, other_income=?, cogs=?, admin_fee=?, shipping_cost_seller=?,
                        voucher_seller_borne=?, dropship_cost=?, other_cost=?, income_verified=?, note=? WHERE id=?',
                    [$status, $ful, mp_parse_date($o['orderDate'] ?? null), ($o['buyerName'] ?? '') ?: null, $revenue,
                        $o['shippingChargedToBuyer'] ?? 0, $o['otherIncome'] ?? 0, $cogs, $adminFee,
                        $o['shippingCostSeller'] ?? 0, $o['voucherSellerBorne'] ?? 0, $dropship, $o['otherCost'] ?? 0,
                        $verified, $note, $ex['id']]
                );
                exec_sql('DELETE FROM order_items WHERE order_id = ?', [$ex['id']]);
                $orderId = (int) $ex['id'];
            } else {
                exec_sql(
                    'INSERT INTO orders (store_id, external_no, marketplace, status, fulfillment, order_date,
                        buyer_name, product_revenue, shipping_charged_to_buyer, other_income, cogs, admin_fee,
                        shipping_cost_seller, voucher_seller_borne, dropship_cost, other_cost, income_verified, note)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$storeId, $no, $store['marketplace'], $status, $ful,
                        mp_parse_date($o['orderDate'] ?? null), ($o['buyerName'] ?? '') ?: null, $revenue,
                        $o['shippingChargedToBuyer'] ?? 0, $o['otherIncome'] ?? 0, $cogs, $adminFee,
                        $o['shippingCostSeller'] ?? 0, $o['voucherSellerBorne'] ?? 0, $dropship, $o['otherCost'] ?? 0, $verified, $note]
                );
                $orderId = (int) $pdo->lastInsertId();
            }
            foreach ($items as $it) {
                exec_sql(
                    'INSERT INTO order_items (order_id, product_id, sku, name, qty, qty_assumed, unit_price, unit_cost)
                     VALUES (?,?,?,?,?,?,?,?)',
                    [$orderId, $it['product_id'], $it['sku'], $it['name'], $it['qty'], $it['qty_assumed'], $it['unit_price'], $it['unit_cost']]
                );
            }
            $pdo->commit();
            $ex ? $updated++ : $created++;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $failed++;
        }
    }

    $msg = "Pesanan: $created baru, $updated diperbarui, $unchanged tetap" . ($failed ? ", $failed gagal" : '') .
        ". ($nDrop dropship, $nSelf packing sendiri.)";
    if ($dupOther > 0) {
        $msg .= " ⚠️ $dupOther pesanan dilewati karena sudah ada di toko lain (cegah duplikat): " .
            implode(', ', $dupList) . ($dupOther > count($dupList) ? ', …' : '') . '.';
    }
    if ($partnerTotal > 0) $msg .= ' Total biaya mitra Jakmall: Rp' . number_format($partnerTotal, 0, ',', '.') . '.';
    if ($selfNoHpp > 0) {
        $msg .= " ⚠️ $selfNoHpp pesanan packing-sendiri belum ber-HPP";
        if ($selfNoSku > 0) {
            $msg .= " ($selfNoSku tanpa SKU — sertakan file Order Completed periode yang sama agar SKU & HPP terbaca)";
        }
        $msg .= '.';
    }
    if ($qtyAssumedN > 0) {
        $msg .= " ⚠️ $qtyAssumedN pesanan qty-nya diasumsikan 1 (dari Laporan Penghasilan yang tak memuat jumlah)" .
            " — impor file pesanan (Order Completed / Pesanan Selesai) periode sama untuk qty & HPP akurat.";
    }
    if ($unmatched) {
        $list = implode(', ', array_slice(array_keys($unmatched), 0, 8));
        $msg .= ' ⚠️ SKU belum ada di katalog (HPP 0): ' . $list .
            (count($unmatched) > 8 ? ', ...' : '') . '. Unggah Master Produk Jakmall lalu import ulang.';
    }
    return $msg;
}

// Kelompok marketplace untuk pencocokan file vs toko (Tokopedia & TikTok berbagi
// Seller Center, jadi satu kelompok).
function mp_market_group(string $marketplace): string
{
    return $marketplace === 'SHOPEE' ? 'SHOPEE' : 'TIKTOKTOKO';
}

// Kosongkan data (pesanan saja, atau total termasuk katalog). Wajib verifikasi
// ketik "KOSONGKAN" untuk mencegah kehilangan data tak sengaja.
function handle_clear_data(): void
{
    $confirm = strtoupper(trim($_POST['confirm'] ?? ''));
    $scope = ($_POST['scope'] ?? 'orders') === 'all' ? 'all' : 'orders';
    if ($confirm !== 'KOSONGKAN') {
        flash('error', 'Konfirmasi salah — ketik KOSONGKAN (huruf besar) untuk melanjutkan. Tidak ada data yang dihapus.');
        return;
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $nOrders = (int) scalar('SELECT COUNT(*) FROM orders');
        exec_sql('DELETE FROM order_items');
        exec_sql('DELETE FROM orders');
        if ($scope === 'all') {
            $nProd = (int) scalar('SELECT COUNT(*) FROM products');
            exec_sql('DELETE FROM product_marketplace_ids');
            exec_sql('DELETE FROM products');
            $pdo->commit();
            flash('success', "Data dikosongkan total: $nOrders pesanan & $nProd produk dihapus.");
        } else {
            $pdo->commit();
            flash('success', "Pesanan dikosongkan: $nOrders pesanan dihapus. Katalog produk tetap.");
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        flash('error', 'Gagal mengosongkan data: ' . e($e->getMessage()));
    }
}

function handle_import(): void
{
    $storeId = (int) ($_POST['store_id'] ?? 0);
    $store = $storeId ? q1('SELECT * FROM stores WHERE id = ?', [$storeId]) : null;

    $uploads = mp_collect_uploads();
    if (!$uploads) {
        flash('error', 'Belum ada file dipilih. Pilih file .xlsx / .csv (boleh beberapa sekaligus).');
        return;
    }

    $orderSources = []; $orderFiles = []; $jakmall = []; $dropshipMap = []; $hasJakmallReport = false; $unknown = [];
    foreach ($uploads as $u) {
        $res = mp_read_file($u['tmp_name'], $u['name']);
        $name = $u['name'] ?: '(tanpa nama)';
        if ($res['type'] === 'jakmall') {
            foreach ($res['products'] as $p) $jakmall[$p['sku']] = $p; // dedup per SKU
        } elseif ($res['type'] === 'jakmall_orders') {
            $hasJakmallReport = true;
            foreach ($res['dropship'] as $no => $info) $dropshipMap[$no] = $info;
        } elseif ($res['type'] === 'orders' && !empty($res['orders'])) {
            $orderSources[] = $res['orders'];
            $orderFiles[] = ['name' => $name, 'mk' => $res['marketplace'] ?? null];
        } else {
            $unknown[] = $name;
        }
    }

    // PENGAMAN: tolak bila file pesanan tak cocok dengan marketplace toko
    // (mis. file Shopee diunggah ke toko Tokopedia). Tidak ada yang diimpor.
    if ($orderSources && $store) {
        $grp = mp_market_group($store['marketplace']);
        $bad = [];
        foreach ($orderFiles as $of) {
            if ($of['mk'] !== null && $of['mk'] !== $grp) $bad[] = $of['name'];
        }
        if ($bad) {
            $fileLabel = in_array('SHOPEE', array_column(array_filter($orderFiles, fn($f) => in_array($f['name'], $bad)), 'mk'), true)
                ? 'Shopee' : 'Tokopedia/TikTok';
            flash('error', '❌ Salah marketplace: file ' . $fileLabel . ' (' . implode(', ', $bad) .
                ') tidak cocok dengan toko "' . $store['name'] . '" (' . MARKETPLACE_LABEL[$store['marketplace']] .
                '). Pilih toko yang sesuai lalu ulangi. Tidak ada data yang diimpor.');
            return;
        }
    }

    $msgs = [];
    if ($jakmall) {
        [$ins, $upd] = import_jakmall_products(array_values($jakmall));
        $msgs[] = "Master produk Jakmall: $ins baru, $upd diperbarui.";
    }
    if ($hasJakmallReport) {
        $msgs[] = 'Laporan Pesanan Jakmall: ' . count($dropshipMap) . ' pesanan dropship terdeteksi.';
    }

    if ($orderSources) {
        if (!$store) {
            flash('error', 'Pilih toko tujuan dulu untuk import pesanan.' . ($msgs ? ' [' . implode(' ', $msgs) . ']' : ''));
            return;
        }
        $orders = mp_merge_orders($orderSources);
        // Pemenuhan: dropship dideteksi otomatis dari Laporan Pesanan Jakmall;
        // sisanya dianggap Packing Sendiri (default, tanpa perlu pilihan manual).
        $msgs[] = import_shopee_orders($orders, $store, $dropshipMap, $hasJakmallReport, 'SELF');
    } elseif ($hasJakmallReport && !$jakmall) {
        $msgs[] = '(Belum ada file pesanan yang diunggah, jadi pesanan belum dibuat.)';
    }

    if (!$jakmall && !$orderSources && !$hasJakmallReport) {
        $list = $unknown ? ' (' . implode(', ', $unknown) . ')' : '';
        flash('error', 'Format file tidak dikenali' . $list .
            '. Didukung: Laporan Penghasilan & file pesanan Shopee/Tokopedia/TikTok, Laporan Pesanan & Master Produk Jakmall.');
        return;
    }
    if ($unknown) $msgs[] = '⚠️ Dilewati (tak dikenali): ' . implode(', ', $unknown) . '.';

    flash('success', implode(' ', $msgs));
}
