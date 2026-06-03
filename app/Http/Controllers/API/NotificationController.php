<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MockTransaction;
use App\Models\UserMarketplace;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private function categoryMeta(string $category): array
    {
        return match ($category) {
            'income' => ['label' => 'Pemasukan', 'badge' => 'green', 'is_refund' => false],
            'refund' => ['label' => 'Refund', 'badge' => 'red', 'is_refund' => true],
            'cancelled' => ['label' => 'Transaksi Dibatalkan', 'badge' => 'grey', 'is_refund' => false],
            'return_request' => ['label' => 'Permintaan Retur Baru', 'badge' => 'yellow', 'is_refund' => false],
            'sync' => ['label' => 'Proses Sinkronisasi Toko', 'badge' => 'yellow', 'is_refund' => false],
            default => ['label' => 'Notifikasi', 'badge' => 'grey', 'is_refund' => false],
        };
    }

    /**
     * Cek duplikat notifikasi (portable: MySQL, SQLite, dll).
     * Window ±8 jam mengakomodasi perbedaan timezone WIB vs UTC.
     */
    private function notificationExistsForTransaction(
        int $userId,
        string $category,
        MockTransaction $tx,
        \Carbon\Carbon $txDate
    ): bool {
        $windowStart = $txDate->copy()->subHours(8);
        $windowEnd = $txDate->copy()->addHours(8);

        return UserNotification::where('user_id', $userId)
            ->where('category', $category)
            ->where('marketplace_name', $tx->marketplace_name)
            ->where('product_name', $tx->product_name)
            ->where('amount', $tx->amount)
            ->whereBetween('occurred_at', [
                $windowStart->toDateTimeString(),
                $windowEnd->toDateTimeString(),
            ])
            ->exists();
    }

    /**
     * Sinkronkan notifikasi pemasukan/refund dari transaksi (bukan cancelled).
     */
    private function syncFromTransactions($user): void
    {
        $transactions = MockTransaction::where('user_id', $user->id)
            ->orderBy('transaction_date', 'desc')
            ->limit(30)
            ->get();

        foreach ($transactions as $tx) {
            $category = $tx->type === 'refund' ? 'refund' : 'income';
            $txDate = \Carbon\Carbon::parse($tx->transaction_date);

            if ($this->notificationExistsForTransaction($user->id, $category, $tx, $txDate)) {
                continue;
            }

            $meta = $this->categoryMeta($category);
            UserNotification::create([
                'user_id' => $user->id,
                'category' => $category,
                'title' => $meta['label'],
                'message' => $category === 'refund'
                    ? "Refund: {$tx->product_name} ({$tx->marketplace_name})"
                    : "Transaksi masuk baru: {$tx->product_name} ({$tx->marketplace_name})",
                'marketplace_name' => $tx->marketplace_name,
                'product_name' => $tx->product_name,
                'amount' => $tx->amount,
                'is_read' => false,
                'occurred_at' => $txDate->toDateTimeString(),
            ]);
        }
    }

    private function buildQuery($user, ?string $typeFilter = null)
    {
        $query = UserNotification::where('user_id', $user->id)
            ->orderBy('is_read', 'asc')
            ->orderBy('occurred_at', 'desc');

        if ($typeFilter === 'income') {
            $query->where('category', 'income');
        } elseif ($typeFilter === 'refund') {
            $query->whereIn('category', ['refund', 'return_request']);
        } elseif ($typeFilter === 'cancelled') {
            $query->where('category', 'cancelled');
        }

        return $query;
    }

    private function formatNotification(UserNotification $n): array
    {
        $meta = $this->categoryMeta($n->category);
        $isRead = (bool) $n->is_read;

        return [
            'id' => $n->id,
            'category' => $n->category,
            'type' => $meta['label'],
            'badge' => $meta['badge'],
            'message' => $n->message,
            'marketplace_name' => $n->marketplace_name,
            'product_name' => $n->product_name,
            'amount' => $n->amount,
            'is_refund' => $meta['is_refund'],
            'is_read' => $isRead,
            'unread' => ! $isRead,
            'status' => $isRead ? 'read' : 'unread',
            'time_ago' => $n->occurred_at->diffForHumans(),
            'occurred_at' => $n->occurred_at,
        ];
    }

    private function listResponse($user, ?string $typeFilter = null, int $limit = 50): array
    {
        $items = $this->buildQuery($user, $typeFilter)->limit($limit)->get();
        $notifications = $items->map(fn ($n) => $this->formatNotification($n));

        return [
            'notifications' => $notifications->values(),
            'unread_count' => UserNotification::where('user_id', $user->id)->where('is_read', false)->count(),
            'total' => $notifications->count(),
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $typeFilter = $request->get('type', 'all');
        $limit = (int) $request->get('limit', 50);

        $this->syncFromTransactions($user);

        $type = $typeFilter === 'all' ? null : $typeFilter;

        return response()->json($this->listResponse($user, $type, $limit));
    }

    public function bulkMarkRead(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $user = $request->user();

        $updated = UserNotification::where('user_id', $user->id)
            ->whereIn('id', $request->ids)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $typeFilter = $request->get('type', 'all');
        $limit = (int) $request->get('limit', 50);
        $type = $typeFilter === 'all' ? null : $typeFilter;

        $payload = $this->listResponse($user, $type, $limit);

        return response()->json(array_merge([
            'message' => "{$updated} notifikasi ditandai sudah dibaca",
            'updated' => $updated,
            'marked_ids' => $request->ids,
        ], $payload));
    }

    /** @deprecated use index */
    public function transactions(Request $request)
    {
        return $this->index($request);
    }
}
