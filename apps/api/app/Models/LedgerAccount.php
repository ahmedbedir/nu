<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
{
    protected $fillable = ['code', 'name_ar', 'name_en', 'type', 'currency'];

    public const CUSTOMER_RECEIVABLE = 'customer_receivable';
    public const GATEWAY_CLEARING = 'gateway_clearing';
    public const CASH_COLLECTED = 'cash_collected_provider';
    public const COMMISSION_REVENUE = 'commission_revenue';
    public const PROVIDER_PAYABLE = 'provider_payable';
    public const REFUNDS = 'refunds';
    public const ADJUSTMENTS = 'adjustments';
    public const DISPUTE_HOLD = 'dispute_hold';
}