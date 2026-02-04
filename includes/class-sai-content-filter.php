<?php

class SAI_Content_Filter
{
    public static function init(): void
    {
        add_filter('the_content', [__CLASS__, 'filter_content'], 12);
    }

    public static function filter_content(string $content): string
    {
        if (is_admin()) {
            return $content;
        }

        $index = SAI_Index::get_index();
        if (empty($index['keywords']) || !is_array($index['keywords'])) {
            return $content;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return $content;
        }

        $keyword_map = self::build_keyword_map($index['keywords'], $post_id);
        if (empty($keyword_map)) {
            return $content;
        }

        return self::replace_keywords($content, $keyword_map);
    }

    private static function build_keyword_map(array $keywords, int $post_id): array
    {
        $map = [];

        foreach ($keywords as $keyword => $entries) {
            foreach ($entries as $entry) {
                if ((int) $entry['source_post_id'] === $post_id) {
                    continue;
                }

                if (!empty($entry['target_post_id']) && (int) $entry['target_post_id'] === $post_id) {
                    continue;
                }

                $map[$keyword] = $entry['url'];
                break;
            }
        }

        return $map;
    }

    private static function replace_keywords(string $content, array $keyword_map): string
    {
        if (!class_exists('DOMDocument')) {
            return $content;
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $encoded = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
        $dom->loadHTML('<div>' . $encoded . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $ignore_tags = ['a', 'script', 'style'];

        if (apply_filters('sai_ignore_headings', true)) {
            $ignore_tags = array_merge($ignore_tags, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']);
        }

        if (apply_filters('sai_ignore_code_blocks', true)) {
            $ignore_tags = array_merge($ignore_tags, ['code', 'pre']);
        }

        $conditions = array_map(static function (string $tag): string {
            return 'not(ancestor::' . $tag . ')';
        }, $ignore_tags);

        $query = '//text()[normalize-space() != "" and ' . implode(' and ', $conditions) . ']';
        $text_nodes = $xpath->query($query);
        if ($text_nodes === false) {
            return $content;
        }

        $pattern = self::build_keyword_pattern(array_keys($keyword_map));
        if ($pattern === null) {
            return $content;
        }

        $lookup = array_change_key_case($keyword_map, CASE_LOWER);

        foreach ($text_nodes as $text_node) {
            $original = $text_node->nodeValue;
            if (!preg_match_all($pattern, $original, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $fragment = $dom->createDocumentFragment();
            $offset = 0;

            foreach ($matches[0] as $match_data) {
                [$match, $position] = $match_data;

                if ($position > $offset) {
                    $fragment->appendChild($dom->createTextNode(substr($original, $offset, $position - $offset)));
                }

                $key = mb_strtolower($match);
                if (!isset($lookup[$key])) {
                    $fragment->appendChild($dom->createTextNode($match));
                } else {
                    $link = $dom->createElement('a');
                    $link->setAttribute('class', 'sai-link');
                    $link->setAttribute('href', esc_url($lookup[$key]));
                    $link->appendChild($dom->createTextNode($match));
                    $fragment->appendChild($link);
                }

                $offset = $position + strlen($match);
            }

            if ($offset < strlen($original)) {
                $fragment->appendChild($dom->createTextNode(substr($original, $offset)));
            }

            $text_node->parentNode->replaceChild($fragment, $text_node);
        }

        $wrapper = $dom->getElementsByTagName('div')->item(0);
        if ($wrapper === null) {
            return $content;
        }

        $output = '';
        foreach ($wrapper->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output;
    }

    private static function build_keyword_pattern(array $keywords): ?string
    {
        $keywords = array_filter(array_map('trim', $keywords));
        if (empty($keywords)) {
            return null;
        }

        usort($keywords, static function (string $a, string $b): int {
            return mb_strlen($b) <=> mb_strlen($a);
        });

        $patterns = [];
        foreach ($keywords as $keyword) {
            $escaped = preg_quote($keyword, '/');
            if (preg_match('/^[\pL\pN_]+$/u', $keyword)) {
                $patterns[] = '\\b' . $escaped . '\\b';
            } else {
                $patterns[] = $escaped;
            }
        }

        return '/(' . implode('|', $patterns) . ')/iu';
    }
}
