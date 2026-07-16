<?php

namespace App\Mail;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BackupMail extends Mailable
{
    use Queueable, SerializesModels;

    public Backup $backup;
    public string $filePath;

    public function __construct(Backup $backup, string $filePath)
    {
        $this->backup = $backup;
        $this->filePath = $filePath;
    }

    public function build(): self
    {
        return $this->subject('نسخة احتياطية — ' . $this->backup->filename)
            ->text('mails.backup')
            ->attach($this->filePath, [
                'as' => $this->backup->filename,
                'mime' => $this->backup->format === 'excel'
                    ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    : 'application/octet-stream',
            ]);
    }
}
