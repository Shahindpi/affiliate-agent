<?php

namespace App\Services;

class PageContentSanitizer
{
    private const TAGS = ['p','br','h2','h3','h4','strong','em','u','ul','ol','li','blockquote','a','img'];

    public function clean(string $html): string
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) return '';
        $this->walk($body);
        $out = ''; foreach ($body->childNodes as $child) $out .= $dom->saveHTML($child);
        return $out;
    }

    private function walk(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!$child instanceof \DOMElement) continue;
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script','style','iframe','object','embed','svg','form'], true)) { $node->removeChild($child); continue; }
            $this->walk($child);
            if (!in_array($tag, self::TAGS, true)) {
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $tag === 'a' ? ['href','title'] : ($tag === 'img' ? ['src','alt','title'] : []), true)) { $child->removeAttributeNode($attr); continue; }
                if (in_array($name, ['href','src'], true) && !$this->safeUrl($attr->value)) $child->removeAttributeNode($attr);
            }
            if ($tag === 'a') $child->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function safeUrl(string $url): bool
    {
        $url = trim(html_entity_decode($url));
        return (str_starts_with($url, '/') && !str_starts_with($url, '//')) || preg_match('/^https?:\/\/[^\s]+$/i', $url) === 1;
    }
}
