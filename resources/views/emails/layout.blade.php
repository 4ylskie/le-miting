<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { margin: 0; padding: 0; background-color: #f7f4f0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; color: #2d2a26; }
        .container { max-width: 600px; margin: 0 auto; padding: 24px 16px; }
        .card { background: #ffffff; border: 1px solid #efe6dc; border-radius: 12px; padding: 28px; }
        .brand { font-size: 20px; font-weight: 700; color: #1f2937; letter-spacing: 0.5px; }
        .brand span { color: #b3541e; }
        .h1 { font-size: 22px; font-weight: 700; margin: 16px 0 8px; }
        .muted { color: #8a8178; font-size: 13px; line-height: 1.6; }
        .enquiry-text { white-space: pre-line; margin-top: 16px; }
        .mt-8 { margin-top: 8px; }
        .mt-12 { margin-top: 12px; }
        .mt-16 { margin-top: 16px; }
        .email-heading { font-size: 15px; margin: 20px 0 8px; }
        .row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .row dt { color: #8a8178; font-weight: 600; margin: 0; }
        .row dd { margin: 0; font-weight: 600; color: #2d2a26; }
        .total { border-top: 1px solid #efe6dc; margin-top: 12px; padding-top: 12px; }
        .btn { display: inline-block; margin-top: 16px; background: #b3541e; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 14px; padding: 10px 18px; border-radius: 8px; }
        .btn-secondary { background: #ffffff; color: #b3541e; border: 1px solid #b3541e; }
        .fineprint { margin-top: 24px; font-size: 12px; color: #a69c92; line-height: 1.6; }
        .status { display: inline-block; background: #e6f4ea; color: #1e7a3e; font-size: 12px; font-weight: 600; border-radius: 999px; padding: 4px 12px; }
        @media (max-width: 480px) { .card { padding: 20px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <p class="brand">Le <span>Miting</span></p>
            <div class="h1">@yield('heading')</div>
            <div>@yield('content')</div>
            <div class="fineprint">
                Le Miting Restaurant · 46 Jalan Ampang, 50450 Kuala Lumpur ·
                +60 3-2141 9000 · {{ config('restaurant.email') }}
            </div>
        </div>
    </div>
</body>
</html>
