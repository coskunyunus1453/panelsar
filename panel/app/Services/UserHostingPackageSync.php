<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;

/**
 * Kullanıcı paketini `subscriptions` tablosundaki hak sağlayan kayıtlara göre günceller.
 * Stripe, PayPal, iyzico vb. webhook’ları aynı tabloya yazıp bu servisi çağırabilir;
 * `payment_provider` ile kaynağı ayırt edin.
 *
 * Stripe: `BillingController` webhook’u `stripe_subscription_id` ile yazar.
 * Diğer sağlayıcılar: `upsertFromExternalProvider()` ile `external_subscription_id` + `payment_provider` kullanın.
 */
class UserHostingPackageSync
{
    /**
     * @param  array<string, mixed>  $attributes  status, billing_cycle, amount, currency, starts_at, ends_at, cancelled_at, trial_ends_at
     */
    public function upsertFromExternalProvider(
        string $paymentProvider,
        string $externalSubscriptionId,
        int $userId,
        int $hostingPackageId,
        array $attributes = [],
    ): Subscription {
        $subscription = Subscription::query()->updateOrCreate(
            [
                'payment_provider' => $paymentProvider,
                'external_subscription_id' => $externalSubscriptionId,
            ],
            array_merge([
                'user_id' => $userId,
                'hosting_package_id' => $hostingPackageId,
                'stripe_subscription_id' => null,
            ], $attributes),
        );

        $this->syncFromSubscriptions($userId);

        return $subscription;
    }

    public function syncFromSubscriptions(int $userId): void
    {
        $user = User::query()->find($userId);
        if (! $user || $user->hosting_package_manual_override) {
            return;
        }

        $subscription = Subscription::query()
            ->where('user_id', $userId)
            ->grantingHostingPackage()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        $user->update([
            'hosting_package_id' => $subscription?->hosting_package_id,
        ]);
    }
}
