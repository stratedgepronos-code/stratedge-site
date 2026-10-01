<?php
/** Icônes internes : pas de bibliothèque chargée depuis un tiers. */
function se_admin_icon(string $name, int $size = 20): string {
    static $paths = [
        'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'chart'=>'<path d="M4 4v16h16M8 15l4-5 4 2 5-7"/>',
        'check'=>'<rect x="3" y="3" width="18" height="18" rx="5"/><path d="m7 12 3 3 7-7"/>',
        'users'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 6"/>',
        'message'=>'<path d="M21 11a9 9 0 0 1-9 9H4l-2 2V11a9 9 0 0 1 19 0Z"/><path d="M7 9h9M7 13h6"/>',
        'ticket'=>'<path d="M3 5h18v5a2 2 0 0 0 0 4v5H3v-5a2 2 0 0 0 0-4Zm12 0v3m0 3v2m0 3v3"/>',
        'lab'=>'<path d="M9 3h6m-5 0v6L4 19a1.3 1.3 0 0 0 1 2h14a1.3 1.3 0 0 0 1-2L14 9V3M7 15h10"/>',
        'live'=>'<path d="M3 8a7 7 0 0 0 0 8m4-5a3 3 0 0 0 0 2m14-5a7 7 0 0 1 0 8m-4-5a3 3 0 0 1 0 2"/><circle cx="12" cy="12" r="1.5"/>',
        'arrow'=>'<path d="M5 12h14m-5-5 5 5-5 5"/>',
        'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'image'=>'<rect x="3" y="3" width="18" height="18" rx="4"/><circle cx="8" cy="8" r="1"/><path d="m3 17 5-5 4 4 4-6 5 7"/>',
        'bulb'=>'<path d="M9 18h6m-6 3h6M9 15v-1a6 6 0 1 1 6 0v1"/>',
        'gift'=>'<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v9h14v-9M12 8v13M12 8H8a3 3 0 1 1 3-3l1 3Zm0 0h4a3 3 0 1 0-3-3l-1 3Z"/>',
        'broadcast'=>'<path d="m3 10 17-7v18L3 14Zm4 6v5h4l-1-4"/>',
        'history'=>'<path d="M3 4v6h6M3 10a9 9 0 1 1 1 8m8-12v6l4 2"/>',
        'target'=>'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'globe'=>'<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>',
        'logout'=>'<path d="M9 3H4v18h5m5-14 5 5-5 5m-5-5h10"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'crown'=>'<path d="m3 6 5 4 4-7 4 7 5-4-2 12H5ZM6 21h12"/>',
    ];
    return '<svg class="se-icon" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['target']).'</svg>';
}
