<?php

class SAI_Index
{
    public const OPTION_KEY = 'sai_mapping_index';

    public static function init(): void
    {
        add_action('save_post', [__CLASS__, 'handle_save_post'], 10, 2);
        add_action('deleted_post', [__CLASS__, 'handle_deleted_post']);
        add_action('added_post_meta', [__CLASS__, 'handle_meta_change'], 10, 4);
        add_action('updated_post_meta', [__CLASS__, 'handle_meta_change'], 10, 4);
        add_action('deleted_post_meta', [__CLASS__, 'handle_meta_change'], 10, 4);
    }

    public static function handle_save_post(int $post_id, WP_Post $post): void
    {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!post_type_supports($post->post_type, 'editor')) {
            return;
        }

        self::build_index();
    }

    public static function handle_deleted_post(int $post_id): void
    {
        self::build_index();
    }

    public static function handle_meta_change(int $meta_id, int $post_id, string $meta_key, $meta_value): void
    {
        if ($meta_key !== SAI_MAPPING_META_KEY) {
            return;
        }

        self::build_index();
    }

    public static function get_index(): array
    {
        $index = get_option(self::OPTION_KEY, []);
        if (!is_array($index)) {
            return [];
        }

        return $index;
    }

    public static function build_index(): void
    {
        $query = new WP_Query([
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => SAI_MAPPING_META_KEY,
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        $index = [
            'keywords' => [],
            'updated_at' => time(),
        ];

        foreach ($query->posts as $post_id) {
            $mappings = get_post_meta($post_id, SAI_MAPPING_META_KEY, true);
            $normalized = self::normalize_mappings($mappings);

            foreach ($normalized as $mapping) {
                $keyword = $mapping['keyword'];
                $url = $mapping['url'];
                $target_post_id = url_to_postid($url);

                $index['keywords'][$keyword][] = [
                    'source_post_id' => $post_id,
                    'url' => $url,
                    'target_post_id' => $target_post_id ?: 0,
                ];
            }
        }

        update_option(self::OPTION_KEY, $index, false);
    }

    private static function normalize_mappings($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];

        foreach ($raw as $key => $value) {
            if (is_array($value) && isset($value['keyword'], $value['url'])) {
                $keyword = trim((string) $value['keyword']);
                $url = trim((string) $value['url']);
            } else {
                $keyword = trim((string) $key);
                $url = trim((string) $value);
            }

            if ($keyword === '' || $url === '') {
                continue;
            }

            $normalized[] = [
                'keyword' => $keyword,
                'url' => $url,
            ];
        }

        return $normalized;
    }
}
