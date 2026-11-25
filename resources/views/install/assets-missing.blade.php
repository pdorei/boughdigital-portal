{{-- Shown by the installer when the front end hasn't been built yet, so it can't use @vite. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Install Bough Digital Portal</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; box-sizing: border-box; background: #fafbf8; color: #1c2730; font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 560px; background: #fff; border: 1px solid #e6e9e3; border-radius: 8px; box-shadow: 0 16px 40px rgba(20, 35, 45, 0.08); padding: 28px; }
        h1 { margin: 0 0 8px; color: #14232d; font-size: 1.6rem; line-height: 1.25; }
        p { margin: 0 0 16px; color: #66717a; }
        pre { margin: 0 0 16px; padding: 14px 16px; overflow-x: auto; background: #14232d; color: #8fcf00; border-radius: 8px; font-size: 15px; }
        code { font-family: ui-monospace, Consolas, monospace; }
        a { display: inline-block; margin-top: 8px; padding: 10px 18px; border-radius: 8px; background: #8fcf00; color: #14232d; font-weight: 700; text-decoration: none; }
        a:focus-visible { outline: 3px solid rgba(143, 207, 0, 0.35); outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <h1>One more step before installing</h1>
        <p>The portal's styles and scripts haven't been built yet, so the installer can't start. From the project folder, run:</p>
        <pre><code>npm install
npm run build</code></pre>
        <p>Deploying to hosting without Node? Run those two commands on your computer first, then upload the <code>public_html/build</code> folder with the rest of the site.</p>
        <a href="{{ url('/install') }}">I've done that, try again</a>
    </main>
</body>
</html>
