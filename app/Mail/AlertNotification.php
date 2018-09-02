<?php

namespace App\Mail;

use App\Models\AlertTemplate;
use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use LibreNMS\Alert\AlertData;
use LibreNMS\Config;

class AlertNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $template;
    public $toEmails;

    /**
     * Create a new message instance.
     *
     * @param string $subject
     * @param AlertTemplate $template
     * @param AlertData $data
     */
    public function __construct($subject, AlertTemplate $template, AlertData $data)
    {
        $this->data = $data;
        $this->template = $template;
        $this->subject($subject);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        d_echo("Attempting to email $this->subject to: " . implode('; ', array_column($this->to, 'address')) . PHP_EOL);

        $template = ['template' => $this->template->template];
        $data = ['alert' => $this->data];

        if (Config::get('email_html', false)) {
            return $this->view($template, $data)->text($template, $data);
        } else {
            return $this->text($template, $data);
        }
    }
}
