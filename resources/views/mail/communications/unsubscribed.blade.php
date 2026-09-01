<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Unsubscribed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f7f7f8; color: #1f2328; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: #fff; border-radius: 12px; padding: 40px; max-width: 420px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        h1 { font-size: 20px; margin-bottom: 8px; }
        p { font-size: 14px; color: #5f6368; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <h1>You've been unsubscribed</h1>
        <p>You will no longer receive bulk emails from {{ config('organization.foundation_name') }}.</p>
    </div>
</body>
</html>
