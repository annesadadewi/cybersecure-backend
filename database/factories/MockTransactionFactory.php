<?php

namespace Database\Factories;

use App\Models\MockTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockTransaction>
 */
class MockTransactionFactory extends Factory
{
    protected $model = MockTransaction::class;

    public function definition(): array
    {
        $marketplaces = ['Shopee', 'Tokopedia', 'Blibli'];
        $mp = fake()->randomElement($marketplaces);

        $products = [
            'Shopee' => [
                ['name' => 'Sepatu Sneakers Pria', 'amount' => 350000],
                ['name' => 'Jaket Hoodie Waterproof', 'amount' => 220000],
                ['name' => 'Kaos Cotton Combed 30s', 'amount' => 75000],
            ],
            'Tokopedia' => [
                ['name' => 'Mechanical Keyboard RGB', 'amount' => 850000],
                ['name' => 'Wireless Gaming Mouse', 'amount' => 450000],
                ['name' => 'Headphone Bluetooth Bass', 'amount' => 380000],
            ],
            'Blibli' => [
                ['name' => 'Smartwatch Fitness Tracker', 'amount' => 600000],
                ['name' => 'TWS Earbuds ANC', 'amount' => 520000],
                ['name' => 'Powerbank 20000mAh', 'amount' => 280000],
            ],
        ];

        $prod = fake()->randomElement($products[$mp]);
        $variation = fake()->randomFloat(2, -0.18, 0.22);
        $amount = (int) round($prod['amount'] * (1 + $variation));

        $roll = fake()->numberBetween(1, 100);
        if ($roll <= 14) {
            $type = MockTransaction::TYPE_REFUND;
            $status = MockTransaction::STATUS_REFUND;
        } elseif ($roll <= 22) {
            $type = MockTransaction::TYPE_INCOME;
            $status = MockTransaction::STATUS_SUSPICIOUS;
        } elseif ($roll <= 28) {
            $type = MockTransaction::TYPE_INCOME;
            $status = MockTransaction::STATUS_FAILED;
        } else {
            $type = MockTransaction::TYPE_INCOME;
            $status = MockTransaction::STATUS_SUCCESS;
        }

        return [
            'user_id' => User::factory(),
            'marketplace_name' => $mp,
            'product_name' => $prod['name'],
            'amount' => max($amount, 25000),
            'type' => $type,
            'status' => $status,
            'transaction_date' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => [
            'type' => MockTransaction::TYPE_INCOME,
            'status' => MockTransaction::STATUS_SUCCESS,
        ]);
    }

    public function refund(): static
    {
        return $this->state(fn () => [
            'type' => MockTransaction::TYPE_REFUND,
            'status' => MockTransaction::STATUS_REFUND,
        ]);
    }

    public function suspicious(): static
    {
        return $this->state(fn () => [
            'type' => MockTransaction::TYPE_INCOME,
            'status' => MockTransaction::STATUS_SUSPICIOUS,
        ]);
    }
}
