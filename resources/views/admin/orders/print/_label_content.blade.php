@php
    $compact = $compact ?? false;
    $packing = $packing ?? false;
    $barcodeW = $compact ? 1.8 : 2.2;
    $barcodeH = $compact ? 44 : 56;
    $qrSize = $compact ? 80 : 100;

    $brandName = config('app.name', 'Tryino');

    $senderPhone = trim((string) ($sender['phone'] ?? ''));
    $senderEmail = trim((string) ($sender['email'] ?? ''));
    $supportPhone = trim((string) config('store.support_phone', ''));

    $contactBits = [];
    if ($supportPhone !== '') {
        $contactBits[] = 'Support: '.$supportPhone;
    }
    if ($senderPhone !== '' && $senderPhone !== $supportPhone) {
        $contactBits[] = 'Shipper: '.$senderPhone;
    }
    if ($senderEmail !== '') {
        $contactBits[] = $senderEmail;
    }
    $contactLine = implode(' · ', $contactBits);

    $isCod = $o['is_cod'] ?? false;
    $codAmount = (float) ($o['cod_label_amount'] ?? 0);
    $codLabelText = $isCod
        ? 'COD: PKR '.number_format($codAmount, 0)
        : 'COD: PKR 0';

    $ship = $o['shipping_address'] ?? [];
    $bill = $o['billing_address'] ?? [];
    $billDiffers = ($bill['line1'] ?? '') !== ($ship['line1'] ?? '')
        || ($bill['city'] ?? '') !== ($ship['city'] ?? '');

    $returnPolicy = config('store.return_policy_summary', '');
    $requiredNotes = config('store.shipping_label_required_notes', '');

    $items = collect($o['items'] ?? []);

    $subtitleText = $packing ? 'Packing slip' : 'Shipping label';
@endphp
<div class="label-inner {{ $compact ? 'label-inner--compact' : '' }}">
    {{-- Col 1: brand, Shipping label, support, order meta | Col 2: barcode --}}
    <div class="label-header-row">
        <div class="label-header-left">
            <div class="label-brand-top">
                <div class="label-logo-wrap">@include('admin.orders.print._tryino_logo')</div>
                <div class="label-brand-text">
                    <div class="label-brand-name">{{ $brandName }}</div>
                </div>
            </div>
            <div class="label-header-details">
                <div class="label-subtitle">{{ $subtitleText }}</div>
                @if ($contactLine !== '')
                    <div class="label-contact">{{ $contactLine }}</div>
                @endif
                <div class="label-meta">
                    <span><strong>Order</strong> <span class="mono">{{ $o['order_number'] }}</span></span>
                    <span class="label-meta-gap"><strong>Date</strong> {{ $o['created_at'] ?? '—' }}</span>
                    @if ($o['shipment'] && $o['shipment']['tracking_number'])
                        <span class="label-meta-gap"><strong>Tracking</strong> <span class="mono">{{ $o['shipment']['tracking_number'] }}</span></span>
                    @endif
                </div>
            </div>
        </div>
        <div class="label-header-barcode">
            {{-- Inner wrapper: wkhtmltopdf aligns inline-block barcode to the right reliably --}}
            <div class="label-header-barcode-inner">
                <div class="label-barcode-block label-barcode-block--header">
                    {!! \Milon\Barcode\Facades\DNS1DFacade::getBarcodeHTML($o['barcode_text'], 'C128', $barcodeW, $barcodeH) !!}
                    <div class="label-barcode-text mono">{{ $o['barcode_text'] }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row: TO (left) | FROM (right) --}}
    <div class="label-address-row">
        <div class="label-panel label-panel-to">
            <div class="label-panel-title">To (recipient)</div>
            <div class="label-panel-body">
                <strong>{{ $o['customer_name'] ?: '—' }}</strong>
                @if (($o['customer_phone'] ?? '') !== '')<br />{{ $o['customer_phone'] }}@endif
                @if (!empty($ship['line1']))<br />{{ $ship['line1'] }}@endif
                @if (!empty($ship['area']) || !empty($ship['city']))
                    <br />{{ $ship['area'] ?? '' }}{{ !empty($ship['area']) && !empty($ship['city']) ? ', ' : '' }}{{ $ship['city'] ?? '' }}
                @endif
                @if (!empty($ship['postal_code']))<br />{{ $ship['postal_code'] }}@endif
                @if ($billDiffers)
                    <br /><span class="muted"><strong>Bill to:</strong> {{ $bill['line1'] ?? '' }}, {{ $bill['city'] ?? '' }}</span>
                @endif
            </div>
        </div>
        <div class="label-panel label-panel-from">
            <div class="label-panel-title">From (shipper)</div>
            <div class="label-panel-body">
                <strong>{{ $sender['business_name'] ?? $brandName }}</strong>
                @if (!empty($sender['contact_name']))<br />{{ $sender['contact_name'] }}@endif
                @if ($senderPhone !== '')<br />{{ $senderPhone }}@endif
                @if ($senderEmail !== '')<br />{{ $senderEmail }}@endif
                @if (!empty($sender['line1']))<br />{{ $sender['line1'] }}@endif
                @if (!empty($sender['area']))<br />{{ $sender['area'] }}@endif
                @if (!empty($sender['city']))<br />{{ $sender['city'] }}@endif
                @if (!empty($sender['postal_code']))<br />{{ $sender['postal_code'] }}@endif
            </div>
        </div>
    </div>

    {{-- Row: Order summary (left) | QR (right) --}}
    <div class="label-order-qr-row">
        <div class="label-panel label-panel-order">
            <div class="label-panel-title">Order</div>
            <div class="label-order-lines">
                @forelse ($items as $it)
                    <div class="label-order-line">
                        <div class="label-order-line-main">
                            <span class="label-order-name">{{ $it->product_name }}</span>
                            @if ($it->variant_label || $it->size_label)
                                <span class="label-order-variant muted">
                                    @if ($it->variant_label){{ $it->variant_label }}@endif
                                    @if ($it->size_label){{ $it->variant_label ? ' · ' : '' }}{{ $it->size_label }}@endif
                                </span>
                            @endif
                        </div>
                        <span class="label-order-qty">× {{ $it->quantity }}</span>
                    </div>
                @empty
                    <div class="muted small">No line items.</div>
                @endforelse
            </div>
            <div class="label-order-total">
                <strong>Total</strong> PKR {{ number_format((float) ($o['grand_total'] ?? 0), 0) }}
            </div>
        </div>
        <div class="label-qr-box">
            <div class="label-qr-label muted small">Scan</div>
            {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size($qrSize)->margin(0)->generate($o['qr_url']) !!}
            <div class="label-qr-hint mono small">{{ $o['order_number'] }}</div>
        </div>
    </div>

    <div class="label-cod {{ $isCod ? 'label-cod--due' : 'label-cod--zero' }}">
        {{ $codLabelText }}
    </div>

    <div class="label-notes">
        @if ($returnPolicy !== '')
            <div class="label-notes-block"><strong>Returns:</strong> {{ $returnPolicy }}</div>
        @endif
        @if ($requiredNotes !== '')
            <div class="label-notes-block"><strong>Note:</strong> {{ $requiredNotes }}</div>
        @endif
    </div>

    <div class="label-cut-line" aria-hidden="true"></div>
</div>
