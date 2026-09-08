<!doctype html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOCAL READ PREVIEW — ZIP 33 Inventory</title>
    @foreach($entry['css'] ?? [] as $css)
        <link rel="stylesheet" href="{{ asset('build-marketplace33-preview/'.$css) }}">
    @endforeach
</head>
<body>
    <div id="root" data-employee-id="{{ auth()->id() }}" data-employee-name="{{ auth()->user()->name }}"></div>
    <script type="module" src="{{ asset('build-marketplace33-preview/'.$entry['file']) }}"></script>
</body>
</html>
