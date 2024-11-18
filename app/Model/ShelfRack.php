<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Model\Distribution;

class ShelfRack extends Model
{
    use SoftDeletes;

    // Set the table name
    protected $table = 'shelf_rack'; 
     
    public $timestamps = true;

    // If you want to use soft deletes
    protected $dates = ['deleted_at'];

    // Define the fillable fields (optional, if you want to use mass assignment)
    protected $fillable = ['name', 'distribution_id', 'organisation_id'];

    // Define the guarded fields (optional, used when you want to prevent mass assignment)
    // protected $guarded = ['id'];
    public function distribution()
    {
        return $this->belongsTo(Distribution::class, 'distribution_id');
    }
}
