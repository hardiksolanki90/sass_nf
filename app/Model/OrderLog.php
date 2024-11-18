<?php

namespace App\Model;

use App\Traits\Organisationid;
use Illuminate\Database\Eloquent\Model;

class OrderLog extends Model
{
    use Organisationid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'order_id',
        'order_detail_id',
        'customer_id',
        'salesman_id',
        'item_id',
        'item_uom_id',
        'reason_id',
        'customer_code',
        'customer_name',
        'merchandiser_code',
        'merchandiser_name',
        'item_name',
        'item_code',
        'item_uom',
        'item_qty',
        'change_item_qty',
        'action',
        'reason'
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class,  'organisation_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class,  'order_id', 'id');
    }

    public function orderDetail()
    {
        return $this->belongsTo(OrderDetail::class,  'order_detail_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class,  'item_id', 'id');
    }

    public function itemUom()
    {
        return $this->belongsTo(ItemUom::class,  'item_uom_id', 'id');
    }

    public function reasonType()
    {
        return $this->belongsTo(ReasonType::class,  'reason_id', 'id');
    }
}
