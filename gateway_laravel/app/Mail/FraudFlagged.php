<?php

namespace App\Mail;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FraudFlagged extends Mailable
{
    use Queueable, SerializesModels;

    public $txn;

    /**
     * Create a new message instance.
     */
    public function __construct(Transaction $txn)
    {
        $this->txn = $txn;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('🚨 Fraudulent Transaction Alert')
                    ->markdown('emails.fraud.flagged');
    }
}