<?php

namespace MCE\Multilang\Frontend;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\DB\TranslationRepository;

class ContentFilter
{
    private TranslationRepository $repository;
    private const SETTINGS_OPTION_KEY = 'mce_multilang_settings';

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
    }

    public function register(): void
    {
        add_filter('the_title', [$this, 'filterTitle'], 20, 2);
        add_filter('get_the_excerpt', [$this, 'filterExcerpt'], 20, 2);
        add_filter('the_content', [$this, 'filterContent'], 20);

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

        $footerOverride = $this->getFooterOverrideForPost($postId);

        if ($footerOverride !== null) {
            return $this->renderTranslatableMarkup($footerOverride);
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

    private function getFooterOverrideForPost(int $postId): ?string
    {
        $language = LanguageManager::getCurrentLanguage();

        if (LanguageManager::isDefault($language)) {
            return null;
        }

        $postType = get_post_type($postId);

        if ($postType !== 'cms_block') {
            return null;
        }

        $settings = get_option(self::SETTINGS_OPTION_KEY, []);

        if (!is_array($settings)) {
            return null;
        }

        $footerBlockId = isset($settings['footer_block_id']) ? (int) $settings['footer_block_id'] : 0;

        if ($footerBlockId <= 0 || $footerBlockId !== $postId) {
            return null;
        }

        $footerHtml = $settings['footer_html'] ?? [];

        if (!is_array($footerHtml)) {
            return null;
        }

        $value = $footerHtml[$language] ?? '';

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $value;
    }

    private function shouldFilterPost(int $postId): bool
    {
        $language = LanguageManager::getCurrentLanguage();

        if (LanguageManager::isDefault($language)) {
            return false;
        }

        $postType = get_post_type($postId);

        return in_array($postType, ['page', 'post', 'product', 'cms_block'], true);
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
