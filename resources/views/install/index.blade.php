<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>Install Bough Digital Portal</title>
        {{-- installer.jsx imports the portal stylesheet itself. --}}
        @vite('resources/js/installer.jsx')
    </head>
    <body>
        <script>
            window.__BOUGH_INSTALLER__ = @json($installer);
        </script>
        <div id="installer"></div>
    </body>
</html>
