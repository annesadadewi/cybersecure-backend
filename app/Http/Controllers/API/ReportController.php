<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MockTransaction;
use App\Models\UserMarketplace;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Throwable;

class ReportController extends Controller
{
    private const MONTH_MAP = ['Jan' => 0, 'Feb' => 1, 'Mar' => 2, 'Apr' => 3, 'May' => 4, 'Jun' => 5];

    private const REPORT_YEAR = 2026;

    /** Juni belum selesai — data & grafik hanya sampai tanggal ini. */
    private const JUNE_PARTIAL_END_DAY = 7;

    private function monthIndex(string $month): ?int
    {
        return self::MONTH_MAP[$month] ?? null;
    }

    private function connectedMarketplaces(int $userId): Collection
    {
        return UserMarketplace::where('user_id', $userId)
            ->where('status', 'connected')
            ->pluck('marketplace_name');
    }

    private function monthRange(string $month, int $year): array
    {
        $monthIdx = $this->monthIndex($month);
        $from = Carbon::create($year, $monthIdx + 1, 1, 0, 0, 0, 'Asia/Jakarta')->startOfDay();

        if ($month === 'Jun' && $year === self::REPORT_YEAR) {
            $to = Carbon::create($year, 6, self::JUNE_PARTIAL_END_DAY, 23, 59, 59, 'Asia/Jakarta');
        } else {
            $to = (clone $from)->endOfMonth();
        }

        return [$from, $to];
    }

    private function isLossTransaction(MockTransaction $tx): bool
    {
        if ($tx->type === MockTransaction::TYPE_REFUND) {
            return true;
        }

        $status = strtolower((string) ($tx->status ?? ''));
        $type = strtolower((string) ($tx->type ?? ''));

        if (str_contains($type, 'refund') || str_contains($status, 'refund')) {
            return true;
        }

        return in_array($status, [
            MockTransaction::STATUS_SUSPICIOUS,
            MockTransaction::STATUS_FAILED,
            MockTransaction::STATUS_PENDING,
            'anomaly',
            'risky',
        ], true);
    }

    private function isProfitTransaction(MockTransaction $tx): bool
    {
        if ($this->isLossTransaction($tx)) {
            return false;
        }

        $status = strtolower((string) ($tx->status ?? MockTransaction::STATUS_SUCCESS));

        return $status === '' || $status === MockTransaction::STATUS_SUCCESS;
    }

    private function computeMetrics(Collection $transactions): array
    {
        $financialLoss = 0;
        $profit = 0;
        $income = 0;
        $refund = 0;

        foreach ($transactions as $tx) {
            $amt = (int) $tx->amount;

            if ($this->isLossTransaction($tx)) {
                $financialLoss += $amt;
                if ($tx->type === MockTransaction::TYPE_REFUND) {
                    $refund += $amt;
                }
            }

            if ($this->isProfitTransaction($tx)) {
                $profit += $amt;
                $income += $amt;
            }
        }

        return [
            'financial_loss' => $financialLoss,
            'profit' => $profit,
            'income_total' => $income,
            'refund_total' => $refund,
            'net_total' => $profit,
            'transaction_count' => $transactions->count(),
        ];
    }

    /**
     * Grafik omzet harian dalam satu bulan (naik/turun per tanggal, dari pendapatan sukses).
     */
    private function buildDailyChart(Collection $transactions): array
    {
        $daily = [];

        foreach ($transactions as $tx) {
            if (!$this->isProfitTransaction($tx)) {
                continue;
            }
            $day = $tx->transaction_date->format('j');
            $daily[$day] = ($daily[$day] ?? 0) + (int) $tx->amount;
        }

        ksort($daily, SORT_NUMERIC);

        $chart = [];
        foreach ($daily as $day => $total) {
            $chart[] = ['name' => "Tgl {$day}", 'Omzet' => $total];
        }

        return $chart;
    }

    /**
     * Grafik omzet per bulan (Jan–Jun): tren naik/turun dari total pendapatan sukses.
     */
    private function buildMonthlyRevenueChart(int $userId, Collection $connected, int $year): array
    {
        $chart = [];

        foreach (array_keys(self::MONTH_MAP) as $month) {
            $txs = $this->transactionsForMonth($userId, $connected, $month, $year);
            $metrics = $this->computeMetrics($txs);
            $chart[] = [
                'name' => $month,
                'Omzet' => $metrics['profit'],
            ];
        }

        return $chart;
    }

