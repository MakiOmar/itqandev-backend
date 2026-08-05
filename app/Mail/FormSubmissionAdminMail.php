<?php

namespace App\Mail;

use App\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FormSubmissionAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $labeled
     */
    public function __construct(
        public Form $form,
        public string $emailSubject,
        public array $labeled,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $rows = '';
        foreach ($this->labeled as $label => $value) {
            $safeLabel = e((string) $label);
            $safeValue = e(is_array($value) ? json_encode($value) : (string) $value);
            $rows .= "<tr><th style=\"text-align:left;padding:4px 8px;\">{$safeLabel}</th><td style=\"padding:4px 8px;\">{$safeValue}</td></tr>";
        }

        $title = e($this->form->title);

        return "<h2>New submission: {$title}</h2><table>{$rows}</table>";
    }
}
