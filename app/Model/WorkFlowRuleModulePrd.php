<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

use App\Model\WorkFlowRulePrd;

class WorkFlowRuleModulePrd extends Model
{    
	protected $table = "work_flow_rule_modules";

    protected $connection = 'server_mysql';
	//use LogsActivity;

    protected $fillable = [
        'name', 'type', 'status'
    ];

    protected static $logAttributes = ['*'];
    
    protected static $logOnlyDirty = false;

    public function workFlowRules()
    {
        return $this->hasMany(WorkFlowRule::class,  'work_flow_rule_module_id', 'id');
    }
}
