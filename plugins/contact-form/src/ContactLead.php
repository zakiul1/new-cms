<?php

namespace Plugins\ContactForm;

use Illuminate\Database\Eloquent\Model;

class ContactLead extends Model
{
    protected $table = 'contact_leads';

    protected $fillable = [
        'name',
        'email',
        'country_name',
        'whatsapp',
    ];
}