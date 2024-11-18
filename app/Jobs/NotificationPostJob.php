<?php

namespace App\Jobs;

use App\Model\CustomerInfo;
use App\Model\SalesmanInfo;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Mail\Mailer;

class NotificationPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $obj;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->obj = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Mailer $mailer)
    {
        $subject = 'Notification';
        $email = $this->obj->email;

        $customerInfo = CustomerInfo::where('user_id', $this->obj->customer_id)->first();
        $salesmanInfo = SalesmanInfo::where('user_id', $this->obj->salesman_id)->first();

        $name = $salesmanInfo->user->getName();
        $c_name = $customerInfo->user->getName();

        $message = "$name has requested to geo approval for customer $customerInfo->customer_code - $c_name.";

        $mailer->send('emails.notification', ['data' => $this->obj, 'content' => $message], function ($message) use ($email, $subject) {
            $message->to($email)
                ->subject($subject);
        });
    }
}
