<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ $bootstrap['csrfToken'] }}">
    <title>JW Studio Admin</title>
    @if ($entry)
        @foreach ($entry['css'] as $css)
            <link rel="stylesheet" href="{{ asset('build-admin/'.$css) }}">
        @endforeach
    @endif
</head>
<body>
    <div id="jw-admin-root">
        <div id="root">
            @unless ($entry)
                <main role="alert">Admin assets are unavailable.</main>
            @endunless
        </div>
    </div>
    <script>
        window.__JW_ADMIN__ = @json($bootstrap);
    </script>
    @if ($entry)
        <script type="module" src="{{ asset('build-admin/'.$entry['file']) }}"></script>
    @endif
</body>
</html>
