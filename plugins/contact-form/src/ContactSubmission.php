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

        'whatsapp',
        'country_name',
        'country_code',

        // ✅ Website + Reference page
        'website_url',
        'reference_url',

        // ✅ Cart items (JSON)
        'cart_items',

        'ip',
        'user_agent',
        'status',
        'attempts',
        'last_error',
        'next_retry_at',
    ];

    protected $casts = [
        'next_retry_at' => 'datetime',
        'cart_items' => 'array', // ✅ auto json encode/decode
    ];
}