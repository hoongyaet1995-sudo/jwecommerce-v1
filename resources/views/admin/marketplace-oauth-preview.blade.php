<!doctype html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? 'LOCAL PREVIEW — Connect Marketplace Store' }}</title>
    @foreach($entry['css'] ?? [] as $css)
        <link rel="stylesheet" href="{{ asset('build-marketplace-preview/'.$css) }}">
    @endforeach
</head>
<body>
    <div id="root"></div>
    <script>window.__JW_ADMIN__ = {{ Illuminate\Support\Js::from($bootstrap) }};</script>
    <script type="module" src="{{ asset('build-marketplace-preview/'.$entry['file']) }}"></script>
</body>
</html>
