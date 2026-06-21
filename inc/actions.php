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

        default:
            redirect(url('dashboard'));
    }
}

// Kumpulkan semua file terunggah (mendukung banyak file: files[] atau file tunggal).
function mp_collect_uploads(): array
{
    $out = [];
    if (!empty($_FILES['files']) && is_array($_FILES['files']['tmp_name'] ?? null)) {
        foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
            if ($tmp && is_uploaded_file($tmp) && ((int) ($_FILES['files']['error'][$i] ?? 1)) === UPLOAD_ERR_OK) {
                $out[] = ['tmp_name' => $tmp, 'name' => $_FILES['files']['name'][$i] ?? ''];
            }
        }
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
    $ins = 0; $upd = 0;
    $pdo->beginTransaction();
    foreach ($products as $p) {
        if (($p['sku'] ?? '') === '') continue;
        $st->execute([$p['sku'], mb_substr((string) $p['name'], 0, 255), (float) $p['cost'], (float) $p['cost'], $supId]);
        if ($st->rowCount() === 1) $ins++; else $upd++; // 1=insert, 2=update (MySQL)
    }
    $pdo->commit();
    return [$ins, $upd];
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

    // Peta SKU -> produk (untuk HPP otomatis), termasuk hasil upsert Jakmall.
    $skus = [];
    foreach ($orders as $o) {
        foreach ($o['items'] as $it) {
            if (!empty($it['sku'])) $skus[$it['sku']] = true;
        }
    }
    $productBySku = [];
    if ($skus) {
        foreach (array_chunk(array_keys($skus), 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            foreach (q("SELECT * FROM products WHERE sku IN ($in)", $chunk) as $p) {
                $productBySku[$p['sku']] = $p;
            }
        }
    }

    $adminPct = (float) $store['default_admin_fee_percent'];
    $created = 0; $skipped = 0; $nDrop = 0; $nSelf = 0; $partnerTotal = 0.0; $unmatched = []; $selfNoHpp = 0; $selfNoSku = 0;
    $pdo = db();

    foreach ($orders as $o) {
        $no = $o['externalNo'];
        $exists = scalar('SELECT id FROM orders WHERE store_id = ? AND external_no = ?', [$storeId, $no]);
        if ($exists) { $skipped++; continue; }

        // Deteksi pemenuhan per pesanan.
        $jak = $dropshipMap[$no] ?? null;
        if ($jak) {
            $ful = 'DROPSHIP';
        } elseif ($hasJakmallReport) {
            $ful = 'SELF';
        } else {
            $ful = $defaultFulfillment;
        }

        $cogs = 0; $dropship = 0; $items = [];
        foreach ($o['items'] as $it) {
            $product = (!empty($it['sku']) && isset($productBySku[$it['sku']])) ? $productBySku[$it['sku']] : null;
            if (!empty($it['sku']) && !$product) $unmatched[$it['sku']] = true;
            $unitCost = $product ? (float) $product['cost_price'] : 0;
            if ($ful === 'SELF') $cogs += $unitCost * $it['qty'];
            elseif (!$jak && $product) $dropship += (float) $product['dropship_cost'] * $it['qty']; // fallback dropship tanpa laporan
            $items[] = [
                'product_id' => $product['id'] ?? null,
                'sku' => $it['sku'] ?: null,
                'name' => $it['name'],
                'qty' => $it['qty'],
                'unit_price' => $it['unitPrice'],
                'unit_cost' => $unitCost,
            ];
        }

        $note = $o['note'] ?? null;
        if ($jak) {
            // Modal dropship riil dari Jakmall (sudah termasuk biaya mitra).
            $dropship = (float) $jak['total'];
            $partnerTotal += (float) $jak['partnerFee'];
            $note = 'Dropship Jakmall' . (!empty($jak['jakmallCode']) ? ' #' . $jak['jakmallCode'] : '') .
                ': produk Rp' . number_format($jak['productCost'], 0, ',', '.') .
                ' + mitra Rp' . number_format($jak['partnerFee'], 0, ',', '.') .
                ($jak['additional'] > 0 ? ' + tambahan Rp' . number_format($jak['additional'], 0, ',', '.') : '') .
                ' = Rp' . number_format($jak['total'], 0, ',', '.');
            $note = mb_substr($note, 0, 500);
        }
        if ($ful === 'DROPSHIP') {
            $nDrop++;
        } else {
            $nSelf++;
            if ($cogs == 0 && $items) {
                $selfNoHpp++;
                // Tak ada satu pun item ber-SKU -> kemungkinan file Order Completed belum diunggah.
                $hasSku = false;
                foreach ($items as $x) if (!empty($x['sku'])) { $hasSku = true; break; }
                if (!$hasSku) $selfNoSku++;
            }
        }

        $revenue = $o['productRevenue'];
        $verified = !empty($o['_hasIncome']) ? 1 : 0; // laba dari Total Penghasilan riil?
        $adminFee = ($o['adminFee'] ?? 0) > 0 ? $o['adminFee'] : ($adminPct > 0 ? $revenue * $adminPct / 100 : 0);

        $pdo->beginTransaction();
        try {
            exec_sql(
                'INSERT INTO orders (store_id, external_no, marketplace, status, fulfillment, order_date,
                    buyer_name, product_revenue, shipping_charged_to_buyer, other_income, cogs, admin_fee,
                    shipping_cost_seller, voucher_seller_borne, dropship_cost, other_cost, income_verified, note)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$storeId, $no, $store['marketplace'], mp_map_status($o['status'] ?? ''), $ful,
                    mp_parse_date($o['orderDate'] ?? null), ($o['buyerName'] ?? '') ?: null, $revenue,
                    $o['shippingChargedToBuyer'] ?? 0, $o['otherIncome'] ?? 0, $cogs, $adminFee,
                    $o['shippingCostSeller'] ?? 0, $o['voucherSellerBorne'] ?? 0, $dropship, $o['otherCost'] ?? 0, $verified, $note]
            );
            $orderId = (int) $pdo->lastInsertId();
            foreach ($items as $it) {
                exec_sql(
                    'INSERT INTO order_items (order_id, product_id, sku, name, qty, unit_price, unit_cost)
                     VALUES (?,?,?,?,?,?,?)',
                    [$orderId, $it['product_id'], $it['sku'], $it['name'], $it['qty'], $it['unit_price'], $it['unit_cost']]
                );
            }
            $pdo->commit();
            $created++;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $skipped++;
        }
    }

    $msg = "Pesanan: $created baru ($nDrop dropship, $nSelf packing sendiri), $skipped dilewati (duplikat).";
    if ($partnerTotal > 0) $msg .= ' Total biaya mitra Jakmall: Rp' . number_format($partnerTotal, 0, ',', '.') . '.';
    if ($selfNoHpp > 0) {
        $msg .= " ⚠️ $selfNoHpp pesanan packing-sendiri belum ber-HPP";
        if ($selfNoSku > 0) {
            $msg .= " ($selfNoSku tanpa SKU — sertakan file Order Completed periode yang sama agar SKU & HPP terbaca)";
        }
        $msg .= '.';
    }
    if ($unmatched) {
        $list = implode(', ', array_slice(array_keys($unmatched), 0, 8));
        $msg .= ' ⚠️ SKU belum ada di katalog (HPP 0): ' . $list .
            (count($unmatched) > 8 ? ', ...' : '') . '. Unggah Master Produk Jakmall lalu import ulang.';
    }
    return $msg;
}

