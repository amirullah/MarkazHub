<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Backup data per-tenant (org-scoped) berbasis PDO — TANPA mysqldump (ramah shared
 * hosting). Menghasilkan file .sql berisi INSERT data milik organisasi tsb saja
 * (tidak membocorkan data tenant lain).
 */
class BackupService
{
    /** Tabel yang di-backup + kolom filter org. */
    private const TABLES = [
        'organizations' => 'id',
        'users' => 'organization_id',
        'suppliers' => 'organization_id',
        'stores' => 'organization_id',
        'products' => 'organization_id',
        'product_marketplace_ids' => 'organization_id',
        'orders' => 'organization_id',
        'order_items' => 'organization_id',
    ];

    public function sqlForOrg(int $orgId): string
    {
        $pdo = DB::connection()->getPdo();
        $out = "-- MarkazHub backup data (organization_id={$orgId})\n";
        $out .= "SET FOREIGN_KEY_CHECKS=0;\n";

        foreach (self::TABLES as $table => $col) {
            $rows = DB::table($table)->where($col, $orgId)->get();
            if ($rows->isEmpty()) {
                continue;
            }
            $cols = array_keys((array) $rows->first());
            $colList = '`' . implode('`,`', $cols) . '`';
            $out .= "\n-- {$table} ({$rows->count()} baris)\n";
            foreach ($rows->chunk(200) as $chunk) {
                $valuesSql = [];
                foreach ($chunk as $row) {
                    $vals = [];
                    foreach ((array) $row as $v) {
                        $vals[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
                    }
                    $valuesSql[] = '(' . implode(',', $vals) . ')';
                }
                $out .= "INSERT INTO `{$table}` ({$colList}) VALUES " . implode(',', $valuesSql) . ";\n";
            }
        }

        $out .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
        return $out;
    }
}
