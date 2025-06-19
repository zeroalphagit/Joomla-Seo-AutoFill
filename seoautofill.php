<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Language\Text;
use Exception;

class PlgSystemSeoAutoFill extends CMSPlugin
{
    protected $app;
    protected $autoloadLanguage = false;

    public function onBeforeRender()
    {
        try {
            // Only run in site (frontend)
            if (Factory::getApplication()->isClient('administrator')) {
                return;
            }

            $doc = Factory::getDocument();

            // Only run for HTML output
            if (!$doc instanceof HtmlDocument) {
                return;
            }

            // Skip if meta description already set
            if ($doc->getMetaData('description')) {
                return;
            }

            $input = Factory::getApplication()->input;
            $option = $input->getCmd('option');
            $view = $input->getCmd('view');
            $id = $input->getInt('id');

            // Only run for com_content single article view
            if ($option !== 'com_content' || $view !== 'article' || !$id) {
                return;
            }

            // Get the article content
            $article = $this->getArticleContent($id);

            // Check if it's public
            if ($article && $article->access === 1) {
                $introtext = $article->introtext ?? '';
                $metaDescription = $this->generateMetaDescription($introtext);

                if ($metaDescription) {
                    $doc->setMetaData('description', $metaDescription);
                    $doc->addCustomTag('<!-- SeoAutoFill plugin: Meta description added -->');
                } else {
                    $doc->addCustomTag('<!-- SeoAutoFill plugin: Description could not be generated. -->');
                }
            }
        } catch (Exception $e) {
            // Log errors without breaking page rendering
            Log::add('SeoAutoFill error in onBeforeRender: ' . $e->getMessage(), Log::ERROR, 'plg_system_seoautofill');
        }
    }

    private function getArticleContent($id)
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                        ->select($db->quoteName(['introtext', 'access']))
                        ->from($db->quoteName('#__content'))
                        ->where($db->quoteName('id') . ' = ' . (int) $id);
            $db->setQuery($query);

            return $db->loadObject();
        } catch (Exception $e) {
            Log::add('SeoAutoFill error retrieving article: ' . $e->getMessage(), Log::ERROR, 'plg_system_seoautofill');
            return null;
        }
    }

    private function generateMetaDescription($content)
    {
        try {
            // Remove <h1> heading
            $content = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $content, 1);

            // Strip HTML and normalize text
            $text = strip_tags($content);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            // Limit to 160 characters without cutting mid-word
            if (strlen($text) > 160) {
                $text = substr($text, 0, 160);
                $lastSpace = strrpos($text, ' ');
                if ($lastSpace !== false) {
                    $text = substr($text, 0, $lastSpace);
                }
            }

            return $text;
        } catch (Exception $e) {
            Log::add('SeoAutoFill error generating meta description: ' . $e->getMessage(), Log::ERROR, 'plg_system_seoautofill');
            return '';
        }
    }

    protected function getFirstImageFromContent($content)
    {
        // Extract the first image (jpg, png, webp)
        preg_match('/<img[^>]+src=["\']([^"\']+\.(jpg|png|webp))["\'][^>]*>/i', $content, $matches);

        if (isset($matches[1])) {
            $imageUrl = $matches[1];

            // Make relative URLs absolute
            if (parse_url($imageUrl, PHP_URL_SCHEME) === null) {
                $imageUrl = Uri::root() . ltrim($imageUrl, '/');
            }

            return ['url' => $imageUrl];
        }

        return ['url' => ''];
    }

    public function onBeforeCompileHead()
    {
        $app = Factory::getApplication();

        // Only run in frontend
        if ($app->isClient('administrator')) {
            return;
        }

        $document = Factory::getDocument();
        $metas = $document->getHeadData();

        // Base OG and Twitter values
        $ogTitle = $metas['title'] ?? '';
        $ogType = 'website';
        $ogUrl = Uri::current();
        $ogImage = '';
        $ogDescription = $metas['description'] ?? '';

        $twitterCardType = 'summary_large_image';
        $twitterTitle = $ogTitle;
        $twitterDescription = $ogDescription;
        $twitterImage = $ogImage;

        $siteName = Factory::getConfig()->get('sitename');

        // Only run for com_content article view
        $itemId = $app->input->getInt('id');
        $view = $app->input->getCmd('view');

        if (!$itemId || $view !== 'article') {
            return;
        }

        try {
            // Attempt to load article to extract image
            $articleModel = BaseDatabaseModel::getInstance('Article', 'ContentModel');
            $article = $articleModel->getItem($itemId);

            if ($article) {
                $introtext = $article->introtext ?? '';
                $fulltext = $article->fulltext ?? '';
                $imageData = $this->getFirstImageFromContent($introtext . $fulltext);
                $ogImage = $imageData['url'];
                $twitterImage = $ogImage;

                $document->addCustomTag('<!-- SeoAutoFill plugin: OG + Twitter meta generated -->');
            }
        } catch (Exception $e) {
            // Gracefully ignore if article doesn't exist (404)
            if ($e->getCode() === 404) {
                return;
            }
            Log::add('SeoAutoFill error in onBeforeCompileHead: ' . $e->getMessage(), Log::ERROR, 'plg_system_seoautofill');
            return;
        }

        // Open Graph tags
        if ($ogTitle) {
            $document->addCustomTag('<meta property="og:title" content="' . htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') . '" />');
        }
        if ($ogType) {
            $document->addCustomTag('<meta property="og:type" content="' . htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8') . '" />');
        }
        if ($ogUrl) {
            $document->addCustomTag('<meta property="og:url" content="' . htmlspecialchars($ogUrl, ENT_QUOTES, 'UTF-8') . '" />');
        }
        if ($ogImage) {
            $document->addCustomTag('<meta property="og:image" content="' . htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') . '" />');
        }
        if ($ogDescription) {
            $document->addCustomTag('<meta property="og:description" content="' . htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8') . '" />');
        }
        if ($siteName) {
            $document->addCustomTag('<meta property="og:site_name" content="' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '" />');
        }

        // Twitter tags
        $document->addCustomTag('<meta name="twitter:card" content="' . htmlspecialchars($twitterCardType, ENT_QUOTES, 'UTF-8') . '" />');
        if ($twitterTitle) {
            $document->addCustomTag('<meta name="twitter:title" content="' . htmlspecialchars($twitterTitle, ENT_QUOTES, 'UTF-8') . '" />');
        }
        if ($twitterDescription) {
            $document->addCustomTag('<meta name="twitter:description" content="' . htmlspecialchars($twitterDescription, ENT_QUOTES, 'UTF-8') . '" />');
        }
        if ($twitterImage) {
            $document->addCustomTag('<meta name="twitter:image" content="' . htmlspecialchars($twitterImage, ENT_QUOTES, 'UTF-8') . '" />');
        }

        // Canonical link
        $canonicalUrl = Uri::current();
        $document->addCustomTag('<link rel="canonical" href="' . htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') . '" />');
    }
}