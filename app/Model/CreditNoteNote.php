<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\Organisationid;
use App\Model\Organisation;
use App\User;

class CreditNoteNote extends Model
{
    protected $fillable = [
        'uuid', 'credit_note_id', 'salesman_id', 'item_uom_id', 'item_id', 'qty', 'credit_note_number'
    ];

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id');
    }

    public function credit()
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id', 'id');
    }

    public function salesman()
    {
        return $this->belongsTo(User::class, 'salesman_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUom::class, 'item_uom_id', 'id');
    }
}
