<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CommissionsService
{
    private const MAIN_CURRENCY = 'EUR';
    private const EXCHANGE_URL = 'https://developers.paysera.com/tasks/api/currency-exchange-rates';
    private const OPERATION_DEPOSIT = 'deposit';
    private const OPERATION_WITHDRAW = 'withdraw';
    private const USER_BUSINESS = 'business';
    private const USER_PRIVATE = 'private';
    private const FEE_DEPOSIT = 0.0003;
    private const FEE_WITHDRAW_BUSINESS = 0.005;
    private const FEE_WITHDRAW_PRIVATE = 0.003;
    private const FREE_LIMIT_AMOUNT = 1000;
    private const FREE_LIMIT_NUM = 3;
    private array $usersFreeLimitAmount = [];

    public function calculations(Collection $sheet): Collection
    {
        return $sheet->map(callback: function ($row) use ($sheet) {
            // get the record from the csv sheet
            [$date, $userId, $userType, $operationType, $amount, $currency] = $row;
            switch ($operationType) {
                case self::OPERATION_DEPOSIT:                   // calculate the fee for the operation deposit
                    return $this->round(amount: $amount * self::FEE_DEPOSIT);
                case self::OPERATION_WITHDRAW:
                    if ($userType === self::USER_BUSINESS) {    // calculate the fee for the withdrawal business user
                        return $this->round(amount: $amount * self::FEE_WITHDRAW_BUSINESS);
                    }
                    if ( $userType === self::USER_PRIVATE) {    // calculate the fee for the  withdrawal private user
                        $amount = $this->getWithdrawPrivate(sheet: $sheet, userId: $userId, date: $date, amount: $amount, currency: $currency);
                        return $this->round(amount: $amount * self::FEE_WITHDRAW_PRIVATE);
                    }
                    return null;
                default:
                    return null;
            }
        });
    }

    private function getWithdrawPrivate(Collection $sheet, int $userId, string $date, float $amount, string $currency): float
    {
        // convert the currency if it's not EUR
        if ($currency != self::MAIN_CURRENCY) {
            $amount /= $this->exchangeRates()->rates->{$currency};
        }

        // calculate the current operation for a user in the same week
        $count = $sheet->where(1, $userId)
            ->where(3, self::OPERATION_WITHDRAW)
            ->where(2, self::USER_PRIVATE)
            ->where(0, '>=', Carbon::parse($date)->startOfWeek()->format('Y-m-d'))
            ->where(0, '<=', $date)
            ->count();

        // reset the free limit for each user in the beginning of the week
        if ($count === 1) {
            $this->usersFreeLimitAmount[$userId] = self::FREE_LIMIT_AMOUNT;
        }

        // calculate the amount and the remaining free limit amount for a user in the same week
        if ($count <= self::FREE_LIMIT_NUM && $this->usersFreeLimitAmount[$userId] > 0) {
            $this->usersFreeLimitAmount[$userId] -= $amount;
            $amount = $this->usersFreeLimitAmount[$userId] >= 0 ? 0 : -$this->usersFreeLimitAmount[$userId];
        }

        // convert back to original currency
        if ($currency != self::MAIN_CURRENCY) {
            $amount *= $this->exchangeRates()->rates->{$currency};
        }

        return $amount;
    }

    private function round(float $amount): string
    {
        return number_format($amount, 2, ",", "");
    }

    public function exchangeRates(): mixed
    {
        return Cache::remember('exchange_rates', 3600,
            fn() => json_decode(json: file_get_contents(filename: self::EXCHANGE_URL))
        );
    }
}


