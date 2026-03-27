<?php

namespace MCE\Multilang\Frontend;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\DB\TranslationRepository;

class ContentFilter
{
    private TranslationRepository $repository;

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

        $translation = $this->getTranslationForPost($postId);

        if (!$translation) {
            return $content;
        }

        $htmlBlockMarkup = $this->getWoodmartHtmlBlockMarkup($translation);

        if ($htmlBlockMarkup !== '') {
            return $htmlBlockMarkup;
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

        $htmlBlockMarkup = $this->getWoodmartHtmlBlockMarkup($translation);

        if ($htmlBlockMarkup !== '') {
            return $htmlBlockMarkup;
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
        if ($markup === '') {
            return '';
        }

        if (function_exists('do_blocks')) {
            $markup = do_blocks($markup);
        }

        $markup = shortcode_unautop($markup);
        $markup = do_shortcode($markup);

        return $markup;
    }

    private function getWoodmartHtmlBlockMarkup(array $translation): string
    {
        if (empty($translation['id'])) {
            return '';
        }

        $blockRef = $this->repository->getTranslationMeta((int) $translation['id'], 'html_block_ref');

        if (!is_string($blockRef) || trim($blockRef) === '') {
            return '';
        }

        $parsedBlock = $this->normalizeWoodmartBlockReference(trim($blockRef));

        if (empty($parsedBlock['id']) && empty($parsedBlock['slug'])) {
            return '';
        }

        if (!empty($parsedBlock['id'])) {
            return $this->renderTranslatableMarkup('[html_block id="' . (int) $parsedBlock['id'] . '"]');
        }

        $blockPost = $this->resolveWoodmartHtmlBlockBySlug((string) $parsedBlock['slug']);

        if (!$blockPost instanceof \WP_Post) {
            return '';
        }

        return $this->renderTranslatableMarkup('[html_block id="' . (int) $blockPost->ID . '"]');
    }

    private function normalizeWoodmartBlockReference(string $blockRef): array
    {
        $blockRef = trim($blockRef);

        if ($blockRef === '') {
            return ['id' => 0, 'slug' => ''];
        }

        if (ctype_digit($blockRef)) {
            return ['id' => (int) $blockRef, 'slug' => ''];
        }

        if (preg_match('/\[(?:html_block|woodmart_html_block)\s+[^\]]*id=["\']?(\d+)["\']?[^\]]*\]/i', $blockRef, $matches)) {
            return ['id' => (int) $matches[1], 'slug' => ''];
        }

        if (preg_match('/\[(?:html_block|woodmart_html_block)\s+[^\]]*slug=["\']?([^"\']+)["\']?[^\]]*\]/i', $blockRef, $matches)) {
            return ['id' => 0, 'slug' => sanitize_title($matches[1])];
        }

        return ['id' => 0, 'slug' => sanitize_title($blockRef)];
    }

    private function resolveWoodmartHtmlBlockBySlug(string $slug): ?\WP_Post
    {
        if ($slug === '') {
            return null;
        }

        $postTypes = [
            'cms_block',
            'html_block',
            'woodmart_html_block',
        ];

        foreach ($postTypes as $postType) {
            $post = get_page_by_path($slug, OBJECT, $postType);

            if ($post instanceof \WP_Post) {
                return $post;
            }
        }

        return null;
    }

    private function resolveWoodmartHtmlBlock(string $blockRef): ?\WP_Post
    {
        $parsedBlock = $this->normalizeWoodmartBlockReference($blockRef);

        $postTypes = [
            'cms_block',
            'html_block',
            'woodmart_html_block',
        ];

        if (!empty($parsedBlock['id'])) {
            $post = get_post((int) $parsedBlock['id']);

            if ($post instanceof \WP_Post && in_array($post->post_type, $postTypes, true)) {
                return $post;
            }
        }

        if (!empty($parsedBlock['slug'])) {
            $post = $this->resolveWoodmartHtmlBlockBySlug((string) $parsedBlock['slug']);

            if ($post instanceof \WP_Post) {
                return $post;
            }
        }

        return null;
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
