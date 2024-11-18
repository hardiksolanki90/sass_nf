<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

use App\Model\WorkFlowRuleModulePrd;


class WorkFlowRulePrd extends Model
{
	protected $table = "work_flow_rules";

    protected $connection = 'server_mysql';
   // use SoftDeletes, LogsActivity, Organisationid;
    
    protected $fillable = [
        'uuid', 'organisation_id', 'work_flow_rule_module_id', 'work_flow_rule_name', 'description', 'event_trigger', 'is_or', 'status'
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

    public function workFlowRuleModule()
    {
        return $this->belongsTo(WorkFlowRuleModule::class,  'work_flow_rule_module_id', 'id');
    }

    public function workFlowRuleApprovalRoles()
    {
        return $this->hasMany(WorkFlowRuleApprovalRole::class,  'work_flow_rule_id', 'id');
    }

    public function workFlowRuleApprovalUsers()
    {
        return $this->hasMany(WorkFlowRuleApprovalUser::class,  'work_flow_rule_id', 'id');
    }
}
