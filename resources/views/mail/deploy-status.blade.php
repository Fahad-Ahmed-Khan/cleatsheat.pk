Deploy {{ $status === 'success' ? 'succeeded' : 'failed' }}

Source: {{ $source }}
Application: {{ $appUrl }}
Time (UTC): {{ now()->toIso8601String() }}

{{ $detail }}
