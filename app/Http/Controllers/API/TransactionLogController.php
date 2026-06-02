<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MockTransaction;
use App\Models\UserMarketplace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionLogController extends Controller
{
    private function connectedMarketplaces($userId)
    {
        return UserMarketplace::where('user_id', $userId)
            ->where('status', 'connected')
            ->pluck('marketplace_name');
    }

    private function baseQuery(Request $request)
    {
        $user = $request->user();
        $connected = $this->connectedMarketplaces($user->id);

        $query = MockTransaction::where('user_id', $user->id)
            ->whereIn('marketplace_name', $connected);

        if ($request->filled('marketplace') && $request->marketplace !== 'all') {
            $query->where('marketplace_name', $request->marketplace);
        }

        if ($request->filled('product') && $request->product !== 'all') {
            $query->where('product_name', $request->product);
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->where('transaction_date', '>=', $request->date_from . ' 00:00:00');
        }

        if ($request->filled('date_to')) {
            $query->where('transaction_date', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                    ->orWhere('marketplace_name', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function computeSummary($collection)
    {
        $income = 0;
        $refund = 0;

        foreach ($collection as $tx) {
            $amt = (int) $tx->amount;
            if ($tx->type === 'refund') {
                $refund += $amt;
            } else {
                $income += $amt;
            }
        }

        return [
            'income_total' => $income,
            'refund_total' => $refund,
            'net_total' => $income - $refund,
            'transaction_count' => $collection->count(),
        ];
    }

    /**
     * Riwayat summary penjualan — filter toko, produk, tanggal
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        $connected = $this->connectedMarketplaces($user->id);

        $transactions = $this->baseQuery($request)
            ->orderBy('transaction_date', 'desc')
            ->get();

        $allForUser = MockTransaction::where('user_id', $user->id)
            ->whereIn('marketplace_name', $connected);

        return response()->json([
            'filters' => [
                'marketplace' => $request->get('marketplace', 'all'),
                'product' => $request->get('product', 'all'),
                'type' => $request->get('type', 'all'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
            ],
            'summary' => $this->computeSummary($transactions),
            'transactions' => $transactions,
            'filter_options' => [
                'marketplaces' => (clone $allForUser)->distinct()->pluck('marketplace_name')->values(),
                'products' => (clone $allForUser)->distinct()->pluck('product_name')->values(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $statsQuery = clone $this->baseQuery($request);
        $summary = $this->computeSummary($statsQuery->get());

        $perPage = $request->get('per_page', 20);
        $transactions = $this->baseQuery($request)
            ->orderBy('transaction_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'transactions' => $transactions,
            'summary' => $summary,
            'connected_marketplaces' => $this->connectedMarketplaces($request->user()->id),
        ]);
    }

    public function show(Request $request, $id)
    {
        $transaction = MockTransaction::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json($transaction);
    }

    public function statistics(Request $request)
    {
        $user = $request->user();
        $days = $request->get('days', 7);
        $connected = $this->connectedMarketplaces($user->id);

        $dailyStats = MockTransaction::where('user_id', $user->id)
            ->whereIn('marketplace_name', $connected)
            ->where('type', '!=', 'refund')
            ->where('transaction_date', '>=', now()->subDays($days))
            ->select(
                DB::raw('DATE(transaction_date) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'daily_statistics' => $dailyStats,
        ]);
    }

    /**
     * Log transaksi terkini (dashboard widget)
     */
    public function recent(Request $request)
    {
        $limit = (int) $request->get('limit', 15);

        $transactions = $this->baseQuery($request)
            ->orderBy('transaction_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'marketplace_name' => $tx->marketplace_name,
                'product_name' => $tx->product_name,
                'amount' => (int) $tx->amount,
                'type' => $tx->type ?? 'income',
                'transaction_date' => $tx->transaction_date,
            ]);

        return response()->json($transactions);
    }

    /**
     * Export spreadsheet (CSV) atau PDF (HTML printable)
     */
    public function export(Request $request)
    {
        $format = $request->get('format', 'csv');
        $transactions = $this->baseQuery($request)
            ->orderBy('transaction_date', 'desc')
            ->get();

        $summary = $this->computeSummary($transactions);
        $filters = [
            'marketplace' => $request->get('marketplace', 'all'),
            'product' => $request->get('product', 'all'),
            'date_from' => $request->get('date_from', ''),
            'date_to' => $request->get('date_to', ''),
        ];

        if ($format === 'pdf') {
            return $this->exportPdf($transactions, $summary, $filters);
        }

        return $this->exportCsv($transactions, $summary);
    }

    private function exportCsv($transactions, $summary)
    {
        $csv = "\xEF\xBB\xBF";
        $csv .= "Ringkasan Penjualan CyberSecure\n";
        $csv .= "Total Pemasukan,{$summary['income_total']}\n";
        $csv .= "Total Pengembalian,{$summary['refund_total']}\n";
        $csv .= "Net,{$summary['net_total']}\n\n";
        $csv .= "Toko,Produk,Tanggal,Jenis,Nominal (IDR)\n";

        foreach ($transactions as $tx) {
            $jenis = $tx->type === 'refund' ? 'Pengembalian' : 'Pemasukan';
            $csv .= sprintf(
                "%s,\"%s\",%s,%s,%s\n",
                $tx->marketplace_name,
                str_replace('"', '""', $tx->product_name),
                $tx->transaction_date,
                $jenis,
                $tx->amount
            );
        }

        return response($csv, 200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="ringkasan-penjualan-' . date('Y-m-d') . '.csv"');
    }

    private function exportPdf($transactions, $summary, $filters)
    {
        $filterLine = sprintf(
            'Toko: %s | Produk: %s | Periode: %s s/d %s',
            $filters['marketplace'] === 'all' ? 'Semua' : $filters['marketplace'],
            $filters['product'] === 'all' ? 'Semua' : $filters['product'],
            $filters['date_from'] ?: '…',
            $filters['date_to'] ?: '…'
        );

        $rows = '';
        foreach ($transactions as $tx) {
            $jenis = $tx->type === 'refund' ? 'Pengembalian' : 'Pemasukan';
            $rows .= "<tr>
                <td>{$tx->marketplace_name}</td>
                <td>{$tx->product_name}</td>
                <td>{$tx->transaction_date}</td>
                <td>{$jenis}</td>
                <td style=\"text-align:right\">Rp " . number_format($tx->amount, 0, ',', '.') . "</td>
            </tr>";
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5">Tidak ada data</td></tr>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Ringkasan Penjualan</title>
<style>
body{font-family:Arial,sans-serif;padding:24px;color:#0D2C3D}
h1{font-size:20px} .meta{font-size:12px;color:#555;margin-bottom:16px}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border:1px solid #ccc;padding:8px} th{background:#1F5E88;color:#fff}
.totals{margin-top:16px;font-size:14px}
</style></head><body>
<h1>Ringkasan Penjualan — CyberSecure</h1>
<p class="meta">{$filterLine}</p>
<table>
<thead><tr><th>Toko</th><th>Produk</th><th>Tanggal</th><th>Jenis</th><th>Nominal</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
<div class="totals">
<p><strong>Total Pemasukan:</strong> Rp {$this->formatIdr($summary['income_total'])}</p>
<p><strong>Total Pengembalian:</strong> Rp {$this->formatIdr($summary['refund_total'])}</p>
<p><strong>Net:</strong> Rp {$this->formatIdr($summary['net_total'])}</p>
<p><strong>Jumlah transaksi:</strong> {$summary['transaction_count']}</p>
</div>
<script>window.onload=function(){window.print()}</script>
</body></html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function formatIdr(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }
}
