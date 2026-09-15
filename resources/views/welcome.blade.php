<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OPS BIOMED MR8</title>
</head>
<body>
    <main style="font-family: system-ui, sans-serif; max-width: 900px; margin: 48px auto; padding: 0 24px;">
        <h1>OPS BIOMED MR8</h1>
        <p>Torre de Control Quirurgica para la linea Midas Rex MR8.</p>
        <p>
            <a href="{{ route('login') }}">Ingresar</a>
            @if (Route::has('register'))
                · <a href="{{ route('register') }}">Registrar usuario</a>
            @endif
        </p>
    </main>
</body>
</html>
