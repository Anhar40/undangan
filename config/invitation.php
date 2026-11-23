<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Preview window
    |--------------------------------------------------------------------------
    |
    | A draft may be edited and previewed for this many hours from the moment
    | it is first created. Editing never resets the clock (PRD section 9).
    |
    */
    'preview_hours' => (int) env('PREVIEW_HOURS', 10),

    'default_price' => (int) env('ACTIVATION_PRICE', 150000),

    /*
    |--------------------------------------------------------------------------
    | Default wedding quote
    |--------------------------------------------------------------------------
    |
    | One source for every invitation and template. The editor may override it
    | or restore it, but templates never hardcode their own quote.
    |
    */
    'default_quote' => '"Dan di antara tanda-tanda kekuasaan-Nya, Dia menciptakan untukmu pasangan hidup dari jenismu sendiri supaya kamu mendapat ketenangan hati, dan dijadikan-Nya kasih sayang di antara kamu."',
    'default_quote_source' => '- Q.S. Ar-Rum: 21 -',

    /*
    |--------------------------------------------------------------------------
    | Template sources
    |--------------------------------------------------------------------------
    |
    | The two original HTML files ship in template/. They are never rewritten.
    | Rendering happens by targeted string substitution at request time.
    |
    */
    'template_path' => base_path('template'),

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_kb' => (int) env('UPLOAD_MAX_KB', 5120),
        'allowed' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_dimension' => (int) env('UPLOAD_MAX_DIMENSION', 1600),
        'max_cover_footer_photos' => 2,
        'disk' => 'public',
    ],

    /*
     | Only these fixed stacks may be emitted into invitation CSS. Customers
     | choose a key; free-form CSS is never stored or rendered.
     */
    'fonts' => [
        'classic' => 'Poppins, sans-serif',
        'editorial' => "'Cormorant Garamond', Georgia, serif",
        'minimal' => 'Inter, system-ui, sans-serif',
    ],

    /*
     | Slugs that would collide with first-party routes or look like system
     | endpoints. They are rejected before the database uniqueness check.
     */
    'reserved_slugs' => [
        'admin', 'auth', 'dashboard', 'login', 'logout', 'payment', 'share',
        'templates', 'transactions', 'u', 'api', 'assets', 'storage', 'undangan',
    ],

    'share' => [
        'default_message' => "Kepada Yth.\nBapak/Ibu/Saudara/i {guest}\n\nTanpa mengurangi rasa hormat, kami bermaksud mengundang Bapak/Ibu/Saudara/i untuk menghadiri acara pernikahan kami.\n\nBerikut link undangan kami untuk informasi lengkap mengenai acara:\n{link}\n\nMerupakan suatu kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir dan memberikan doa restu.\n\nTerima kasih.\n\n{couple}",
    ],
];
