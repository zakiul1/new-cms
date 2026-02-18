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

        // ✅ new fields
        'lead_id',
        'payload',
        'sent_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',     // ✅ optional but useful
        'updated_at' => 'datetime',     // ✅ optional but useful
        'next_retry_at' => 'datetime',
        'sent_at' => 'datetime',
        'cart_items' => 'array',
        'payload' => 'encrypted:array', // ✅ Laravel will encrypt/decrypt automatically
    ];
}