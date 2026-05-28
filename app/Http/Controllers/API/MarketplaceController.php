<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserMarketplace;
use App\Models\MockTransaction;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    /**
     * Get user's connected marketplaces.
     */
    public function index(Request $request)
    {
        $marketplaces = UserMarketplace::where('user_id', $request->user()->id)->get();
        return response()->json($marketplaces);
    }

    /**
     * Store or update a marketplace connection.
     */
    public function store(Request $request)
    {
        $request->validate([
            'marketplace_name' => 'required|string|in:Shopee,Tokopedia,Lazada,Blibli,Bukalapak',
            'marketplace_email' => 'required|email|max:255',
            'password' => 'required|string|min:4',
        ]);

        $user = $request->user();

        // Connect or update marketplace
        $marketplace = UserMarketplace::updateOrCreate(
            [
                'user_id' => $user->id,
                'marketplace_name' => $request->marketplace_name,
            ],
            [
                'marketplace_email' => $request->marketplace_email,
                'password' => $request->password, // Stored as-is (simulated token/password)
                'status' => 'connected',
            ]
        );

        // Check and generate mock transactions if they do not exist
        $hasTransactions = MockTransaction::where('user_id', $user->id)
            ->where('marketplace_name', $request->marketplace_name)
            ->exists();

        if (!$hasTransactions) {
            $products = [
                'Shopee' => [
                    ['name' => 'Sepatu Sneakers Pria', 'amount' => 350000],
                    ['name' => 'Jaket Hoodie Waterproof', 'amount' => 220000],
                    ['name' => 'Kaos Cotton Combed 30s', 'amount' => 75000],
                    ['name' => 'Tas Ransel Laptop', 'amount' => 180000]
                ],
                'Tokopedia' => [
                    ['name' => 'Mechanical Keyboard RGB', 'amount' => 850000],
                    ['name' => 'Wireless Gaming Mouse', 'amount' => 450000],
                    ['name' => 'Monitor Gaming 24 Inch', 'amount' => 1950000]
                ],
                'Lazada' => [
                    ['name' => 'Kipas Angin Portable Mini', 'amount' => 85000],
                    ['name' => 'Casing HP Matte Premium', 'amount' => 45000]
                ],
                'Blibli' => [
                    ['name' => 'Smartwatch Fitness Tracker', 'amount' => 600000],
                    ['name' => 'TWS Earbuds ANC', 'amount' => 520000]
                ],
                'Bukalapak' => [
                    ['name' => 'SSD M.2 NVMe 1TB', 'amount' => 1100000],
                    ['name' => 'RAM DDR4 16GB Dual Channel', 'amount' => 750000]
                ]
            ];

            $mpName = $request->marketplace_name;
            $items = $products[$mpName] ?? [['name' => 'Barang Elektronik', 'amount' => 500000]];

            // Generate 12 mock transactions spread over the last 7 days
            for ($i = 0; $i < 12; $i++) {
                $prod = $items[array_rand($items)];
                $daysAgo = rand(0, 7);
                $hour = rand(8, 21);
                $minute = rand(0, 59);
                $date = now('Asia/Jakarta')->subDays($daysAgo)->setHour($hour)->setMinute($minute);
                
                // Variation +-15%
                $variation = rand(-15, 15) / 100;
                $finalAmount = (int) ($prod['amount'] * (1 + $variation));

                MockTransaction::create([
                    'user_id' => $user->id,
                    'marketplace_name' => $mpName,
                    'product_name' => $prod['name'],
                    'amount' => $finalAmount,
                    'transaction_date' => $date,
                ]);
            }
        }

        return response()->json([
            'message' => 'Marketplace connected successfully',
            'marketplace' => $marketplace
        ], 200);
    }

    /**
     * Disconnect/remove a marketplace connection.
     */
    public function destroy(Request $request, $id)
    {
        $marketplace = UserMarketplace::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        // We can either set status to disconnected or delete the connection.
        // The prompt says: "Jika status toko user adalah 'connected', backend harus bisa mengirimkan..."
        // Setting status to 'disconnected' allows toggling status in UI.
        $marketplace->update(['status' => 'disconnected']);

        return response()->json([
            'message' => 'Marketplace disconnected successfully',
            'marketplace' => $marketplace
        ]);
    }

    /**
     * Retrieve mock transactions for connected marketplaces.
     */
    public function getTransactions(Request $request)
    {
        $user = $request->user();

        // Get names of marketplaces that are currently connected
        $connectedMarketplaces = UserMarketplace::where('user_id', $user->id)
            ->where('status', 'connected')
            ->pluck('marketplace_name');

        // Fetch transactions for these connected marketplaces
        $transactions = MockTransaction::where('user_id', $user->id)
            ->whereIn('marketplace_name', $connectedMarketplaces)
            ->orderBy('transaction_date', 'asc')
            ->get();

        return response()->json($transactions);
    }
}
