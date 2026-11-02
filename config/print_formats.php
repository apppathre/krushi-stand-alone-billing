<?php

/**
 * Sale / tax invoice print layouts. User picks format (+ paper size) when printing.
 * Default format is stored on company_profile.default_invoice_print_format.
 */
return [
    'sale' => [
        'standard' => [
            'label' => 'Standard invoice',
            'label_mr' => 'सामान्य बिल',
            'blurb' => 'Clean A4 / half / quarter invoice. Supports English or Marathi labels.',
            'view' => 'sales.pdf',
            'sizes' => ['a4', 'half', 'quarter'],
            'default_size' => 'a4',
        ],
        'agro' => [
            'label' => 'Agro tax invoice',
            'label_mr' => 'कृषी कर बील',
            'blurb' => 'Detailed bilingual tax invoice (batch, packing, licences, GST summary) — like pesticide / fertilizer bills.',
            'view' => 'sales.pdf-agro',
            'sizes' => ['a4'],
            'default_size' => 'a4',
            'orientation' => 'landscape',
        ],
    ],
];
