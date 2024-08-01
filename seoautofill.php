<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Application\CMSApplication;
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
            // Convert <br> to a space
            $content = preg_replace('/<br\s*\/?>/i', ' ', $content);
            // Decode HTML entities
            $content = html_entity_decode($content);
            // Strip remaining HTML tags
            $text = strip_tags($content);

            // Extract sentences up to the character limit (160)
            $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
            $metaDescription = '';
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (strlen($metaDescription) + strlen($sentence) + 1 > 160) {
                    break;
                }
                // Add a space before the sentence if $metaDescription is not empty
                $metaDescription .= ($metaDescription ? ' ' : '') . $sentence;
            }

            // Remove extra spaces from beginning and end
            return trim($metaDescription);
        } catch (Exception $e) {
            // Log the error
            $logger = Logger::getInstance();
            $logger->add(new LogEntry('Seo AutoFill plugin error generating meta description: ' . $e->getMessage(), LogEntry::ERROR));

            // Notify the user
            Factory::getApplication()->enqueueMessage('An error occurred in the Seo AutoFill plugin while generating the meta description. Please try again later.', 'error');
            return ''; // Return empty if there's an error
        }
    }
}
?>
