<?php

return [

    // --- Register your custom Bangla font (root level) ---
    'font_data' => [
        'solaimanlipi' => [
            'R'         => 'SolaimanLipi.ttf',   // put this file at storage/fonts/SolaimanLipi.ttf
            'B'         => 'SolaimanLipi.ttf',   // reuse if you don't have a bold file
            'useOTL'    => 0xFF,
            'useKashida'=> 75,
        ],
        // If you later add others (Nikosh, Noto Sans Bengali), define them here too.
        'nikosh' => [
            'R'         => 'Potro Sans Bangla Regular.ttf',
            'B'         => 'Potro Sans Bangla Bold.ttf',
            'useOTL'    => 0xFF,
            'useKashida'=> 75,
        ],
        'notosansbengali' => [
            'R'         => 'BL Fiona Bangla Unicode.ttf',
            'B'         => 'BL Fiona Bangla Italic.ttf',
            'useOTL'    => 0xFF,
            'useKashida'=> 75,
        ],
    ],

    // Optional public path override
    'public_path' => null,

    // Show warnings
    'show_warnings' => false,

    // --- DOMPDF core options ---
    'options' => [

        // Font dirs / cache (must exist & be writable)
        'font_dir'   => storage_path('fonts'),
        'font_cache' => storage_path('fonts'),

        // Temp dir
        'temp_dir' => sys_get_temp_dir(),

        // Security sandbox
        'chroot' => realpath(base_path()),

        // Allowed protocols
        'allowed_protocols' => [
            'data://'  => ['rules' => []],
            'file://'  => ['rules' => []],
            'http://'  => ['rules' => []],
            'https://' => ['rules' => []],
        ],

        'artifactPathValidation' => null,
        'log_output_file' => null,

        // Turn on if you want smaller PDFs (subset embedded glyphs)
        'enable_font_subsetting' => false,

        // Renderer
        'pdf_backend' => 'CPDF',

        // Media / paper
        'default_media_type' => 'screen',
        'default_paper_size' => 'a4',
        'default_paper_orientation' => 'portrait',

        // *** USE YOUR BANGLA FONT AS DEFAULT ***
        'default_font' => 'notosansbengali',

        // DPI
        'dpi' => 96,

        // Security toggles
        'enable_php' => false,
        'enable_javascript' => true,

        // If you’ll load images/css via URL, set TRUE
        'enable_remote' => true,

        'allowed_remote_hosts' => null,

        'font_height_ratio' => 1.1,

        // Always on in dompdf 2.x, but keep true
        'enable_html5_parser' => true,
    ],
];