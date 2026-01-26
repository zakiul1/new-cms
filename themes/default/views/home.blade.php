@extends('theme::layouts.app')

@section('title', 'CMS Home')

@section('content')
    <h1>It works ✅</h1>
    <p>Active theme: <strong>{{ app(\App\Cms\Themes\ThemeManager::class)->activeSlug() }}</strong></p>
@endsection
