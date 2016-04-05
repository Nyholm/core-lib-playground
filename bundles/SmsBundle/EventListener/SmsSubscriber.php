<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SmsBundle\EventListener;

use Joomla\Http\Http;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\SmsBundle\Event\SmsSendEvent;
use Mautic\SmsBundle\SmsEvents;

/**
 * Class CampaignSubscriber
 *
 * @package MauticSmsBundle
 */
class SmsSubscriber extends CommonSubscriber
{
    /**
     * @var Http
     */
    protected $http;

    /**
     * @var string
     */
    protected $urlRegEx = '/https?\:\/\/([a-zA-Z0-9\-\.]+\.[a-zA-Z]+(\.[a-zA-Z])?)(\/\S*)?/i';

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            SmsEvents::SMS_ON_SEND => array('onSmsSend', 0)
        );
    }

    /**
     * @param SmsSendEvent $event
     */
    public function onSmsSend(SmsSendEvent $event)
    {
        $content = $event->getContent();
        $tokens = array();

        if ($this->contentHasLinks($content)) {
            preg_match_all($this->urlRegEx, $content, $matches);

            foreach ($matches[0] as $url) {
                $tokens[$url] = $this->buildShortLink($url);
            }
        }

        foreach ($tokens as $search => $replace) {
            str_ireplace($search, $replace, $content);
        }

        $event->setContent($content);
    }

    /**
     * Check string for links
     *
     * @param string $content
     *
     * @return bool
     */
    protected function contentHasLinks($content)
    {
        return preg_match($this->urlRegEx, $content);
    }

    /**
     * @param $url
     *
     * @return string
     */
    protected function buildShortLink($url)
    {
        $response = $this->http->get('https://api-ssl.bitly.com/v3/shorten?access_token=080e684d77f2d592a2a5a1fc92978fcfe33cc80d&format=txt&longurl=' . urlencode($url));

        return ($response->code === 200) ? $response->body : $url;
    }
}