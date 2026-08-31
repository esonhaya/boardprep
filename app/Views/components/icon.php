<?php
$icon = (string) ($icon ?? 'info');
$size = (string) ($iconSize ?? 'md');
$label = $iconLabel ?? null;
$paths = [
    'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    'book' => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v17H6.5A2.5 2.5 0 0 0 4 21.5zm0 0v17M8 6h8M8 10h8"/>',
    'let' => '<path d="M3 6.5 12 3l9 3.5v11L12 21l-9-3.5zM12 3v18M3 6.5l9 3.8 9-3.8M7 8v7m10-7v7"/>',
    'cse' => '<path d="M3 9 12 4l9 5M5 10v8m4-8v8m6-8v8m4-8v8M3 20h18M17 5v-2h3v3"/>',
    'criminologist' => '<path d="M4 4h16v16H4zM8 4v16M4 8h4M12 9h5M12 13h5M12 17h3"/>',
    'nursing' => '<path d="M12 3v6m-3-3h6M7 6a7 7 0 1 0 10 0M6 17h12M8 14v6m8-6v6"/>',
    'psychometrician' => '<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0M3 14h4m10 0h4M5 10l3 1m8-1 3-1"/>',
    'exam' => '<path d="M5 3h14a2 2 0 0 1 2 2v14H3V5a2 2 0 0 1 2-2zm3 4h8M8 11h8M8 15h5"/>',
    'practice' => '<path d="m13 2-9 11h7l-1 9 9-12h-7z"/>',
    'history' => '<path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5M12 7v5l3 2"/>',
    'progress' => '<path d="M4 19V5m0 14h16M8 16v-5m4 5V7m4 9V3"/>',
    'chart' => '<path d="M4 19V5m0 14h16M8 16v-5m4 5V7m4 9V3"/>',
    'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
    'question' => '<circle cx="12" cy="12" r="9"/><path d="M9.7 9a2.4 2.4 0 1 1 3.8 1.9c-1 .7-1.5 1-1.5 2.1M12 16h.01"/>',
    'check' => '<path d="m5 12 4 4L19 6"/>',
    'warning' => '<path d="m12 3 9 17H3zM12 9v4M12 16h.01"/>',
    'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
    'edit' => '<path d="m4 16-1 5 5-1L20 8l-4-4zM14 6l4 4"/>',
    'inspect' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5M10.5 7v7M7 10.5h7"/>',
    'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
    'civic' => '<path d="m3 9 9-5 9 5M5 10v8m4-8v8m6-8v8m4-8v8M3 20h18"/>',
    'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
    'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'topic' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v8M8 12h8"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.3 1a7 7 0 0 0-1.7-1L14.6 3h-4l-.3 2.1a7 7 0 0 0-1.7 1l-2.3-1-2 3.4L6.3 10a7 7 0 0 0 0 2l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 1.7 1l.3 2.1h4l.3-2.1a7 7 0 0 0 1.7-1l2.3 1 2-3.4-2-1.5c.1-.3.1-.7.1-1z"/>',
    'doctor' => '<path d="M9 3v6a3 3 0 0 0 6 0V3M7 3h4M13 3h4M12 12v3a5 5 0 0 0 5 5h2M5 20h5"/>',
];
$svgLabel = $label === null ? ' aria-hidden="true"' : ' aria-label="' . htmlspecialchars((string) $label) . '"';
?><svg class="ui-icon ui-icon-<?= htmlspecialchars($size) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"<?= $svgLabel ?>><?= $paths[$icon] ?? $paths['info'] ?></svg>
