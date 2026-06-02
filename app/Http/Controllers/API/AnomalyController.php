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

        // Check active core systems from request parameters
        $hasDb = $request->query('core_db') === '1';
        $hasMail = $request->query('core_mail') === '1';
        $hasPayment = $request->query('core_payment') === '1';
        $hasWeb = $request->query('core_web') === '1';

        $hasCustomDb = false;
        $hasCustomMail = false;
        $hasCustomPayment = false;
        $hasCustomWeb = false;

        foreach ($request->query() as $key => $val) {
            if ($val === '1') {
                if (str_starts_with($key, 'custom_database_')) $hasCustomDb = true;
                if (str_starts_with($key, 'custom_email_')) $hasCustomMail = true;
                if (str_starts_with($key, 'custom_payment_')) $hasCustomPayment = true;
                if (str_starts_with($key, 'custom_web_')) $hasCustomWeb = true;
            }
        }

        $isDbActive = $hasDb || $hasCustomDb;
        $isMailActive = $hasMail || $hasCustomMail;
        $isPaymentActive = $hasPayment || $hasCustomPayment;
        $isWebActive = $hasWeb || $hasCustomWeb;

        $incidents = [];

        // sec-1, sec-2, sec-4, sec-5, sec-6 are associated with Web Portal
        if ($isWebActive) {
            $incidents[] = [
                'id' => 'sec-1',
                'time' => '10:24:15 WIB',
                'activity' => 'Brute-Force Login',
                'location' => '182.253.42.9 (Jakarta)',
                'recommendation' => 'Saran AI: Segera blokir IP permanen dan reset kata sandi akun terkait.',
                'risk_level' => 'high',
                'status' => 'Open',
            ];
            $incidents[] = [
                'id' => 'sec-2',
                'time' => '09:15:30 WIB',
                'activity' => 'Deteksi Anomali Geografis',
                'location' => '103.14.22.18 (Singapura)',
                'recommendation' => 'Saran AI: Paksa logout sesi aktif dan minta verifikasi ulang saat login.',
                'risk_level' => 'medium',
                'status' => 'Open',
            ];
            $incidents[] = [
                'id' => 'sec-4',
                'time' => '06:42:08 WIB',
                'activity' => 'Lonjakan Percobaan Login Gagal',
                'location' => '114.122.5.44 (Surabaya)',
                'recommendation' => 'Saran AI: Aktifkan rate limiting dan pantau pola credential stuffing.',
                'risk_level' => 'medium',
                'status' => 'Open',
            ];
            $incidents[] = [
                'id' => 'sec-5',
                'time' => '05:18:55 WIB',
                'activity' => 'Perubahan Konfigurasi Firewall',
                'location' => '10.0.4.22 (Internal)',
                'recommendation' => 'Saran AI: Verifikasi perubahan dengan tim infrastruktur dan rollback jika tidak sah.',
                'risk_level' => 'medium',
                'status' => 'Open',
            ];
            $incidents[] = [
                'id' => 'sec-6',
                'time' => '04:55:01 WIB',
                'activity' => 'Scan Port Tidak Diizinkan',
                'location' => '45.33.12.8 (Amerika Serikat)',
                'recommendation' => 'Saran AI: Blokir IP sumber dan aktifkan IDS signature terbaru.',
                'risk_level' => 'medium',
                'status' => 'Open',
            ];
        }

        // sec-3 is associated with Payment Gateways
        if ($isPaymentActive) {
            $incidents[] = [
                'id' => 'sec-3',
                'time' => '07:05:12 WIB',
                'activity' => 'Upaya Akses API Tidak Sah',
                'location' => '202.89.24.102 (Rusia)',
                'recommendation' => 'Saran AI: Cabut akses token sementara dan lakukan rotasi Secret Key API.',
                'risk_level' => 'high',
                'status' => 'In Review',
            ];
        }

        // sec-7 is associated with Email Server
        if ($isMailActive) {
            $incidents[] = [
                'id' => 'sec-7',
                'time' => '03:30:44 WIB',
                'activity' => 'Login dari Perangkat Tidak Dikenal',
                'location' => '36.66.201.12 (Bandung)',
                'recommendation' => 'Saran AI: Kirim OTP verifikasi dan minta konfirmasi ke pemilik akun.',
                'risk_level' => 'low',
                'status' => 'Ignored',
            ];
        }

        // sec-8 is associated with Database
        if ($isDbActive) {
            $incidents[] = [
                'id' => 'sec-8',
                'time' => '02:12:33 WIB',
                'activity' => 'Akses File Sensitif Berulang',
                'location' => '192.168.1.55 (Internal)',
                'recommendation' => 'Saran AI: Audit hak akses role dan batasi permission folder /config.',
                'risk_level' => 'low',
                'status' => 'Open',
            ];
        }

        return $incidents;
    }

    private function getFilteredTransactionIncidents(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return [];
        }

        $connectedMps = \App\Models\UserMarketplace::where('user_id', $user->id)
            ->where('status', 'connected')
            ->pluck('marketplace_name')
            ->map(fn($name) => strtolower(trim($name)))
            ->toArray();

        $incidents = [];

        if (in_array('shopee', $connectedMps)) {
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
        }

        if (in_array('tokopedia', $connectedMps)) {
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
        }

        if (in_array('lazada', $connectedMps)) {
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
        }

        if (in_array('blibli', $connectedMps)) {
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
        }

        if (in_array('bukalapak', $connectedMps)) {
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
        }

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
            ]);
        }

        $sec = $this->getFilteredSecurityIncidents($request);
        return response()->json([
            'incidents' => $sec,
            'metrics' => $this->countByRisk($sec),
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
