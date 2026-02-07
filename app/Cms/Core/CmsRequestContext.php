<?php

namespace App\Cms\Core;

use Illuminate\Http\Request;

final class CmsRequestContext
{
    public function __construct(
        public readonly Request $request,
        public readonly ?string $routeName,
        public readonly bool $isAdmin,
    ) {}
}
