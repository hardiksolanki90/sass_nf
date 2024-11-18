<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Goodreceiptnotedetail extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'good_receipt_note_detail';

    protected $fillable = [
        'uuid', 'good_receipt_note_id', 'credit_note_detail_id', 'reason_id', 'item_id', 'item_uom_id', 'qty', 'original_item_qty', 'reason'
    ];

    protected static $logAttributes = ['*'];

    protected static $logOnlyDirty = false;

    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            $model->uuid = (string) \Uuid::generate();
        });
    }

    public function item()
    {
        return $this->belongsTo(Item::class,  'item_id', 'id');
    }

    public function reasonType()
    {
        return $this->belongsTo(ReasonType::class,  'reason_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUom::class,  'item_uom_id', 'id');
    }

    public function creditNoteDetail()
    {
        return $this->belongsTo(CreditNoteDetail::class,  'credit_note_detail_id', 'id');
    }

    public function goodReceiptNote()
    {
        return $this->belongsTo(Goodreceiptnote::class,  'good_receipt_note_id', 'id');
    }
}
