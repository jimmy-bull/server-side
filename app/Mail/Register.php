<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Register extends Mailable
{
    use Queueable, SerializesModels;
    public $data = [];
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(array $user_password)
    {
        $this->data  = $user_password;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('noreply@49keysbanger.com')
            ->subject('Thanks for registering')
            ->view('emails.register');
    }
}
