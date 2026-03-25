<?php

namespace MCE\Multilang\Frontend;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\DB\TranslationRepository;

class LanguageSwitcher
{
    private TranslationRepository $repository;

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
    }

    public function register(): void
    {
        add_shortcode('mce_lang_switcher', [$this, 'renderShortcode']);
    }

    public function renderShortcode(array $atts = []): string
    {
        $currentLanguage = LanguageManager::getCurrentLanguage();
        $languages = LanguageManager::getSupportedLanguages();

        $output  = '<div class="mce-lang-switcher" style="position:relative; display:inline-block;">';
        $output .= '<select onchange="if(this.value){window.location.href=this.value;}" style="padding:8px 12px; min-width:90px;">';

        foreach ($languages as $language) {
            $url = $this->buildLanguageUrl($language);

            $output .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_url($url),
                selected($currentLanguage, $language, false),
                esc_html(strtoupper($language))
            );
        }

        $output .= '</select>';
        $output .= '</div>';

        return $output;
    }

    private function buildLanguageUrl(string $language): string
    {
        $objectId = get_queried_object_id();

        if (!$objectId && is_front_page()) {
            $objectId = (int) get_option('page_on_front');
        }

        if (!$objectId) {
            return $language === LanguageManager::getDefaultLanguage()
                ? home_url('/')
                : home_url('/' . $language . '/');
        }

        if ((int) get_option('page_on_front') === $objectId) {
            return $language === LanguageManager::getDefaultLanguage()
                ? home_url('/')
                : home_url('/' . $language . '/');
        }

        $post = get_post($objectId);

        if (!$post) {
            return $language === LanguageManager::getDefaultLanguage()
                ? home_url('/')
                : home_url('/' . $language . '/');
        }

        if ($language === LanguageManager::getDefaultLanguage()) {
            return get_permalink($objectId);
        }

        $translation = $this->repository->getTranslation($objectId, $post->post_type, $language);
        $slug = '';

        if ($translation && !empty($translation['translated_slug'])) {
            $slug = (string) $translation['translated_slug'];
        } else {
            $slug = $post->post_name;
        }

        if ($post->post_type === 'product') {
            return home_url('/' . $language . '/product/' . $slug . '/');
        }

        return home_url('/' . $language . '/' . $slug . '/');
    }
}
