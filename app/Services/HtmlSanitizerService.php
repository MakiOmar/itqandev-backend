<?php

namespace App\Services;

use DOMDocument;
use DOMNode;

class HtmlSanitizerService
{
    /**
     * Allowed HTML tags for rich text content.
     */
    protected array $allowedTags = [
        'p', 'br', 'strong', 'em', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'img',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    /**
     * Allowed attributes per tag.
     */
    protected array $allowedAttributes = [
        'a' => ['href', 'title', 'target'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'table' => ['class'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    /**
     * Sanitize HTML content by removing dangerous tags and attributes.
     */
    public function sanitize(?string $html): ?string
    {
        if (empty($html)) {
            return $html;
        }

        // Use strip_tags for basic sanitization if DOMDocument is not available
        if (!class_exists('DOMDocument')) {
            return strip_tags($html, '<' . implode('><', $this->allowedTags) . '>');
        }

        // Use DOMDocument for more sophisticated sanitization
        return $this->sanitizeWithDom($html);
    }

    /**
     * Sanitize HTML using DOMDocument for better control.
     */
    protected function sanitizeWithDom(string $html): string
    {
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->encoding = 'UTF-8';

        // Normalize escaped entities once; DOM will serialize — avoid deprecated mb_* HTML entity conversion (PHP 8.2+).
        $html = html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        // Wrap fragment for UTF-8 parsing (no mb_convert_encoding → HTML-ENTITIES).
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Clear libxml errors
        libxml_clear_errors();
        
        $body = $dom->getElementsByTagName('body')->item(0);
        
        if (!$body) {
            $fallback = strip_tags($html, '<' . implode('><', $this->allowedTags) . '>');

            return $this->decodeEntitiesToUtf8($fallback);
        }

        $this->sanitizeNode($body);

        // Get sanitized HTML
        $sanitized = '';
        foreach ($body->childNodes as $node) {
            $sanitized .= $dom->saveHTML($node);
        }

        // saveHTML() emits numeric entities for non-ASCII; store and serve UTF-8 instead
        return $this->decodeEntitiesToUtf8($sanitized);
    }

    /**
     * Turn HTML numeric/named entities back into UTF-8 (DOM saveHTML entity-encodes Unicode).
     */
    protected function decodeEntitiesToUtf8(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Recursively sanitize DOM nodes.
     */
    protected function sanitizeNode(DOMNode $node): void
    {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            $tagName = strtolower($node->nodeName);

            // Remove disallowed tags
            if (!in_array($tagName, $this->allowedTags, true)) {
                // Move children to parent
                while ($node->firstChild) {
                    $child = $node->removeChild($node->firstChild);
                    $node->parentNode->insertBefore($child, $node);
                }
                $node->parentNode->removeChild($node);
                return;
            }

            // Remove disallowed attributes
            $allowedAttrs = $this->allowedAttributes[$tagName] ?? [];
            $attributesToRemove = [];

            foreach ($node->attributes as $attr) {
                if (!in_array(strtolower($attr->nodeName), $allowedAttrs, true)) {
                    $attributesToRemove[] = $attr->nodeName;
                } else {
                    // Sanitize attribute values
                    $attrValue = $this->sanitizeAttribute($attr->nodeName, $attr->nodeValue);
                    $node->setAttribute($attr->nodeName, $attrValue);
                }
            }

            foreach ($attributesToRemove as $attrName) {
                $node->removeAttribute($attrName);
            }

            // Special handling for links - ensure safe URLs
            if ($tagName === 'a' && $node->hasAttribute('href')) {
                $href = $node->getAttribute('href');
                if (!$this->isSafeUrl($href)) {
                    $node->removeAttribute('href');
                }
            }

            // Special handling for images - ensure safe URLs
            if ($tagName === 'img' && $node->hasAttribute('src')) {
                $src = $node->getAttribute('src');
                if (!$this->isSafeUrl($src)) {
                    $node->removeAttribute('src');
                }
            }
        }

        // Recursively sanitize children
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            $this->sanitizeNode($child);
        }
    }

    /**
     * Sanitize attribute value.
     */
    protected function sanitizeAttribute(string $attrName, string $value): string
    {
        // Remove javascript: and data: protocols
        if (preg_match('/^(javascript|data|vbscript):/i', $value)) {
            return '';
        }

        // Preserve DOM serialization semantics: do not pre-escape attributes here —
        // saveHTML() will escape them once; prior htmlspecialchars caused &amp;amp; etc.

        return $value;
    }

    /**
     * Check if URL is safe.
     */
    protected function isSafeUrl(string $url): bool
    {
        // Allow relative URLs
        if (strpos($url, '/') === 0 || strpos($url, './') === 0) {
            return true;
        }

        // Allow http/https URLs
        if (preg_match('/^https?:\/\//i', $url)) {
            return true;
        }

        // Block javascript:, data:, vbscript: protocols
        if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
            return false;
        }

        return false;
    }

    /**
     * Strip all HTML tags (for plain text fields).
     */
    public function stripAll(?string $html): ?string
    {
        if (empty($html)) {
            return $html;
        }

        return strip_tags($html);
    }

    /**
     * Escape HTML entities (for output).
     */
    public function escape(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
