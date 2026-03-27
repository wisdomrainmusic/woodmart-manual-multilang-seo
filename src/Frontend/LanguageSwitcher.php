<?php

namespace MCE\Multilang\Frontend;

use MCE\Multilang\Core\Config;
use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\Core\LocalizedUrlBuilder;
use MCE\Multilang\DB\TranslationRepository;

class LanguageSwitcher
{
    private TranslationRepository $repository;
    private LocalizedUrlBuilder $urlBuilder;

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
        $this->urlBuilder = new LocalizedUrlBuilder($this->repository);
    }

    public function register(): void
    {
        add_shortcode('mce_lang_switcher', [$this, 'renderShortcode']);
    }

    public function renderShortcode(array $atts = []): string
    {
        $currentLanguage = LanguageManager::getCurrentLanguage();
        $languages = LanguageManager::getSupportedLanguages();
        $cookieName = esc_js(Config::getLanguageCookieName());
        $cookieTtl = (int) Config::getLanguageCookieTtl();

        $output  = '<div class="mce-lang-switcher" style="position:relative; display:inline-block;">';
        $output .= '<select onchange="if(this.value){var lang=this.options[this.selectedIndex].getAttribute(\'data-lang\')||\'\';if(lang){document.cookie=\'' . $cookieName . '=\' + encodeURIComponent(lang) + \';path=/;max-age=' . $cookieTtl . ';SameSite=Lax\';}window.location.href=this.value;}" style="padding:8px 12px; min-width:90px;">';

        foreach ($languages as $language) {
            $url = $this->buildLanguageUrl($language);

            $output .= sprintf(
                '<option value="%s" data-lang="%s" %s>%s</option>',
                esc_url($url),
                esc_attr($language),
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
            $url = LanguageManager::isDefault($language)
                ? home_url('/')
                : home_url('/' . $language . '/');

            return $this->appendLanguageQueryArgForDefault($url, $language);
        }

        $url = $this->urlBuilder->buildObjectUrl($objectId, $language, false);

        if ($url !== '') {
            return $this->appendLanguageQueryArgForDefault($url, $language);
        }

        $fallbackUrl = LanguageManager::isDefault($language)
            ? home_url('/')
            : home_url('/' . $language . '/');

        return $this->appendLanguageQueryArgForDefault($fallbackUrl, $language);
    }

    private function appendLanguageQueryArgForDefault(string $url, string $language): string
    {
        if (!LanguageManager::isDefault($language)) {
            return $url;
        }

        return add_query_arg(Config::getLanguageQueryVar(), $language, $url);
    }
}
