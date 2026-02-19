<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $platformName;

    public function __construct($code, $platformName)
    {
        $this->code = $code;
        $this->platformName = $platformName;
    }

    public function build()
    {
        return $this->subject('Your 2FA Code')->view('emails.two_factor_code');
    }
}
