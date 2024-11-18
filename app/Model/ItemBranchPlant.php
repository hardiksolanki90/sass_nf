<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ItemBranchPlant extends Model
{
    protected $fillable = [
        "lob_id",
        'item_id',
        'storage_location_id',
        'status',
    ];

    public function lob()
    {
        return $this->belongsTo(Lob::class, 'lob_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id');
    }

    public function storagelocation()
    {
        return $this->belongsTo(Storagelocation::class, 'storage_location_id', 'id');
    }
}
