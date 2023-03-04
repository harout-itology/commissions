<?php

namespace App\Services;


use Carbon\Carbon;
use Illuminate\Support\Collection;


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

    public function calculations(Collection $sheet): array
    {
        $commissions = [];

        // get lines from the csv sheet
        foreach ($sheet as $row) {
            $date = $row[0];
            $userId = intval($row[1]);
            $userType = $row[2];
            $operationType = $row[3];
            $amount = floatval($row[4]);
            $currency = $row[5];

            // calculate the fee for the operation deposit
            if ($operationType === self::OPERATION_DEPOSIT) {
                $commissions[] = $this->round(amount: $amount * self::FEE_DEPOSIT);
            }
            // calculate the fee for the operation withdraw business user
            elseif ($operationType === self::OPERATION_WITHDRAW && $userType === self::USER_BUSINESS) {
                $commissions[] = $this->round(amount: $amount * self::FEE_WITHDRAW_BUSINESS);
            }
            // calculate the fee for the operation withdraw private user
            elseif ($operationType === self::OPERATION_WITHDRAW && $userType === self::USER_PRIVATE) {
                $amount = $this->getWithdrawPrivate(sheet: $sheet, userId: $userId, date: $date, amount: $amount, currency: $currency);
                $commissions[] = $this->round(amount: $amount * self::FEE_WITHDRAW_PRIVATE);
            }
            // return null for other cases
            else {
                $commissions[] = null;
            }
        }
        return $commissions;
    }

    private function getWithdrawPrivate(Collection $sheet, int $userId, string $date, float $amount, string $currency): float
    {
        // start point for each user in the same week
        $count = 0;
        $weekStart = Carbon::parse(time: $date)->startOfWeek()->format(format: 'Y-m-d');

        // convert the currency if it's not EUR
        if ($currency != self::MAIN_CURRENCY) {
            $amount = round($amount / $this->getExchange()->rates->{$currency}, 2);
        }

        // calculate the current operation for a user in the same week
        foreach ($sheet as $row) {
            if ($row[1] === $userId && $row[3] === self::OPERATION_WITHDRAW && $row[2] == self::USER_PRIVATE) {
                if ($row[0] >= $weekStart && $row[0] <= $date) {
                    $count++;
                }
            }
        }

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
            $amount = $amount * $this->getExchange()->rates->{$currency};
        }

        return $amount;
    }

    private function getExchange(): mixed
    {
        return json_decode(file_get_contents(filename: self::EXCHANGE_URL));
    }

    private function round(float $amount): string
    {
        return number_format(round($amount, 2), 2, ",", "");
    }
}