function handle_import(): void
{
    $storeId = (int) ($_POST['store_id'] ?? 0);
    $fulfillment = in_array($_POST['fulfillment'] ?? '', FULFILLMENTS, true) ? $_POST['fulfillment'] : 'SELF';

    $uploads = mp_collect_uploads();
    if (!$uploads) {
        flash('error', 'Belum ada file dipilih. Pilih file .xlsx / .csv (boleh beberapa sekaligus).');
        return;
    }

    $orderSources = []; $jakmall = []; $dropshipMap = []; $hasJakmallReport = false; $unknown = [];
    foreach ($uploads as $u) {
        $res = mp_read_file($u['tmp_name'], $u['name']);
        if ($res['type'] === 'jakmall') {
            foreach ($res['products'] as $p) $jakmall[$p['sku']] = $p; // dedup per SKU
        } elseif ($res['type'] === 'jakmall_orders') {
            $hasJakmallReport = true;
            foreach ($res['dropship'] as $no => $info) $dropshipMap[$no] = $info;
        } elseif ($res['type'] === 'orders' && !empty($res['orders'])) {
            $orderSources[] = $res['orders'];
        } else {
            $unknown[] = $u['name'] ?: '(tanpa nama)';
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
        $store = q1('SELECT * FROM stores WHERE id = ?', [$storeId]);
        if (!$store) {
            flash('error', 'Pilih toko tujuan dulu untuk import pesanan.' . ($msgs ? ' [' . implode(' ', $msgs) . ']' : ''));
            return;
        }
        $orders = mp_merge_orders($orderSources);
        $msgs[] = import_shopee_orders($orders, $store, $dropshipMap, $hasJakmallReport, $fulfillment);
    } elseif ($hasJakmallReport && !$jakmall) {
        // Hanya laporan Jakmall tanpa file pesanan: tak ada yang bisa diimpor.
        $msgs[] = '(Belum ada file pesanan Shopee yang diunggah, jadi pesanan belum dibuat.)';
    }

    if (!$jakmall && !$orderSources && !$hasJakmallReport) {
        $list = $unknown ? ' (' . implode(', ', $unknown) . ')' : '';
        flash('error', 'Format file tidak dikenali' . $list .
            '. Didukung: Laporan Penghasilan & Order Shopee, Laporan Pesanan & Master Produk Jakmall, atau CSV pesanan.');
        return;
    }
    if ($unknown) $msgs[] = '⚠️ Dilewati (tak dikenali): ' . implode(', ', $unknown) . '.';

    flash('success', implode(' ', $msgs));
}
