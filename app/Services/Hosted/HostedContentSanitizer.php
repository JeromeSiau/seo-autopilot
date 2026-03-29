<?php

namespace App\Services\Hosted;

use DOMDocument;
use DOMElement;
use DOMNode;

class HostedContentSanitizer
{
    private const ALLOWED_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'p', 'a', 'ul', 'ol', 'li', 'strong', 'em',
        'code', 'pre', 'blockquote', 'img', 'table', 'thead',
        'tbody', 'tr', 'th', 'td', 'br', 'hr', 'span', 'div',
    ];

    private const ALLOWED_ATTRS = [
        'href', 'src', 'alt', 'title', 'class', 'id', 'target', 'rel',
    ];

    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $internalErrors = libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div data-hosted-root="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        /** @var DOMElement|null $root */
        $root = $dom->getElementsByTagName('div')->item(0);

        if (!$root) {
            return strip_tags($html, $this->allowedTagsString());
        }

        $this->sanitizeNode($root);

        return $this->innerHtml($root);
    }

    private function sanitizeNode(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    $this->unwrapElement($child);
                    continue;
                }

                $this->sanitizeAttributes($child);
                $this->sanitizeNode($child);
            }
        }
    }

    private function sanitizeAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = $attribute->value;

            if (str_starts_with($name, 'on') || !in_array($name, self::ALLOWED_ATTRS, true)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            if (in_array($name, ['href', 'src'], true) && preg_match('/^\s*javascript:/i', $value)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            if ($name === 'target' && $value === '_blank') {
                $rel = trim($element->getAttribute('rel'));
                $parts = array_filter(explode(' ', $rel));
                $parts = array_values(array_unique([...$parts, 'noopener', 'noreferrer']));
                $element->setAttribute('rel', implode(' ', $parts));
            }
        }
    }

    private function unwrapElement(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (!$parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
        $this->sanitizeNode($parent);
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument?->saveHTML($child) ?? '';
        }

        return trim($html);
    }

    private function allowedTagsString(): string
    {
        return '<' . implode('><', self::ALLOWED_TAGS) . '>';
    }
}
