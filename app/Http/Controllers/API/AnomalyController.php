<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AnomalyController extends Controller
{
    private function getFilteredSecurityIncidents(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return [];
        }

        // Hanya mengembalikan 1 insiden keamanan yang ditampilkan di modal tab Keamanan.
        // Insiden lain (sec-1..7) dihapus karena tidak ditampilkan di frontend
        // sehingga metrik dan diagram pie konsisten dengan apa yang terlihat user.
        return [
            [
                'id'             => 'sec-8',
                'time'           => '02:12:33 WIB',
                'activity'       => 'Akses File Sensitif Berulang',
                'location'       => '192.168.1.55 (Internal)',
                'recommendation' => 'Saran AI: Audit hak akses role dan batasi permission folder /config.',
                'risk_level'     => 'low',
                'status'         => 'Open',
            ],
        ];
    }

    private function getFilteredTransactionIncidents(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return [];
        }

        $incidents = [];

        $incidents[] = [
            'id' => 'tx-1',
            'time' => '11:08:22 WIB',
            'activity' => 'Retur Bermasalah / Potensi Fraud',
            'marketplace' => 'Shopee',
            'amount' => 1250000,
            'recommendation' => 'Saran AI: Tahan refund 48 jam, verifikasi bukti unboxing & lacak pola buyer.',
            'risk_level' => 'high',
            'status' => 'Open',
        ];
        
        $incidents[] = [
            'id' => 'tx-5',
            'time' => '07:40:02 WIB',
            'activity' => 'Pola Refund Berulang (Same Buyer)',
            'marketplace' => 'Shopee',
            'amount' => 310000,
            'recommendation' => 'Saran AI: Blacklist buyer ID sementara dan aktifkan alert otomatis.',
            'risk_level' => 'medium',
            'status' => 'Open',
        ];

        $incidents[] = [
            'id' => 'tx-2',
            'time' => '10:45:10 WIB',
            'activity' => 'Indikasi Dobel Refund',
            'marketplace' => 'Tokopedia',
            'amount' => 890000,
            'recommendation' => 'Saran AI: Cocokkan ID pesanan dengan riwayat refund sebelumnya di dashboard marketplace.',
            'risk_level' => 'high',
            'status' => 'In Review',
        ];

        $incidents[] = [
            'id' => 'tx-3',
            'time' => '09:22:44 WIB',
            'activity' => 'Transaksi Nilai Tidak Wajar',
            'marketplace' => 'Lazada',
            'amount' => 45000,
            'recommendation' => 'Saran AI: Bandingkan harga katalog dan cek apakah ada manipulasi diskon flash sale.',
            'risk_level' => 'low',
            'status' => 'Open',
        ];

        $incidents[] = [
            'id' => 'tx-4',
            'time' => '08:55:18 WIB',
            'activity' => 'Retur Bermasalah / Potensi Fraud',
            'marketplace' => 'Blibli',
            'amount' => 620000,
            'recommendation' => 'Saran AI: Eskalasi ke tim CS marketplace dengan bukti foto produk rusak palsu.',
            'risk_level' => 'high',
            'status' => 'Open',
        ];

        $incidents[] = [
            'id' => 'tx-6',
            'time' => '06:18:27 WIB',
            'activity' => 'Sinkronisasi Stok Gagal Berulang',
            'marketplace' => 'Bukalapak',
            'amount' => 0,
            'recommendation' => 'Saran AI: Re-authenticate token API toko dan jalankan sync manual.',
            'risk_level' => 'low',
            'status' => 'Open',
        ];

        return $incidents;
    }

    private function countByRisk(array $items): array
    {
        $counts = ['high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($items as $item) {
            $level = $item['risk_level'] ?? 'low';
            if (isset($counts[$level])) {
                $counts[$level]++;
            }
        }
        return $counts;
    }

    public function metrics(Request $request)
    {
        $security = $this->getFilteredSecurityIncidents($request);
        $transaction = $this->getFilteredTransactionIncidents($request);
        $all = array_merge($security, $transaction);
        $risk = $this->countByRisk($all);

        return response()->json([
            'high_risk' => $risk['high'],
            'medium_risk' => $risk['medium'],
            'low_risk' => $risk['low'],
            'total' => count($all),
            'summary_label' => "{$risk['high']} High · {$risk['medium']} Medium · {$risk['low']} Low",
            'system_integrity' => true,
            'system_integrity_status' => 'Active',
            'system_integrity_score' => 100,
        ]);
    }

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'security');

        if ($tab === 'transaction') {
            $tx = $this->getFilteredTransactionIncidents($request);
            return response()->json([
                'incidents' => $tx,
                'metrics' => $this->countByRisk($tx),
                'system_integrity' => true,
                'system_integrity_status' => 'Active',
                'system_integrity_score' => 100,
            ]);
        }

        $sec = $this->getFilteredSecurityIncidents($request);
        return response()->json([
            'incidents' => $sec,
            'metrics' => $this->countByRisk($sec),
            'system_integrity' => true,
            'system_integrity_status' => 'Active',
            'system_integrity_score' => 100,
        ]);
    }

    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|string|in:Open,In Review,Resolved,Ignored,False Positive,Mitigasi Diterapkan',
        ]);

        return response()->json([
            'message' => 'Status insiden diperbarui',
            'id' => $id,
            'status' => $request->status,
        ]);
    }
}
