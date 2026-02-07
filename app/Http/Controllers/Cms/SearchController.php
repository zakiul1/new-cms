<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Search\SearchIndex;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        $type = $request->query('type'); // post|page|null
        $page = (int) $request->query('page', 1);

        $result = app(SearchIndex::class)->search($q, $page, 10, is_string($type) ? $type : null);

        return view('search', [
            'q' => $q,
            'type' => $type,
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 10,
        ]);
    }
}