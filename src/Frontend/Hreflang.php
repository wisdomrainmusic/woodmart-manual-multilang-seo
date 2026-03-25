<?php

namespace MCE\Multilang\Frontend;

class Hreflang
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'render'], 5);
    }

    public function render(): void
    {
        if (is_admin()) {
            return;
        }

        $objectId = get_queried_object_id();

        if (!$objectId) {
            return;
        }

        $languages = ['en', 'de', 'fr', 'it', 'es', 'tr'];

        foreach ($languages as $lang) {
            echo $this->buildTag($objectId, $lang);
        }
    }

    private function buildTag(int $objectId, string $lang): string
    {
        $url = $this->buildUrl($objectId, $lang);

        return sprintf(
            '<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
            esc_attr($lang),
            esc_url($url)
        );
    }

    private function buildUrl(int $objectId, string $lang): string
    {
        $permalink = get_permalink($objectId);

        if ($lang === 'en') {
            return $permalink;
        }

        return home_url('/' . $lang . '/' . basename($permalink) . '/');
    }
}
