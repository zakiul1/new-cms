<?php

use Illuminate\Support\Facades\File;

$from = base_path('plugins/siatex/dist');
$to = public_path('plugins/siatex/dist');

// If plugin dist doesn't exist, nothing to publish
if (!File::exists($from)) {
    return;
}

// ✅ Always overwrite to avoid the “exists but empty/old” problem
if (File::exists($to)) {
    File::deleteDirectory($to);
}

File::makeDirectory($to, 0755, true);

// Copy fresh dist to public
File::copyDirectory($from, $to);