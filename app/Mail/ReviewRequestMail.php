<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReviewRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $review;
    public $reviewLink;

    public function __construct($review, $reviewLink)
    {
        $this->review = $review;
        $this->reviewLink = $reviewLink;
    }

    public function build()
    {
        return $this
            ->subject('We’d Love Your Review!')
            ->view('emails.review-request');
    }
}
