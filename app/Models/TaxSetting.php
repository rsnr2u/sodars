<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxSetting extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'gst_percentage',
        'cgst_percentage',
        'sgst_percentage',
        'igst_percentage',
        'tds_percentage',
    ];

    protected $casts = [
        'gst_percentage' => 'decimal:2',
        'cgst_percentage' => 'decimal:2',
        'sgst_percentage' => 'decimal:2',
        'igst_percentage' => 'decimal:2',
        'tds_percentage' => 'decimal:2',
    ];
}
