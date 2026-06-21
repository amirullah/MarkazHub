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

        // ---------- IMPORT CSV ----------
        case 'import_orders':
            handle_import();
            redirect(url('orders'));

        default:
            redirect(url('dashboard'));
    }
}

function handle_import(): void
{
    $storeId = (int) ($_POST['store_id'] ?? 0);
    $fulfillment = in_array($_POST['fulfillment'] ?? '', FULFILLMENTS, true) ? $_POST['fulfillment'] : 'SELF';

    $store = q1('SELECT * FROM stores WHERE id = ?', [$storeId]);
    if (!$store) {
        flash('error', 'Pilih toko tujuan dulu.');
        return;
    }
    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        flash('error', 'File CSV belum dipilih.');
        return;
    }

    $rows = mp_read_csv($_FILES['file']['tmp_name']);
    $orders = mp_rows_to_orders($rows);
    if (count($orders) === 0) {
        flash('error', 'Tidak ada pesanan terbaca. Pastikan ada kolom nomor pesanan & nama produk.');
        return;
    }

    // Peta SKU -> produk (untuk HPP otomatis)
    $skus = [];
    foreach ($orders as $o) {
        foreach ($o['items'] as $it) {
            if (!empty($it['sku'])) $skus[$it['sku']] = true;
        }
    }
    $productBySku = [];
    if ($skus) {
        $in = implode(',', array_fill(0, count($skus), '?'));
        foreach (q("SELECT * FROM products WHERE sku IN ($in)", array_keys($skus)) as $p) {
            $productBySku[$p['sku']] = $p;
        }
    }

    $adminPct = (float) $store['default_admin_fee_percent'];
    $created = 0; $skipped = 0; $unmatched = [];
    $pdo = db();

    foreach ($orders as $o) {
        $exists = scalar('SELECT id FROM orders WHERE store_id = ? AND external_no = ?',
            [$storeId, $o['externalNo']]);
        if ($exists) { $skipped++; continue; }

        // Bangun item + hitung HPP / biaya dropship
        $cogs = 0; $dropship = 0; $items = [];
        foreach ($o['items'] as $it) {
            $product = (!empty($it['sku']) && isset($productBySku[$it['sku']])) ? $productBySku[$it['sku']] : null;
            if (!empty($it['sku']) && !$product) $unmatched[$it['sku']] = true;
            $unitCost = $product ? (float) $product['cost_price'] : 0;
            $cogs += $unitCost * $it['qty'];
            if ($fulfillment === 'DROPSHIP' && $product) {
                $dropship += (float) $product['dropship_cost'] * $it['qty'];
            }
            $items[] = [
                'product_id' => $product['id'] ?? null,
                'sku' => $it['sku'] ?: null,
                'name' => $it['name'],
                'qty' => $it['qty'],
                'unit_price' => $it['unitPrice'],
                'unit_cost' => $unitCost,
            ];
        }
        // Untuk dropship, modal = biaya Jakmall, HPP stok sendiri tidak dihitung.
        if ($fulfillment === 'DROPSHIP') $cogs = 0;

        $revenue = $o['productRevenue'];
        $adminFee = $o['adminFee'] > 0 ? $o['adminFee'] : ($adminPct > 0 ? $revenue * $adminPct / 100 : 0);

        $pdo->beginTransaction();
        try {
            exec_sql(
                'INSERT INTO orders (store_id, external_no, marketplace, status, fulfillment, order_date,
                    buyer_name, product_revenue, shipping_charged_to_buyer, other_income, cogs, admin_fee,
                    shipping_cost_seller, voucher_seller_borne, dropship_cost, other_cost)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$storeId, $o['externalNo'], $store['marketplace'], mp_map_status($o['status']), $fulfillment,
                    mp_parse_date($o['orderDate']), $o['buyerName'] ?: null, $revenue,
                    $o['shippingChargedToBuyer'], $o['otherIncome'], $cogs, $adminFee,
                    $o['shippingCostSeller'], $o['voucherSellerBorne'], $dropship, $o['otherCost']]
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

    $msg = "Berhasil: $created pesanan baru, $skipped dilewati (duplikat).";
    if ($unmatched) {
        $list = implode(', ', array_slice(array_keys($unmatched), 0, 8));
        $msg .= ' ⚠️ SKU belum terdaftar (HPP 0): ' . $list .
            (count($unmatched) > 8 ? ', ...' : '') . '. Tambahkan di menu Produk lalu import ulang.';
    }
    flash($created > 0 ? 'success' : 'error', $msg);
}
