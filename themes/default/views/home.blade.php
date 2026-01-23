<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>CMS Home</title>
    {!! cms_assets()->renderStyles() !!}
</head>

<body>
    <h1>It works ✅</h1>
    <p>Active theme: <strong>{{ app(\App\Cms\Themes\ThemeManager::class)->activeSlug() }}</strong></p>

    {!! cms_assets()->renderScripts() !!}
</body>

</html>
