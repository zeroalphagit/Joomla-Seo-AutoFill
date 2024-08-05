<?php
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Log\LogEntry;
use Joomla\Log\Logger;
use Exception;

class PlgSystemSeoAutoFill extends CMSPlugin
{
    protected $app;
    protected $autoloadLanguage = false;

    public function onBeforeRender()
    {
        try {
            // Ensure we are in the frontend
            if (Factory::getApplication()->isClient('administrator')) {
                return;
            }

            $doc = Factory::getDocument();

            // Ensure we are dealing with an HTML document
            if (!$doc instanceof HtmlDocument) {
                return;
            }

            // Check if meta description already exists
            if ($doc->getMetaData('description')) {
                return;
            }

            // Get the current view and option
            $input = Factory::getApplication()->input;
            $option = $input->getCmd('option');
            $view = $input->getCmd('view');
            $id = $input->getInt('id');

            // Only proceed for single article view
            if ($option === 'com_content' && $view === 'article' && $id) {
                $article = $this->getArticleContent($id);
                if ($article && $article->access === 1) { // Check if access level is Public (1)
                    $metaDescription = $this->generateMetaDescription($article->introtext);
                    if ($metaDescription) {
                        $doc->setMetaData('description', $metaDescription);
                    } else {
                        $doc->addCustomTag('<!-- Seo AutoFill: Description could not be generated. -->');
                    }
                }
            }
        } catch (Exception $e) {
            // Log the error
            $logger = Logger::getInstance();
            $logger->add(new LogEntry('Seo AutoFill plugin error: ' . $e->getMessage(), LogEntry::ERROR));

            // Notify the user
            Factory::getApplication()->enqueueMessage('An error occurred in the Seo AutoFill plugin while processing SEO auto-fill. Please try again later.', 'error');
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

            return $db->loadObject(); // Use loadObject to get both content and access level
        } catch (Exception $e) {
            // Log the error
            $logger = Logger::getInstance();
            $logger->add(new LogEntry('Seo AutoFill plugin error retrieving article content: ' . $e->getMessage(), LogEntry::ERROR));

            // Notify the user
            Factory::getApplication()->enqueueMessage('An error occurred in the Seo AutoFill plugin while retrieving article content. Please try again later.', 'error');
            return null; // Return null if there's an error
        }
    }

    private function generateMetaDescription($content)
    {
        try {
            // Remove the first <h1> tag and its content
            $content = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $content, 1);
    
            // Remove all HTML tags
            $text = strip_tags($content);
    
            // Decode HTML entities
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    
            // Remove non-printable characters
            $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    
            // Normalize whitespace: replace multiple spaces and newlines with a single space
            $text = preg_replace('/\s+/', ' ', $text);
    
            // Ensure no leading or trailing spaces
            $text = trim($text);
    
            // Trim to 160 characters at the last complete word
            if (strlen($text) > 160) {
                // Find the position of the last space within the 160 characters limit
                $text = substr($text, 0, 160);
                $lastSpace = strrpos($text, ' ');
                if ($lastSpace !== false) {
                    $text = substr($text, 0, $lastSpace);
                }
            }
    
            return $text;
        } catch (Exception $e) {
            // Log the error
            $logger = Logger::getInstance();
            $logger->add(new LogEntry('Seo AutoFill plugin error generating meta description: ' . $e->getMessage(), LogEntry::ERROR));
    
            // Notify the user
            Factory::getApplication()->enqueueMessage('An error occurred while generating the meta description. Please try again later.', 'error');
            return ''; // Return empty if there's an error
        }
    }  

    protected function getFirstImageFromContent($content)
    {
        // Use preg_match to find the first image URL with specified file extensions
        preg_match('/<img[^>]+src=["\']([^"\']+\.(jpg|png|webp))["\'][^>]*>/i', $content, $matches);

        if (isset($matches[1])) {
            $imageUrl = $matches[1];

            // Convert relative URL to absolute URL
            if (parse_url($imageUrl, PHP_URL_SCHEME) === null) {
                // Relative URL; make it absolute
                $imageUrl = Uri::root() . ltrim($imageUrl, '/');
            }

            return ['url' => $imageUrl];
        }

        return ['url' => ''];
    }

    public function onBeforeCompileHead()
    {
        $app = Factory::getApplication();

        if ($app->isClient('administrator')) {
            return;
        }

        $document = Factory::getDocument();
        $metas = $document->getHeadData();

        // Extract title and URL from existing meta data
        $ogTitle = isset($metas['title']) ? $metas['title'] : '';
        $ogType = 'website'; // Default type
        $ogUrl = Uri::current();
        $ogImage = ''; // Default image URL set to empty
        $ogDescription = isset($metas['description']) ? $metas['description'] : '';

        // Twitter Card variables
        $twitterCardType = 'summary_large_image'; // Default type
        $twitterTitle = $ogTitle;
        $twitterDescription = $ogDescription;
        $twitterImage = $ogImage;

        // Fetch site name from global configuration
        $siteName = Factory::getConfig()->get('sitename');

        // Retrieve the article content and find the first image
        $itemId = $app->input->getInt('id'); // Get the article ID
        if ($itemId) {
            $articleModel = BaseDatabaseModel::getInstance('Article', 'ContentModel');
            $article = $articleModel->getItem($itemId);

            if ($article) {
                // Extract the first image from the article content
                $imageData = $this->getFirstImageFromContent($article->introtext . $article->fulltext);
                $ogImage = $imageData['url'];

                // Set Twitter image to Open Graph image
                $twitterImage = $ogImage;
            }
        }

        // Add Open Graph tags first
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

        // Add Twitter Card tags after Open Graph
        if ($twitterTitle) {
            $document->addCustomTag('<meta name="twitter:title" content="' . htmlspecialchars($twitterTitle, ENT_QUOTES, 'UTF-8') . '" />');
        }
        if ($twitterDescription) {
            $document->addCustomTag('<meta name="twitter:description" content="' . htmlspecialchars($twitterDescription, ENT_QUOTES, 'UTF-8') . '" />');
        }
        if ($twitterImage) {
            $document->addCustomTag('<meta name="twitter:image" content="' . htmlspecialchars($twitterImage, ENT_QUOTES, 'UTF-8') . '" />');
        }

        // Add Twitter Card type
        $document->addCustomTag('<meta name="twitter:card" content="' . htmlspecialchars($twitterCardType, ENT_QUOTES, 'UTF-8') . '" />');

        // Add canonical tag
        $canonicalUrl = Uri::current();
        $document->addCustomTag('<link rel="canonical" href="' . htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') . '" />');
    }
}
?>
