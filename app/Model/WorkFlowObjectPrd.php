<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

//use App\Model\WorkFlowRule;
//use App\Model\WorkFlowObjectAction;

class WorkFlowObjectPrd extends Model
{
	protected $table = "work_flow_objects";

    protected $connection = 'server_mysql';
    //use SoftDeletes, LogsActivity, Organisationid;
    
    protected $fillable = [
        'uuid', 'organisation_id', 'work_flow_rule_id', 'module_name', 'request_object', 'is_approved_all', 'is_anyone_reject', 'currently_approved_stage', 'raw_id',
    ];

    protected $casts = [
        'request_object' => 'collection',
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

    public function organisation()
    {
        return $this->belongsTo(Organisation::class,  'organisation_id', 'id');
    }

    public function workFlowRule()
    {
        return $this->belongsTo(WorkFlowRule::class,  'work_flow_rule_id', 'id');
    }

    public function workFlowObjectActions()
    {
        return $this->hasMany(WorkFlowObjectAction::class,  'work_flow_object_id', 'id');
    }
}
