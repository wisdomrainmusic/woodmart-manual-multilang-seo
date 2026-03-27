<?php

namespace MCE\Multilang\Frontend;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\DB\TranslationRepository;

class ContentFilter
{
    private TranslationRepository $repository;
    private const SETTINGS_OPTION_KEY = 'mce_multilang_settings';
    private bool $footerBufferStarted = false;

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
    }

    public function register(): void
    {
        add_filter('the_title', [$this, 'filterTitle'], 20, 2);
        add_filter('get_the_excerpt', [$this, 'filterExcerpt'], 20, 2);
        add_filter('the_content', [$this, 'filterContent'], 20);
        add_action('template_redirect', [$this, 'maybeStartFooterOutputBuffer'], 0);
        add_action('shutdown', [$this, 'maybeEndFooterOutputBuffer'], 0);

        add_filter('woocommerce_short_description', [$this, 'filterWooShortDescription'], 20);
        add_filter('woocommerce_product_get_description', [$this, 'filterWooProductDescription'], 20, 2);
        add_filter('woocommerce_product_get_short_description', [$this, 'filterWooProductShortDescription'], 20, 2);
    }

    private function resolveCurrentPostId(int $postId = 0): int
    {
        if ($postId > 0) {
            return $postId;
        }

        $queriedObjectId = get_queried_object_id();

        if (is_numeric($queriedObjectId) && (int) $queriedObjectId > 0) {
            return (int) $queriedObjectId;
        }

        $loopPostId = get_the_ID();

        if (is_numeric($loopPostId) && (int) $loopPostId > 0) {
            return (int) $loopPostId;
        }

        global $post;

        if (is_object($post) && !empty($post->ID)) {
            return (int) $post->ID;
        }

        return 0;
    }

    public function filterTitle(string $title, int $postId = 0): string
    {
        if (is_admin()) {
            return $title;
        }

        $postId = $this->resolveCurrentPostId($postId);

        if ($postId <= 0) {
            return $title;
        }

        if (!$this->shouldFilterPost($postId)) {
            return $title;
        }

        $translation = $this->getTranslationForPost($postId);

        if (!$translation) {
            return $title;
        }

        return !empty($translation['translated_title']) ? (string) $translation['translated_title'] : $title;
    }

    public function filterExcerpt(string $excerpt, $post): string
    {
        if (is_admin() || !is_object($post) || empty($post->ID)) {
            return $excerpt;
        }

        $postId = (int) $post->ID;

        if (!$this->shouldFilterPost($postId)) {
            return $excerpt;
        }

        $translation = $this->getTranslationForPost($postId);

        if (!$translation) {
            return $excerpt;
        }

        return !empty($translation['translated_excerpt']) ? (string) $translation['translated_excerpt'] : $excerpt;
    }

    public function filterContent(string $content): string
    {
        if (is_admin() || !is_singular()) {
            return $content;
        }

        $postId = $this->resolveCurrentPostId();

        if ($postId <= 0 || !$this->shouldFilterPost($postId)) {
            return $content;
        }

        $translation = $this->getTranslationForPost($postId);

        if (!$translation) {
            return $content;
        }

        if (!empty($translation['custom_html'])) {
            return $this->renderTranslatableMarkup((string) $translation['custom_html']);
        }

        if (!empty($translation['translated_content'])) {
            return $this->renderTranslatableMarkup((string) $translation['translated_content']);
        }

        return $content;
    }

    public function filterWooShortDescription(string $description): string
    {
        if (is_admin() || !is_product()) {
            return $description;
        }

        $postId = $this->resolveCurrentPostId();

        if ($postId <= 0) {
            return $description;
        }

        $translation = $this->getTranslationForPost($postId);

        if (!$translation) {
            return $description;
        }

        return !empty($translation['translated_excerpt']) ? (string) $translation['translated_excerpt'] : $description;
    }

    public function filterWooProductDescription(string $description, $product): string
    {
        if (is_admin() || !is_object($product) || !method_exists($product, 'get_id')) {
            return $description;
        }

        $postId = (int) $product->get_id();

        if ($postId <= 0) {
            return $description;
        }

        $translation = $this->getTranslationForPost($postId);

        if (!$translation) {
            return $description;
        }

        if (!empty($translation['custom_html'])) {
            return $this->renderTranslatableMarkup((string) $translation['custom_html']);
        }

        return !empty($translation['translated_content'])
            ? $this->renderTranslatableMarkup((string) $translation['translated_content'])
            : $description;
    }

    public function filterWooProductShortDescription(string $description, $product): string
    {
        if (is_admin() || !is_object($product) || !method_exists($product, 'get_id')) {
            return $description;
        }

        $postId = (int) $product->get_id();

        if ($postId <= 0) {
            return $description;
        }

        $translation = $this->getTranslationForPost($postId);

        if (!$translation) {
            return $description;
        }

        return !empty($translation['translated_excerpt']) ? (string) $translation['translated_excerpt'] : $description;
    }

    private function renderTranslatableMarkup(string $markup): string
    {
        if ($markup == '') {
            return $markup;
        }

        if (function_exists('do_blocks')) {
            $markup = do_blocks($markup);
        }

        $markup = shortcode_unautop($markup);
        $markup = do_shortcode($markup);

        return $markup;
    }

    public function maybeStartFooterOutputBuffer(): void
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $language = LanguageManager::getCurrentLanguage();

        if (LanguageManager::isDefault($language)) {
            return;
        }

        $footerHtml = $this->getRenderedFooterBlockForLanguage($language);

        if ($footerHtml === null) {
            return;
        }

        if ($this->footerBufferStarted) {
            return;
        }

        $this->footerBufferStarted = true;

        ob_start([$this, 'replaceFooterMarkupInBuffer']);
    }

    public function maybeEndFooterOutputBuffer(): void
    {
        if (!$this->footerBufferStarted) {
            return;
        }

        if (ob_get_level() > 0) {
            @ob_end_flush();
        }

        $this->footerBufferStarted = false;
    }

    public function replaceFooterMarkupInBuffer(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        $language = LanguageManager::getCurrentLanguage();

        if (LanguageManager::isDefault($language)) {
            return $html;
        }

        $replacementFooter = $this->getRenderedFooterBlockForLanguage($language);

        if ($replacementFooter === null) {
            return $html;
        }

        $replacementFooter = $this->renderTranslatableMarkup($replacementFooter);

        /**
         * Primary target based on the DOM structure seen on the live site:
         * <footer class="mce-footer"> ... </footer>
         */
        $pattern = '#<footer\b[^>]*class=(["\'])[^"\']*\bmce-footer\b[^"\']*\1[^>]*>.*?</footer>#is';

        $result = preg_replace($pattern, $replacementFooter, $html, 1, $count);

        if (is_string($result) && $count > 0) {
            return $result;
        }

        /**
         * Fallback: replace the entire main footer container if needed.
         */
        $fallbackPattern = '#<div\b[^>]*class=(["\'])[^"\']*\bmain-footer\b[^"\']*\bwd-entry-content\b[^"\']*\1[^>]*>.*?</div>\s*</div>\s*</footer>#is';
        $fallbackReplacement = '<div class="container main-footer wd-entry-content">' . $replacementFooter . '</div></footer>';

        $fallbackResult = preg_replace($fallbackPattern, $fallbackReplacement, $html, 1, $fallbackCount);

        if (is_string($fallbackResult) && $fallbackCount > 0) {
            return $fallbackResult;
        }

        return $html;
    }

    private function getRenderedFooterBlockForLanguage(string $language): ?string
    {
        $settings = get_option(self::SETTINGS_OPTION_KEY, []);

        if (!is_array($settings)) {
            return null;
        }

        $blockIds = $settings['footer_block_ids'] ?? [];

        if (!is_array($blockIds)) {
            return null;
        }

        $blockId = isset($blockIds[$language]) ? (int) $blockIds[$language] : 0;

        if ($blockId <= 0) {
            return null;
        }

        $post = get_post($blockId);

        if (!$post || $post->post_type !== 'cms_block') {
            return null;
        }

        $value = (string) $post->post_content;

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function shouldFilterPost(int $postId): bool
    {
        $language = LanguageManager::getCurrentLanguage();

        if (LanguageManager::isDefault($language)) {
            return false;
        }

        $postType = get_post_type($postId);

        return in_array($postType, ['page', 'post', 'product'], true);
    }

    private function getTranslationForPost(int $postId): ?array
    {
        $language = LanguageManager::getCurrentLanguage();

        if (LanguageManager::isDefault($language)) {
            return null;
        }

        $postType = get_post_type($postId);

        if (!$postType) {
            return null;
        }

        return $this->repository->getTranslation($postId, $postType, $language);
    }
}
