<?php

namespace Plugins\ContactForm\Console;

use Illuminate\Console\Command;
use Plugins\ContactForm\ContactSubmission;

class PruneContactSubmissionsCommand extends Command
{
    protected $signature = 'contact-form:prune';
    protected $description = 'Delete old contact submissions (sent>24h, pending>3 days).';

    public function handle(): int
    {
        // Sent: delete after 24 hours
        $sentCutoff = now()->subHours(24);

        $sentDeleted = ContactSubmission::query()
            ->where('status', 'sent')
            ->where(function ($q) use ($sentCutoff) {
                $q->whereNotNull('sent_at')->where('sent_at', '<=', $sentCutoff)
                    ->orWhere(function ($q2) use ($sentCutoff) {
                        $q2->whereNull('sent_at')->where('updated_at', '<=', $sentCutoff);
                    });
            })
            ->delete();

        // Pending: delete after 3 days
        $pendingCutoff = now()->subDays(3);

        $pendingDeleted = ContactSubmission::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', $pendingCutoff)
            ->delete();

        $this->info("Pruned: sent={$sentDeleted}, pending={$pendingDeleted}");

        return self::SUCCESS;
    }
}