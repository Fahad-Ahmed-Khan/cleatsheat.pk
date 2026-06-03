<?php

$storeName = env('APP_NAME', 'CleatSheat.pk');
$phone = env('STORE_SUPPORT_PHONE', '+923001234567');
$whatsapp = env('STORE_SUPPORT_WHATSAPP_URL', 'https://wa.me/923001234567');
$deliveryMin = (int) env('STORE_DELIVERY_DAYS_MIN', 2);
$deliveryMax = (int) env('STORE_DELIVERY_DAYS_MAX', 5);
$shippingFlat = (int) env('STORE_SHIPPING_FLAT', 200);
$returnSummary = env(
    'STORE_RETURN_POLICY_SUMMARY',
    '14-day returns on unworn shoes in original packaging. COD orders: inspect at delivery.',
);

return [
    'privacy-policy' => [
        'title' => 'Privacy Policy',
        'description' => 'How we collect, use, and protect your personal information when you shop for football boots in Pakistan.',
        'body' => <<<'HTML'
<h2>Overview</h2>
<p>We respect your privacy. This policy explains what information we collect when you shop with us, how we use it, and the choices you have.</p>
<h2>Information we collect</h2>
<ul>
<li><strong>Order details:</strong> name, phone number, email, shipping address, and items purchased.</li>
<li><strong>Account data:</strong> if you create an account, your login email and profile details you provide.</li>
<li><strong>Technical data:</strong> device type, browser, and basic analytics to improve the store experience.</li>
</ul>
<h2>How we use your information</h2>
<ul>
<li>To process and deliver orders, including courier handoff and tracking updates.</li>
<li>To contact you about your order, returns, or support requests.</li>
<li>To prevent fraud and keep our checkout secure.</li>
<li>To improve our website, products, and customer service.</li>
</ul>
<h2>Sharing</h2>
<p>We share data only with service providers who help us operate the store (payment, shipping, messaging) and only as needed to fulfil your order. We do not sell your personal information.</p>
<h2>Retention &amp; security</h2>
<p>We keep order records as required for accounting and customer support. We use reasonable technical and organisational measures to protect your data.</p>
<h2>Your rights</h2>
<p>You may request access, correction, or deletion of personal data where applicable law allows. Contact us using the details on our <a href="/contact">contact page</a>.</p>
<h2>Updates</h2>
<p>We may update this policy from time to time. The latest version will always be published on this page.</p>
HTML,
    ],

    'terms-and-conditions' => [
        'title' => 'Terms & Conditions',
        'description' => 'Terms governing use of our website and purchases of football shoes, cleats, and accessories in Pakistan.',
        'body' => <<<'HTML'
<h2>Agreement</h2>
<p>By using this website and placing an order, you agree to these terms. If you do not agree, please do not use the site.</p>
<h2>Products &amp; pricing</h2>
<ul>
<li>Product descriptions, images, and sizing notes are provided in good faith. Minor variations may occur for pre-owned or limited-stock items.</li>
<li>Prices are shown in PKR unless stated otherwise and may change without notice until checkout is completed.</li>
<li>We reserve the right to cancel orders affected by pricing errors, stock issues, or suspected fraud.</li>
</ul>
<h2>Orders &amp; payment</h2>
<ul>
<li>An order is confirmed when you receive an order confirmation (email/SMS/WhatsApp where applicable).</li>
<li>We accept payment methods shown at checkout, including cash on delivery where offered. See our <a href="/payment-policy">payment policy</a>.</li>
<li>You are responsible for providing accurate contact and delivery details.</li>
</ul>
<h2>Delivery</h2>
<p>Estimated delivery windows are indicative and may vary by city, courier capacity, or public holidays. See our <a href="/shipping-policy">shipping policy</a>. Risk passes to you upon successful delivery unless otherwise required by law.</p>
<h2>Limitation of liability</h2>
<p>To the fullest extent permitted by law, we are not liable for indirect or consequential losses. Our liability for any order is limited to the amount you paid for that order.</p>
<h2>Governing law</h2>
<p>These terms are governed by the laws of Pakistan. Disputes will be handled in good faith and, where needed, through appropriate local forums.</p>
HTML,
    ],

    'return-policy' => [
        'title' => 'Returns & Exchanges',
        'description' => 'Returns, exchanges, and refunds for football boots and gear — Pakistan.',
        'body' => <<<HTML
<h2>Summary</h2>
<p>{$returnSummary}</p>
<h2>Eligibility</h2>
<ul>
<li>Items must be unworn, in original condition, with tags and packaging where applicable.</li>
<li>Return requests should be raised within 14 days of delivery unless otherwise stated on your order confirmation.</li>
<li>Pre-owned or clearance items may be final sale unless otherwise marked on the product page.</li>
<li>Football socks, grippers, and hygiene-sensitive accessories may be non-returnable once opened.</li>
</ul>
<h2>How to start a return</h2>
<p>Contact our support team via <a href="/contact">contact</a> or WhatsApp with your order number and reason for return. We will confirm next steps, including pickup or drop-off instructions if applicable.</p>
<h2>Refunds</h2>
<p>Approved refunds are processed to the original payment method where possible. COD orders may be refunded via bank transfer or store credit as agreed with support.</p>
<h2>Exchanges</h2>
<p>Size exchanges depend on stock availability. We will do our best to offer an alternative UK/EU size or store credit if exchange is not possible.</p>
<h2>Exclusions</h2>
<p>Customised items, items damaged after delivery, and products marked final sale are not eligible for return unless defective on arrival.</p>
HTML,
    ],

    'payment-policy' => [
        'title' => 'Payment Policy',
        'description' => 'Payment methods in Pakistan: COD, JazzCash, Easypaisa, and secure card payments for football boots and cleats.',
        'body' => <<<HTML
<h2>Accepted payment methods</h2>
<p>We offer flexible payment options for customers across Pakistan:</p>
<ul>
<li><strong>Cash on delivery (COD):</strong> pay when your parcel arrives. Inspect your order at the door before paying.</li>
<li><strong>JazzCash:</strong> mobile wallet payment — details shared at checkout when selected.</li>
<li><strong>Easypaisa:</strong> mobile wallet payment — details shared at checkout when selected.</li>
<li><strong>Debit / credit card:</strong> secure online payment via our payment partner (where enabled at checkout).</li>
</ul>
<h2>Currency</h2>
<p>All prices are listed in <strong>PKR (Pakistani Rupees)</strong>. Your bank or wallet may apply its own conversion fees for international cards.</p>
<h2>Order confirmation</h2>
<p>An order is considered placed once you receive confirmation (on-screen, email, SMS, or WhatsApp where applicable). If payment fails for prepaid methods, your order will not be processed until payment succeeds.</p>
<h2>Security</h2>
<p>Card payments are processed through secure, encrypted gateways. We do not store full card numbers on our servers.</p>
<h2>COD notes</h2>
<p>COD availability may vary by city and order value. A small COD handling fee may apply where shown at checkout. Please keep your phone reachable for courier coordination.</p>
<h2>Questions</h2>
<p>For payment issues, contact us via the <a href="/contact">contact page</a> or WhatsApp with your order number.</p>
HTML,
    ],

    'disclaimer' => [
        'title' => 'Disclaimer',
        'description' => 'Disclaimer for product information, pricing, and use of this website in Pakistan.',
        'body' => <<<HTML
<h2>General</h2>
<p>The information on {$storeName} is provided for general shopping and product guidance. While we strive for accuracy, we make no warranties about completeness, reliability, or suitability for a particular purpose.</p>
<h2>Product images &amp; descriptions</h2>
<p>Colours, materials, and condition may vary slightly from photos due to lighting, screen settings, or batch differences — especially for pre-owned football boots. Always read size notes and condition labels on each product page.</p>
<h2>Third-party brands</h2>
<p>Brand names (Nike, Adidas, Puma, etc.) are used for identification only. We are not affiliated with or endorsed by those brands unless explicitly stated.</p>
<h2>External links</h2>
<p>Our site may link to couriers, payment providers, or social platforms. We are not responsible for their content or policies.</p>
<h2>Limitation of liability</h2>
<p>To the extent permitted by Pakistani law, {$storeName} is not liable for indirect damages arising from use of this website or products purchased here.</p>
<h2>Contact</h2>
<p>Questions? See our <a href="/contact">contact page</a>.</p>
HTML,
    ],

    'shipping-policy' => [
        'title' => 'Shipping & Delivery Policy',
        'description' => "Nationwide delivery of football shoes and cleats in Pakistan — timelines, couriers, and PKR {$shippingFlat} flat shipping.",
        'body' => <<<HTML
<h2>Delivery coverage</h2>
<p>We deliver football boots, cleats, socks, grippers, and accessories across Pakistan via trusted courier partners.</p>
<h2>Estimated timelines</h2>
<ul>
<li><strong>Major cities</strong> (Lahore, Karachi, Islamabad, Rawalpindi, Faisalabad): typically {$deliveryMin}–{$deliveryMax} business days after dispatch.</li>
<li><strong>Other cities &amp; towns:</strong> may take additional 1–3 business days depending on courier routing.</li>
<li><strong>Peak seasons</strong> (Eid, sales): allow extra time; we will communicate delays where possible.</li>
</ul>
<h2>Shipping charges</h2>
<p>Standard flat shipping is <strong>PKR {$shippingFlat}</strong> unless a promotion states otherwise at checkout. Heavy or remote-area surcharges, if any, are shown before you pay.</p>
<h2>Order tracking</h2>
<p>Once dispatched, you may receive a tracking link or reference by SMS/WhatsApp. You can also use our <a href="/track-order">track order</a> page.</p>
<h2>Inspection on delivery (COD)</h2>
<p>For cash-on-delivery orders, inspect the parcel before paying. Report damage or wrong items within 24 hours with photos.</p>
<h2>Failed delivery</h2>
<p>If a courier cannot reach you after reasonable attempts, the order may return to us. Re-delivery or refund terms will be agreed with support.</p>
<h2>Questions</h2>
<p>Need help? <a href="/contact">Contact us</a> or message on WhatsApp.</p>
HTML,
    ],

    'about' => [
        'title' => 'About Us',
        'description' => "About {$storeName} — original used football boots, cleats, and gear for players in Pakistan.",
        'body' => <<<HTML
<h2>Who we are</h2>
<p><strong>{$storeName}</strong> is a Pakistan-focused online store for football players who want match-ready boots without paying full retail every season. We specialise in <strong>original used football shoes</strong> — FG, SG, AG, and turf — with honest condition grading and clear UK/EU sizing.</p>
<h2>What we sell</h2>
<ul>
<li><a href="/c/football-shoes">Football shoes &amp; boots</a> for grass, turf, and futsal</li>
<li><a href="/c/football-cleats">Football cleats</a> with surface-matched studs</li>
<li><a href="/c/grippers">Grippers</a> and anti-slip solutions</li>
<li><a href="/c/football-socks">Football socks</a> and grip socks</li>
<li><a href="/c/accessories">Accessories</a> — laces, bags, care kits, and more</li>
</ul>
<h2>Why shop with us</h2>
<ul>
<li>Inspected listings with real photos and size tags</li>
<li>WhatsApp fit help before you checkout</li>
<li>COD, JazzCash, Easypaisa, and card options (see <a href="/payment-policy">payment policy</a>)</li>
<li>Nationwide delivery — Lahore to Karachi and beyond</li>
</ul>
<h2>Our promise</h2>
<p>We would not list a boot we would not loan to a teammate. If something is off, our <a href="/return-policy">returns policy</a> and support team are here to fix it.</p>
<h2>Get in touch</h2>
<p>Visit our <a href="/contact">contact page</a> or message us on WhatsApp for sizing and stock questions.</p>
HTML,
    ],

    'faq' => [
        'title' => 'Frequently Asked Questions',
        'description' => 'FAQs about football shoes, cleats, sizing, COD, delivery, and returns in Pakistan.',
        'faqs' => [
            [
                'q' => 'Do you deliver football boots across Pakistan?',
                'a' => 'Yes. We ship nationwide via courier partners. Major cities usually receive orders within a few business days after dispatch.',
            ],
            [
                'q' => 'What payment methods do you accept?',
                'a' => 'We accept cash on delivery (COD), JazzCash, Easypaisa, and secure card payments where shown at checkout.',
            ],
            [
                'q' => 'How do I choose FG vs AG vs Turf boots?',
                'a' => 'FG suits natural grass, AG suits 3G/4G turf, TF/Turf suits rubber courts and dusty astro turf, and IC/indoor suits futsal halls. See our journal guide on boot types or browse by surface category.',
            ],
            [
                'q' => 'Are your football shoes new or used?',
                'a' => 'We specialise in original used boots in inspected condition. Condition is stated on each product page.',
            ],
            [
                'q' => 'What size should I order?',
                'a' => 'Use the UK/EU size on the listing and compare your foot length in cm. Message us on WhatsApp before checkout if you are between sizes.',
            ],
            [
                'q' => 'Can I return football boots if the size is wrong?',
                'a' => 'Returns and exchanges are possible for unworn items in original condition within our return window. See the return policy for details.',
            ],
            [
                'q' => 'Do you sell grippers and football socks?',
                'a' => 'Yes. Browse grippers and football socks in our accessories categories for match-day grip and comfort.',
            ],
        ],
        'body' => null,
    ],

    'contact' => [
        'title' => 'Contact Us',
        'description' => "Contact {$storeName} — phone, WhatsApp, email, and support hours for football boots in Pakistan.",
        'local_business' => [
            'streetAddress' => env('STORE_BUSINESS_ADDRESS', 'Lahore, Pakistan'),
            'addressLocality' => env('STORE_BUSINESS_CITY', 'Lahore'),
            'telephone' => $phone,
            'email' => env('STORE_CONTACT_EMAIL', 'info@cleatsheat.pk'),
            'openingHours' => env('STORE_OPENING_HOURS', 'Mo-Sa 10:00-19:00'),
        ],
        'body' => <<<HTML
<h2>Customer support</h2>
<p>We are here to help with sizing, stock, orders, and returns.</p>
<ul>
<li><strong>Phone / WhatsApp:</strong> <a href="{$whatsapp}" target="_blank" rel="noopener noreferrer">Message on WhatsApp</a></li>
<li><strong>Phone:</strong> {$phone}</li>
<li><strong>Email:</strong> info@cleatsheat.pk</li>
</ul>
<h2>Hours</h2>
<p>Monday – Saturday, 10:00 AM – 7:00 PM (PKT). Responses may be slower on public holidays.</p>
<h2>Order help</h2>
<p>Already placed an order? Use <a href="/track-order">track order</a> or WhatsApp with your order number.</p>
<h2>Visit</h2>
<p>We operate online with dispatch from Lahore. Local pickup may be arranged by appointment — message us first.</p>
HTML,
    ],
];
