-- ============================================================
-- MarkazHub (versi PHP + MySQL) - skema database & data contoh
-- Import file ini lewat phpMyAdmin: pilih database Anda, tab "Import".
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS stores;

SET FOREIGN_KEY_CHECKS = 1;

-- Toko di tiap marketplace (satu marketplace bisa banyak toko)
CREATE TABLE stores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  marketplace ENUM('SHOPEE','TOKOPEDIA','TIKTOK') NOT NULL,
  default_admin_fee_percent DECIMAL(6,3) NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_store (marketplace, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Supplier (untuk dropship gunakan tipe JAKMALL)
CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL UNIQUE,
  type ENUM('SELF','JAKMALL','OTHER') NOT NULL DEFAULT 'SELF',
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Produk + HPP/modal, dicocokkan via SKU saat import
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  cost_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  dropship_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  supplier_id INT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prod_supplier FOREIGN KEY (supplier_id)
    REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pesanan (semua nilai uang dalam Rupiah)
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  store_id INT NOT NULL,
  external_no VARCHAR(190) NOT NULL,
  marketplace ENUM('SHOPEE','TOKOPEDIA','TIKTOK') NOT NULL,
  status ENUM('PENDING','PAID','SHIPPED','COMPLETED','CANCELLED','RETURNED') NOT NULL DEFAULT 'PAID',
  fulfillment ENUM('SELF','DROPSHIP') NOT NULL DEFAULT 'SELF',
  order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  buyer_name VARCHAR(190) NULL,
  -- pendapatan
  product_revenue DECIMAL(14,2) NOT NULL DEFAULT 0,
  shipping_charged_to_buyer DECIMAL(14,2) NOT NULL DEFAULT 0,
  other_income DECIMAL(14,2) NOT NULL DEFAULT 0,
  -- biaya
  cogs DECIMAL(14,2) NOT NULL DEFAULT 0,
  admin_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
  shipping_cost_seller DECIMAL(14,2) NOT NULL DEFAULT 0,
  voucher_seller_borne DECIMAL(14,2) NOT NULL DEFAULT 0,
  dropship_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  other_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  -- 1 = laba dari "Total Penghasilan" Shopee (uang bersih riil);
  -- 0 = estimasi (biaya admin dari persentase toko, belum ada Laporan Penghasilan)
  income_verified TINYINT(1) NOT NULL DEFAULT 0,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order (store_id, external_no),
  KEY idx_order_date (order_date),
  KEY idx_marketplace (marketplace),
  CONSTRAINT fk_order_store FOREIGN KEY (store_id)
    REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NULL,
  sku VARCHAR(190) NULL,
  name VARCHAR(255) NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_item_order FOREIGN KEY (order_id)
    REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_product FOREIGN KEY (product_id)
    REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pemetaan ID Produk marketplace (mis. Product ID Shopee per toko) -> SKU,
-- diisi dari Master Produk Jakmall. Dipakai mencocokkan item Laporan Penghasilan
-- (yang hanya punya ID Produk + nama, tanpa SKU penjual) ke katalog/HPP.
CREATE TABLE IF NOT EXISTS product_marketplace_ids (
  marketplace_product_id VARCHAR(64) NOT NULL PRIMARY KEY,
  sku VARCHAR(190) NOT NULL,
  KEY idx_pmi_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DATA CONTOH (boleh dihapus jika ingin mulai dari kosong)
-- ============================================================
INSERT INTO suppliers (id, name, type, note) VALUES
  (1, 'Jakmall', 'JAKMALL', 'Dropship'),
  (2, 'Gudang Sendiri', 'SELF', NULL);

INSERT INTO products (id, sku, name, cost_price, dropship_cost, supplier_id) VALUES
  (1, 'KAOS-PUTIH-M', 'Kaos Polos Putih M', 35000, 42000, 2),
  (2, 'TOPI-HITAM', 'Topi Baseball Hitam', 45000, 55000, 1),
  (3, 'TUMBLER-500', 'Tumbler Stainless 500ml', 60000, 72000, 1),
  (4, 'SANDAL-AS', 'Sandal Karet Anti Slip', 22000, 28000, 2);

INSERT INTO stores (id, name, marketplace, default_admin_fee_percent) VALUES
  (1, 'Berkah Store', 'SHOPEE', 6.500),
  (2, 'Berkah Official', 'TOKOPEDIA', 5.000),
  (3, 'Berkah ID', 'TIKTOK', 8.000);

INSERT INTO orders
  (id, store_id, external_no, marketplace, status, fulfillment, order_date, buyer_name,
   product_revenue, cogs, admin_fee, shipping_cost_seller, voucher_seller_borne, dropship_cost) VALUES
  (1, 1, 'SHP-2026-0001', 'SHOPEE', 'COMPLETED', 'SELF', '2026-06-20 10:00:00', 'Andi',
   150000, 70000, 9750, 0, 5000, 0),
  (2, 1, 'SHP-2026-0002', 'SHOPEE', 'COMPLETED', 'DROPSHIP', '2026-06-19 10:00:00', 'Budi',
   120000, 0, 7800, 0, 0, 72000),
  (3, 2, 'TKP-77881', 'TOKOPEDIA', 'COMPLETED', 'SELF', '2026-06-18 10:00:00', 'Citra',
   180000, 89000, 4500, 10000, 0, 0),
  (4, 3, 'TT-558822', 'TIKTOK', 'SHIPPED', 'DROPSHIP', '2026-06-17 10:00:00', 'Dewi',
   220000, 0, 9600, 0, 0, 127000);

INSERT INTO order_items (order_id, product_id, sku, name, qty, unit_price, unit_cost) VALUES
  (1, 1, 'KAOS-PUTIH-M', 'Kaos Polos Putih M', 2, 75000, 35000),
  (2, 3, 'TUMBLER-500', 'Tumbler Stainless 500ml', 1, 120000, 60000),
  (3, 2, 'TOPI-HITAM', 'Topi Baseball Hitam', 1, 90000, 45000),
  (3, 4, 'SANDAL-AS', 'Sandal Karet Anti Slip', 2, 45000, 22000),
  (4, 2, 'TOPI-HITAM', 'Topi Baseball Hitam', 1, 95000, 45000),
  (4, 3, 'TUMBLER-500', 'Tumbler Stainless 500ml', 1, 125000, 60000);
