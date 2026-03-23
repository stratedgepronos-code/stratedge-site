<?php
/**
 * Nettoyage HTML pour les emails Broadcast (admin uniquement).
 * — Balises autorisées via strip_tags
 * — href / src : http(s) uniquement
 * — Suppression onclick, style, etc.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}

/** URL autorisée dans un email broadcast */
function broadcast_allowed_url(string $url): bool {
    $url = trim($url);
    if ($url === '' || $url === '#') {
        return false;
    }
    if (!preg_match('#^https?://#i', $url)) {
        return false;
    }
    $p = @parse_url($url);
    if ($p === false || empty($p['scheme']) || empty($p['host'])) {
        return false;
    }
    $scheme = strtolower((string)$p['scheme']);
    return in_array($scheme, ['http', 'https'], true);
}

/**
 * @return string HTML sûr pour insertion dans le template email
 */
function sanitize_broadcast_email_html(string $html): string {
    if (trim($html) === '') {
        return '';
    }
    $html = str_replace("\0", '', $html);

    $allowedTags = '<p><br><br/><strong><b><em><i><u><a><ul><ol><li><h1><h2><h3><h4><h5><span><div><img><table><thead><tbody><tfoot><tr><td><th><caption><blockquote><hr>';
    $html = strip_tags($html, $allowedTags);

    if (!class_exists(DOMDocument::class)) {
        return nl2br(htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $wrapped = '<div data-broadcast-root="1">' . $html . '</div>';
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $ok = @$doc->loadHTML(
        '<?xml encoding="UTF-8">' . $wrapped,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    if (!$ok) {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $xpath = new DOMXPath($doc);
    $root = $xpath->query('//div[@data-broadcast-root="1"]')->item(0);
    if (!$root instanceof DOMElement) {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    broadcast_clean_node_attributes($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

function broadcast_clean_node_attributes(DOMNode $node): void {
    if ($node instanceof DOMElement) {
        $tag = strtolower($node->tagName);
        $attrsRemove = [];

        if ($node->hasAttributes()) {
            foreach (iterator_to_array($node->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (strpos($name, 'on') === 0) {
                    $attrsRemove[] = $attr->name;
                    continue;
                }
                if (in_array($name, ['style', 'class', 'id'], true)) {
                    $attrsRemove[] = $attr->name;
                    continue;
                }

                if ($tag === 'a' && $name === 'href') {
                    if (broadcast_allowed_url($attr->value)) {
                        $node->setAttribute('href', $attr->value);
                    } else {
                        $attrsRemove[] = $attr->name;
                    }
                    continue;
                }

                if ($tag === 'img' && $name === 'src') {
                    if (broadcast_allowed_url($attr->value)) {
                        $node->setAttribute('src', $attr->value);
                    } else {
                        $attrsRemove[] = $attr->name;
                    }
                    continue;
                }

                if ($tag === 'img' && in_array($name, ['alt', 'title', 'width', 'height'], true)) {
                    continue;
                }

                $attrsRemove[] = $attr->name;
            }
            foreach ($attrsRemove as $n) {
                $node->removeAttribute($n);
            }
        }

        if ($tag === 'img' && !$node->hasAttribute('src')) {
            $node->parentNode?->removeChild($node);
            return;
        }
    }

    if (!$node->hasChildNodes()) {
        return;
    }
    $children = [];
    foreach ($node->childNodes as $c) {
        $children[] = $c;
    }
    foreach ($children as $c) {
        broadcast_clean_node_attributes($c);
    }
}

/** Texte pour notification push (pas de HTML) */
function broadcast_message_to_plain(string $message, bool $wasHtml): string {
    if (!$wasHtml) {
        return $message;
    }
    $t = strip_tags($message);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', $t);
    return trim((string)$t);
}
