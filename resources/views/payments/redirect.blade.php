<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirecting to payment…</title>
</head>
<body>
<p style="font-family: system-ui, sans-serif; padding: 1rem;">Redirecting you to the payment page…</p>
<form id="pay" method="POST" action="{{ $submitUrl }}">
    @foreach ($fields as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
</form>
<script>
    document.getElementById('pay').submit();
</script>
</body>
</html>
