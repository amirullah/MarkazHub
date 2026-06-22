<?php
/**
 * GOLDEN TEST — pengunci kebenaran perhitungan laba (Fase 0 migrasi v2).
 *
 * Tujuan: mengabadikan angka laba v1 yang SUDAH DIAUDIT BENAR. Saat logika di-port
 * ke Laravel (ProfitService), jalankan skenario yang sama dan hasilnya WAJIB identik
 * (selisih 0). Skenario di tests/golden/scenarios.json mencerminkan kasus nyata.
 *
 * Jalankan:  php tests/golden_test.php
 * Exit code: 0 = semua lulus, 1 = ada yang gagal.
 */

require __DIR__ . '/../inc/helpers.php'; // memakai hitung_laba() v1 yang asli

// Rumus kanonik (acuan independen) — harus sama dgn hitung_laba() v1.
function canonical_profit(array $o): float
{
    $g = fn($k) => (float) ($o[$k] ?? 0);
    return ($g('product_revenue') + $g('other_income'))
        - ($g('cogs') + $g('admin_fee') + $g('shipping_cost_seller')
            + $g('voucher_seller_borne') + $g('dropship_cost') + $g('other_cost'));
}

function canonical_net(array $o): float
{
    $g = fn($k) => (float) ($o[$k] ?? 0);
    return $g('product_revenue') + $g('other_income')
        - $g('admin_fee') - $g('shipping_cost_seller') - $g('voucher_seller_borne') - $g('other_cost');
}

$data = json_decode(file_get_contents(__DIR__ . '/golden/scenarios.json'), true);
if (!$data || empty($data['scenarios'])) {
    fwrite(STDERR, "Gagal memuat scenarios.json\n");
    exit(2);
}

$pass = 0; $fail = 0;
echo "=== GOLDEN TEST: perhitungan laba ===\n\n";
foreach ($data['scenarios'] as $s) {
    $in = $s['input'];
    $expProfit = (float) $s['expected_profit'];

    // 1) Rumus kanonik harus sama dengan expected.
    $canon = round(canonical_profit($in), 2);
    // 2) hitung_laba() v1 (implementasi nyata) harus sama dengan expected.
    $v1 = round((float) hitung_laba($in)['profit'], 2);

    $okProfit = (abs($canon - $expProfit) < 0.01) && (abs($v1 - $expProfit) < 0.01);

    $okNet = true; $netInfo = '';
    if (array_key_exists('expected_net', $s)) {
        $net = round(canonical_net($in), 2);
        $okNet = abs($net - (float) $s['expected_net']) < 0.01;
        $netInfo = sprintf(' | net=%s (exp %s)', number_format($net, 0, ',', '.'), number_format((float) $s['expected_net'], 0, ',', '.'));
    }

    $ok = $okProfit && $okNet;
    $ok ? $pass++ : $fail++;
    printf("[%s] %-34s laba=%s (exp %s)%s\n",
        $ok ? 'OK ' : 'X  ',
        $s['id'],
        number_format($v1, 0, ',', '.'),
        number_format($expProfit, 0, ',', '.'),
        $netInfo
    );
    if (!$ok) {
        printf("     ^ GAGAL: kanonik=%s, hitung_laba=%s%s\n", $canon, $v1,
            $okNet ? '' : ', net tidak cocok');
    }
}

echo "\n--------------------------------------------\n";
printf("Hasil: %d lulus, %d gagal (dari %d skenario)\n", $pass, $fail, $pass + $fail);
echo $fail === 0
    ? "✓ Semua angka laba terkunci. ProfitService v2 harus reproduksi ini persis.\n"
    : "✗ Ada yang gagal — perbaiki sebelum lanjut.\n";

exit($fail === 0 ? 0 : 1);
