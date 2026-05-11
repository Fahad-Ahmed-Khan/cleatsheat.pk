@extends('admin.orders.print._base')

@push('styles')
<style>
    /* wkhtmltopdf: force content to use full printable width */
    html, body {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0;
        padding: 0;
    }
    /* Full-width slip inside narrow PDF margins (see OrderPrintService label margins). */
    .page.label {
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    .label-inner {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }
    .muted { color: #6b7280; }
    .small { font-size: 10px; }
    .mono { font-family: ui-monospace, Consolas, monospace; }

    /* Header: use table layout — wkhtmltopdf/WebKit often ignores flex for PDF output. */
    .label-header-row {
        display: table;
        width: 100%;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 8px;
    }
    .label-header-left {
        display: table-cell;
        width: 58%;
        vertical-align: top;
        padding-right: 8px;
        box-sizing: border-box;
    }
    .label-header-left .label-brand-top {
        margin-bottom: 0;
    }
    /* Indented to match brand name (logo width + logo gap). */
    .label-header-details {
        margin-top: 4px;
        box-sizing: border-box;
        padding-left: 38px; /* 28px logo + 10px gap */
    }
    .label-subtitle {
        font-size: 11px;
        color: #6b7280;
        margin: 0 0 5px 0;
        line-height: 1.35;
    }
    .label-contact {
        font-size: 11px;
        color: #374151;
        margin: 0 0 5px 0;
        line-height: 1.35;
    }
    .label-meta {
        font-size: 11px;
        color: #6b7280;
        margin: 0;
        line-height: 1.45;
    }
    .label-meta .label-meta-gap { margin-left: 12px; }
    .label-header-barcode {
        display: table-cell;
        width: 42%;
        vertical-align: top;
        text-align: right;
        padding-left: 4px;
        box-sizing: border-box;
    }
    .label-header-barcode-inner {
        width: 100%;
        text-align: right;
    }
    .label-brand-top {
        display: table;
        width: 100%;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }
    .label-logo-wrap {
        display: table-cell;
        width: 1%;
        vertical-align: middle;
        padding-right: 10px;
        white-space: nowrap;
        width: 28px;
        height: 28px;
    }
    .label-brand-text {
        display: table-cell;
        vertical-align: middle;
        word-wrap: break-word;
    }
    .label-brand-name { font-weight: 800; line-height: 1.15; letter-spacing: -0.02em; font-size: 24px; }

    .label-address-row {
        display: table;
        width: 100%;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 8px 0;
        margin: 0 0 8px 0;
    }
    .label-panel-to,
    .label-panel-from {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 8px 10px;
        background: #fafafa;
    }
    .label-panel-title {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #111827;
        margin-bottom: 6px;
        padding-bottom: 4px;
        border-bottom: 1px solid #e5e7eb;
    }
    .label-panel-body { font-size: 11px; line-height: 1.4; word-wrap: break-word; }

    .label-order-qr-row {
        display: table;
        width: 100%;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 8px 0;
        margin: 0 0 8px 0;
    }
    .label-panel-order {
        display: table-cell;
        width: 68%;
        vertical-align: top;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 8px 10px;
        background: #fff;
    }
    .label-qr-box {
        display: table-cell;
        width: 32%;
        vertical-align: middle;
        text-align: center;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 8px 6px;
        background: #fff;
    }
    .label-qr-label { margin-bottom: 4px; }
    .label-qr-hint { margin-top: 4px; font-size: 9px; }

    .label-order-lines { max-height: 42mm; overflow: hidden; }
    .label-order-line {
        display: table;
        width: 100%;
        table-layout: fixed;
        font-size: 10px;
        line-height: 1.35;
        padding: 3px 0;
        border-bottom: 1px dotted #e5e7eb;
    }
    .label-order-line:last-child { border-bottom: none; }
    .label-order-line-main {
        display: table-cell;
        vertical-align: top;
        width: auto;
        min-width: 0;
        word-wrap: break-word;
    }
    .label-order-name { font-weight: 600; display: block; }
    .label-order-variant { font-size: 9px; display: block; }
    .label-order-qty {
        display: table-cell;
        vertical-align: top;
        text-align: right;
        white-space: nowrap;
        font-weight: 700;
        padding-left: 6px;
        width: 1%;
    }

    .label-order-total {
        margin-top: 8px;
        padding-top: 6px;
        border-top: 1px solid #e5e7eb;
        font-size: 12px;
        text-align: right;
    }

    .label-cod {
        margin-top: 4px;
        padding: 8px 12px;
        border-radius: 6px;
        font-weight: 800;
        font-size: 14px;
        text-align: center;
    }
    .label-cod--due { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; }
    .label-cod--zero { background: #f3f4f6; border: 1px solid #d1d5db; color: #374151; }

    .label-notes {
        margin-top: 8px;
        padding-top: 6px;
        border-top: 1px solid #e5e7eb;
        font-size: 9px;
        line-height: 1.4;
        color: #4b5563;
    }
    .label-notes-block + .label-notes-block { margin-top: 5px; }

    .label-barcode-block--header {
        display: inline-block;
        text-align: right;
        vertical-align: top;
        max-width: 100%;
    }
    .label-barcode-block--header table {
        width: auto !important;
        max-width: 100%;
        margin: 0 0 0 auto;
    }
    .label-barcode-text {
        font-size: 9px;
        margin-top: 2px;
        text-align: right;
    }

    .label-cut-line {
        margin-top: 10px;
        margin-bottom: 5mm;
        padding-top: 6px;
        border-top: 1px dotted #9ca3af;
        width: 100%;
        box-sizing: border-box;
    }

    /* 3-up A4: tighter */
    .label-inner--compact .label-brand-name { letter-spacing: 0; font-size: 17px; }
    .label-inner--compact .label-header-details { margin-top: 2px; }
    .label-inner--compact .label-header-details { padding-left: 28px; } /* 22px logo + 6px gap */
    .label-inner--compact .label-subtitle,
    .label-inner--compact .label-contact,
    .label-inner--compact .label-meta { font-size: 9px; }
    .label-inner--compact .label-subtitle,
    .label-inner--compact .label-contact { margin-bottom: 3px; }
    .label-inner--compact .label-meta .label-meta-gap { margin-left: 8px; }
    .label-inner--compact .label-panel-to,
    .label-inner--compact .label-panel-from,
    .label-inner--compact .label-panel-order,
    .label-inner--compact .label-qr-box { padding: 5px 6px; }
    .label-inner--compact .label-header-row { margin-bottom: 2px; }
    .label-inner--compact .label-header-left { width: 56%; padding-right: 4px; }
    .label-inner--compact .label-header-barcode { width: 44%; padding-left: 2px; }
    .label-inner--compact .label-logo-wrap { padding-right: 6px; width: 22px; height: 22px; }
    .label-inner--compact .label-barcode-text { font-size: 7px; margin-top: 1px; }
    .label-inner--compact .label-cut-line { margin-top: 6px; margin-bottom: 2.5mm; padding-top: 4px; }
    .label-inner--compact .label-panel-title { font-size: 8px; margin-bottom: 4px; }
    .label-inner--compact .label-panel-body { font-size: 9px; }
    .label-inner--compact .label-order-line { font-size: 8px; padding: 2px 0; }
    .label-inner--compact .label-order-lines { max-height: 22mm; }
    .label-inner--compact .label-order-total { font-size: 10px; margin-top: 4px; padding-top: 4px; }
    .label-inner--compact .label-cod { font-size: 11px; padding: 5px 8px; }
    .label-inner--compact .label-notes { font-size: 7px; margin-top: 4px; }

    .page-a4-three {
        width: 100%;
        max-width: 210mm;
        min-height: 297mm;
        height: 297mm;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
        page-break-after: always;
    }
    .page-a4-three:last-child { page-break-after: auto; }
    .label-slot {
        flex: 1 1 0;
        min-height: 0;
        border-bottom: 1px dashed #d1d5db;
        padding: 1mm 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        width: 100%;
        box-sizing: border-box;
    }
    .label-slot:last-child { border-bottom: none; }
    .label-slot .label-inner { flex: 1; min-height: 0; display: flex; flex-direction: column; overflow: hidden; }
    .label-slot .label-inner .label-cut-line { margin-top: auto; }
</style>
@endpush

@section('content')
    @if (($layout ?? 'one_per_page') === 'three_per_a4')
        @foreach ($orders->chunk(3) as $chunk)
            <div class="page page-a4-three">
                @foreach ($chunk as $o)
                    <div class="label-slot">
                        @include('admin.orders.print._label_content', ['o' => $o, 'sender' => $sender, 'compact' => true])
                    </div>
                @endforeach
                @for ($i = $chunk->count(); $i < 3; $i++)
                    <div class="label-slot label-slot-empty" aria-hidden="true"></div>
                @endfor
            </div>
        @endforeach
    @else
        @foreach ($orders as $o)
            <div class="page label">
                @include('admin.orders.print._label_content', ['o' => $o, 'sender' => $sender, 'compact' => false])
            </div>
        @endforeach
    @endif
@endsection
