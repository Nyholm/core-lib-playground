<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\PointBundle\Event\TriggerBuilderEvent;
use Mautic\PointBundle\PointEvents;

/**
 * Class PointSubscriber
 */
class PointSubscriber extends CommonSubscriber
{

    /**
     * {@inheritdoc}
     */
    static public function getSubscribedEvents()
    {
        return array(
            PointEvents::TRIGGER_ON_BUILD   => array('onTriggerBuild', 0)
        );
    }

    /**
     * @param TriggerBuilderEvent $event
     */
    public function onTriggerBuild(TriggerBuilderEvent $event)
    {
        $changeLists = array(
            'label'       => 'mautic.campaign.point.trigger.changecampaigns',
            'callback'    => array('\\Mautic\\CampaignBundle\\Helper\\PointEventHelper', 'changeCampaigns'),
            'formType'    => 'campaignevent_addremovelead'
        );

        $event->addEvent('campaign.changecampaign', $changeLists);
    }

}
