<?php

namespace Plugins\ContactForm;

use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    protected $table = 'contact_submissions';

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'ip',
        'user_agent',
        'status',
        'attempts',
        'last_error',
        'next_retry_at',
    ];
}