    private function trendPercent(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function transactionsForMonth(int $userId, Collection $connected, string $month, int $year): Collection
    {
        [$from, $to] = $this->monthRange($month, $year);

        // Semua mock transaksi milik user (tidak dibatasi status connected — agar laporan selalu terisi)
        return MockTransaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$from, $to])
            ->orderBy('transaction_date', 'desc')
            ->get();
    }

    /**
     * Ringkasan per bulan (tab + monthly revenue: profit & kerugian per bulan).
     */
    public function monthsOverview(Request $request)
    {
        $user = $request->user();
        $year = (int) $request->get('year', self::REPORT_YEAR);
        $connected = $this->connectedMarketplaces($user->id);

        $overview = [];
        $monthlyRevenue = [];

        foreach (array_keys(self::MONTH_MAP) as $month) {
            $txs = $this->transactionsForMonth($user->id, $connected, $month, $year);
            $metrics = $this->computeMetrics($txs);

            $overview[$month] = [
                'has_data' => $txs->isNotEmpty(),
                'transaction_count' => $txs->count(),
                'profit' => $metrics['profit'],
                'financial_loss' => $metrics['financial_loss'],
            ];

            $monthlyRevenue[] = [
                'month' => $month,
                'profit' => $metrics['profit'],
                'financial_loss' => $metrics['financial_loss'],
                'income_total' => $metrics['income_total'],
                'refund_total' => $metrics['refund_total'],
                'net_total' => $metrics['net_total'],
                'transaction_count' => $metrics['transaction_count'],
                'is_partial' => $month === 'Jun' && $year === self::REPORT_YEAR,
            ];
        }

        return response()->json([
            'year' => $year,
            'months' => $overview,
            'monthly_revenue' => $monthlyRevenue,
            'revenue_chart' => $this->buildMonthlyRevenueChart($user->id, $connected, $year),
        ]);
    }

    /**
     * Data laporan bulanan: metrik, grafik omzet harian, ringkasan transaksi.
     */
    public function monthly(Request $request)
    {
        $request->validate(['month' => 'required|string|in:Jan,Feb,Mar,Apr,May,Jun']);

        $user = $request->user();
        $month = $request->month;
        $year = (int) $request->get('year', self::REPORT_YEAR);
        $connected = $this->connectedMarketplaces($user->id);

        $transactions = $this->transactionsForMonth($user->id, $connected, $month, $year);
        $metrics = $this->computeMetrics($transactions);
        $chart = $this->buildDailyChart($transactions);

        $prevMonthIdx = $this->monthIndex($month) - 1;
        $prevMonthName = $prevMonthIdx >= 0 ? array_keys(self::MONTH_MAP)[$prevMonthIdx] : null;

        $prevLoss = 0;
        $prevProfit = 0;
        if ($prevMonthName) {
            $prevTxs = $this->transactionsForMonth($user->id, $connected, $prevMonthName, $year);
            $prevMetrics = $this->computeMetrics($prevTxs);
            $prevLoss = $prevMetrics['financial_loss'];
            $prevProfit = $prevMetrics['profit'];
        }

        $lossTrend = $this->trendPercent($metrics['financial_loss'], $prevLoss);
        $profitTrend = $this->trendPercent($metrics['profit'], $prevProfit);

        return response()->json([
            'month' => $month,
            'year' => $year,
            'is_partial' => $month === 'Jun' && $year === self::REPORT_YEAR,
            'metrics' => [
                'financial_loss' => $metrics['financial_loss'],
                'profit' => $metrics['profit'],
                'loss_trend_percent' => $lossTrend,
                'profit_trend_percent' => $profitTrend,
            ],
            'chart' => $chart,
            'summary' => [
                'income_total' => $metrics['income_total'],
                'refund_total' => $metrics['refund_total'],
                'net_total' => $metrics['net_total'],
                'transaction_count' => $metrics['transaction_count'],
            ],
            'transactions' => $transactions,
        ]);
    }

    /**
     * Unduh ringkasan penjualan bulanan (PDF atau spreadsheet CSV).
     */
    public function export(Request $request)
    {
        $request->validate([
            'month' => 'required|string|in:Jan,Feb,Mar,Apr,May,Jun',
            'format' => 'required|string|in:pdf,csv,xlsx,spreadsheet',
        ]);

        $user = $request->user();
        $month = $request->month;
        $year = (int) $request->get('year', self::REPORT_YEAR);
        $format = match ($request->format) {
            'spreadsheet', 'xlsx' => 'xlsx',
            default => $request->format,
        };
        $connected = $this->connectedMarketplaces($user->id);

        $transactions = $this->transactionsForMonth($user->id, $connected, $month, $year);
        $metrics = $this->computeMetrics($transactions);

        $summary = [
            'income_total' => $metrics['income_total'],
            'refund_total' => $metrics['refund_total'],
            'net_total' => $metrics['net_total'],
            'financial_loss' => $metrics['financial_loss'],
            'profit' => $metrics['profit'],
            'transaction_count' => $metrics['transaction_count'],
        ];

        $slug = strtolower($month) . '-' . $year;

        if ($format === 'pdf') {
            return $this->exportPdf($transactions, $summary, $month, $year, $slug);
        }

        if ($format === 'xlsx') {
            return $this->exportSpreadsheet($transactions, $summary, $month, $year, $slug);
        }

        return $this->exportCsv($transactions, $summary, $month, $year, $slug);
    }

    private function exportCsv(Collection $transactions, array $summary, string $month, int $year, string $slug)
    {
        $csv = "\xEF\xBB\xBF";
        $csv .= "Ringkasan Penjualan CyberSecure — {$month} {$year}\n";
        $csv .= "Total Pemasukan,{$summary['income_total']}\n";
        $csv .= "Total Kerugian,{$summary['financial_loss']}\n";
        $csv .= "Total Pengembalian,{$summary['refund_total']}\n";
        $csv .= "Profit,{$summary['profit']}\n";
        $csv .= "Net,{$summary['net_total']}\n";
        $csv .= "Jumlah Transaksi,{$summary['transaction_count']}\n\n";
        $csv .= "Toko,Produk,Tanggal,Status,Jenis,Nominal (IDR)\n";

        foreach ($transactions as $tx) {
            $jenis = $tx->type === MockTransaction::TYPE_REFUND ? 'Pengembalian' : 'Pemasukan';
            $csv .= sprintf(
                "%s,\"%s\",%s,%s,%s,%s\n",
                $tx->marketplace_name,
                str_replace('"', '""', $tx->product_name),
                $tx->transaction_date,
                $tx->status ?? '',
                $jenis,
                $tx->amount
            );
        }

        return $this->binaryAttachmentResponse(
            $csv,
            'text/csv; charset=UTF-8',
            'ringkasan-penjualan-' . $slug . '.csv'
        );
    }

    private function exportPdf(Collection $transactions, array $summary, string $month, int $year, string $slug): Response
    {
        $filename = 'ringkasan-penjualan-' . $slug . '.pdf';

        try {
            $html = $this->buildExportHtml($transactions, $summary, $month, $year, 120);
            $pdfBinary = $this->renderPdfBinary($html);
        } catch (Throwable $e) {
            report($e);
            $pdfBinary = $this->renderPdfBinary(
                $this->buildSummaryOnlyHtml($summary, $month, $year)
            );
        }

        return $this->spaBlobResponse($pdfBinary, $filename);
    }

    private function exportSpreadsheet(Collection $transactions, array $summary, string $month, int $year, string $slug): Response
    {
        $periodNote = $month === 'Jun' && $year === self::REPORT_YEAR
            ? ' (sampai tgl ' . self::JUNE_PARTIAL_END_DAY . ' Juni)'
            : '';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        $xml .= '<Worksheet ss:Name="Ringkasan"><Table>' . "\n";

        $xml .= $this->spreadsheetRow(['Ringkasan Penjualan CyberSecure — ' . $month . ' ' . $year . $periodNote]);
        $xml .= $this->spreadsheetRow([]);
        $xml .= $this->spreadsheetRow(['Total Pemasukan', $summary['income_total']]);
        $xml .= $this->spreadsheetRow(['Total Kerugian', $summary['financial_loss']]);
        $xml .= $this->spreadsheetRow(['Total Pengembalian', $summary['refund_total']]);
        $xml .= $this->spreadsheetRow(['Profit', $summary['profit']]);
        $xml .= $this->spreadsheetRow(['Net', $summary['net_total']]);
        $xml .= $this->spreadsheetRow(['Jumlah Transaksi', $summary['transaction_count']]);
        $xml .= $this->spreadsheetRow([]);
        $xml .= $this->spreadsheetRow(['Toko', 'Produk', 'Tanggal', 'Status', 'Jenis', 'Nominal (IDR)']);

        foreach ($transactions as $tx) {
            $jenis = $tx->type === MockTransaction::TYPE_REFUND ? 'Pengembalian' : 'Pemasukan';
            $xml .= $this->spreadsheetRow([
                $tx->marketplace_name,
                $tx->product_name,
                (string) $tx->transaction_date,
                $tx->status ?? '',
                $jenis,
                (int) $tx->amount,
            ]);
        }

        $xml .= '</Table></Worksheet></Workbook>';

        return $this->binaryAttachmentResponse(
            $xml,
            'application/vnd.ms-excel; charset=UTF-8',
            'ringkasan-penjualan-' . $slug . '.xls'
        );
    }

    /**
     * Response biner untuk unduhan via Axios/fetch (SPA).
     * Tanpa Content-Disposition: attachment + tanpa application/pdf agar IDM
     * tidak memotong XHR sebelum blob diterima browser.
     */
    private function spaBlobResponse(string $binary, string $filename): Response
    {
        return response($binary, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => (string) strlen($binary),
            'X-Suggested-Filename' => $filename,
            'Cache-Control' => 'private, no-cache',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Response unduhan file langsung (CSV / XLS dengan attachment header).
     */
    private function binaryAttachmentResponse(string $binary, string $contentType, string $filename): Response
    {
        return response($binary, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($binary),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function renderPdfBinary(string $html): string
    {
        $tempDir = storage_path('app/dompdf-temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', $tempDir);
        $options->set('chroot', $tempDir);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        if ($output === '' || $output === null) {
            throw new \RuntimeException('Dompdf menghasilkan output kosong.');
        }

        return $output;
    }

    private function spreadsheetRow(array $cells): string
    {
        $row = '<Row>';
        foreach ($cells as $cell) {
            $type = is_int($cell) || is_float($cell) ? 'Number' : 'String';
            $value = htmlspecialchars((string) $cell, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $row .= '<Cell><Data ss:Type="' . $type . '">' . $value . '</Data></Cell>';
        }
        $row .= '</Row>' . "\n";

        return $row;
    }

    private function buildExportHtml(
        Collection $transactions,
        array $summary,
        string $month,
        int $year,
        int $maxRows = 0
    ): string {
        $periodNote = $month === 'Jun' && $year === self::REPORT_YEAR
            ? ' (data sampai ' . self::JUNE_PARTIAL_END_DAY . ' Juni — bulan belum selesai)'
            : '';

        $totalTx = $transactions->count();
        $listed = $maxRows > 0 ? $transactions->take($maxRows) : $transactions;
        $truncatedNote = $maxRows > 0 && $totalTx > $maxRows
            ? '<p class="meta">Menampilkan ' . $maxRows . ' dari ' . $totalTx . ' transaksi.</p>'
            : '';

        $rows = '';
        foreach ($listed as $tx) {
            $jenis = $tx->type === MockTransaction::TYPE_REFUND ? 'Pengembalian' : 'Pemasukan';
            $dateStr = $tx->transaction_date instanceof \DateTimeInterface
                ? $tx->transaction_date->format('d/m/Y H:i')
                : (string) $tx->transaction_date;

            $rows .= '<tr>'
                . '<td>' . e((string) $tx->marketplace_name) . '</td>'
                . '<td>' . e((string) $tx->product_name) . '</td>'
                . '<td>' . e($dateStr) . '</td>'
                . '<td>' . e((string) ($tx->status ?? '-')) . '</td>'
                . '<td>' . e($jenis) . '</td>'
                . '<td style="text-align:right">Rp ' . e($this->formatIdr((int) $tx->amount)) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6">Tidak ada data</td></tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Ringkasan Penjualan {$month} {$year}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;padding:24px;color:#0D2C3D}
h1{font-size:20px} .meta{font-size:12px;color:#555;margin-bottom:16px}
table{width:100%;border-collapse:collapse;font-size:11px}
th,td{border:1px solid #ccc;padding:6px} th{background:#1F5E88;color:#fff}
.totals{margin-top:16px;font-size:13px}
</style></head><body>
<h1>Ringkasan Penjualan — CyberSecure</h1>
<p class="meta">Periode: {$month} {$year}{$periodNote}</p>
{$truncatedNote}
<table>
<thead><tr><th>Toko</th><th>Produk</th><th>Tanggal</th><th>Status</th><th>Jenis</th><th>Nominal</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
<div class="totals">
<p><strong>Total Pemasukan:</strong> Rp {$this->formatIdr($summary['income_total'])}</p>
<p><strong>Total Kerugian:</strong> Rp {$this->formatIdr($summary['financial_loss'])}</p>
<p><strong>Total Pengembalian:</strong> Rp {$this->formatIdr($summary['refund_total'])}</p>
<p><strong>Profit:</strong> Rp {$this->formatIdr($summary['profit'])}</p>
<p><strong>Net:</strong> Rp {$this->formatIdr($summary['net_total'])}</p>
<p><strong>Jumlah transaksi:</strong> {$summary['transaction_count']}</p>
</div>
</body></html>
HTML;
    }

    private function buildSummaryOnlyHtml(array $summary, string $month, int $year): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:DejaVu Sans,sans-serif;padding:24px">'
            . '<h1>Ringkasan Penjualan — CyberSecure</h1>'
            . '<p>Periode: ' . e($month) . ' ' . e((string) $year) . '</p>'
            . '<p><strong>Total Pemasukan:</strong> Rp ' . e($this->formatIdr($summary['income_total'])) . '</p>'
            . '<p><strong>Total Kerugian:</strong> Rp ' . e($this->formatIdr($summary['financial_loss'])) . '</p>'
            . '<p><strong>Profit:</strong> Rp ' . e($this->formatIdr($summary['profit'])) . '</p>'
            . '<p><strong>Jumlah transaksi:</strong> ' . e((string) $summary['transaction_count']) . '</p>'
            . '</body></html>';
    }

    private function formatIdr(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }
}
