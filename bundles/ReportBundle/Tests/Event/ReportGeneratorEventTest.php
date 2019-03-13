<?php

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ReportBundle\Tests\Entity;

use Doctrine\DBAL\Query\QueryBuilder;
use Mautic\ChannelBundle\Helper\ChannelListHelper;
use Mautic\ReportBundle\Entity\Report;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;

class ReportGeneratorEventTest extends \PHPUnit_Framework_TestCase
{
    private $report;
    private $queryBuilder;
    private $channelListHelper;
    private $reportGeneratorEvent;

    protected function setUp()
    {
        parent::setUp();

        defined('MAUTIC_TABLE_PREFIX') || define('MAUTIC_TABLE_PREFIX', getenv('MAUTIC_DB_PREFIX') ?: '');

        $this->report               = $this->createMock(Report::class);
        $this->queryBuilder         = $this->createMock(QueryBuilder::class);
        $this->channelListHelper    = $this->createMock(ChannelListHelper::class);
        $this->reportGeneratorEvent = new ReportGeneratorEvent(
            $this->report,
            [], // Use the setter if you need different options
            $this->queryBuilder,
            $this->channelListHelper
        );
    }

    public function testAddCategoryLeftJoinWhenColumnIsNotUsed()
    {
        $this->report->expects($this->once())
            ->method('getSelectAndAggregatorAndOrderAndGroupByColumns')
            ->willReturn(['e.id', 'comp.name']);

        $this->queryBuilder->expects($this->never())
            ->method('leftJoin');

        $this->reportGeneratorEvent->addCategoryLeftJoin($this->queryBuilder, 'e');
    }

    public function testAddCategoryLeftJoinWhenColumnIsUsed()
    {
        $this->report->expects($this->once())
            ->method('getSelectAndAggregatorAndOrderAndGroupByColumns')
            ->willReturn(
                ['e.id', ReportGeneratorEvent::CATEGORY_PREFIX.'.title', 'comp.name']
            );

        $this->queryBuilder->expects($this->once())
            ->method('leftJoin')
            ->with(
                'e',
                MAUTIC_TABLE_PREFIX.'categories',
                ReportGeneratorEvent::CATEGORY_PREFIX,
                ReportGeneratorEvent::CATEGORY_PREFIX.'.id = e.category_id'
            );

        $this->reportGeneratorEvent->addCategoryLeftJoin($this->queryBuilder, 'e');
    }

    public function testAddLeadLeftJoinWhenColumnIsNotUsed()
    {
        $this->report->expects($this->exactly(5))
            ->method('getSelectAndAggregatorAndOrderAndGroupByColumns')
            ->willReturn(['e.id', 'h.name']);

        $this->queryBuilder->expects($this->never())
            ->method('leftJoin');

        $this->reportGeneratorEvent->addLeadLeftJoin($this->queryBuilder, 'e');
    }

    public function testAddLeadLeftJoinWhenColumnIsUsed()
    {
        $this->report->expects($this->once())
            ->method('getSelectAndAggregatorAndOrderAndGroupByColumns')
            ->willReturn(
                ['e.id', ReportGeneratorEvent::CONTACT_PREFIX.'.email', 'comp.name']
            );

        $this->queryBuilder->expects($this->once())
            ->method('leftJoin')
            ->with(
                'e',
                MAUTIC_TABLE_PREFIX.'leads',
                ReportGeneratorEvent::CONTACT_PREFIX,
                ReportGeneratorEvent::CONTACT_PREFIX.'.id = e.lead_id'
            );

        $this->reportGeneratorEvent->addLeadLeftJoin($this->queryBuilder, 'e');
    }

    public function testAddLeadLeftJoinWhenCampaignIdColumnIsUsed()
    {
        $this->report->expects($this->exactly(5))
            ->method('getSelectAndAggregatorAndOrderAndGroupByColumns')
            ->willReturn(
                ['e.id', 'clel.campaign_id', 'h.name']
            );

        $this->queryBuilder->expects($this->once())
            ->method('leftJoin')
            ->with(
                'e',
                MAUTIC_TABLE_PREFIX.'leads',
                ReportGeneratorEvent::CONTACT_PREFIX,
                ReportGeneratorEvent::CONTACT_PREFIX.'.id = e.lead_id'
            );

        $this->reportGeneratorEvent->addLeadLeftJoin($this->queryBuilder, 'e');
    }

    public function testAddIpAddressLeftJoinWhenColumnIsNotUsed()
    {
        $this->report->expects($this->once())
            ->method('getSelectAndAggregatorAndOrderAndGroupByColumns')
            ->willReturn(['e.id', 't.name']);

        $this->queryBuilder->expects($this->never())
            ->method('leftJoin');

        $this->reportGeneratorEvent->addIpAddressLeftJoin($this->queryBuilder, 'e');
    }

    public function testAddIpAddressLeftJoinWhenColumnIsUsed()
    {
        $this->report->expects($this->once())
            ->method('getSelectAndAggregatorAndOrderAndGroupByColumns')
            ->willReturn(
                ['e.id', ReportGeneratorEvent::IP_ADDRESS_PREFIX.'.address', 'comp.name']
            );

        $this->queryBuilder->expects($this->once())
            ->method('leftJoin')
            ->with(
                'e',
                MAUTIC_TABLE_PREFIX.'ip_addresses',
                ReportGeneratorEvent::IP_ADDRESS_PREFIX,
                ReportGeneratorEvent::IP_ADDRESS_PREFIX.'.id = e.ip_id'
            );

        $this->reportGeneratorEvent->addIpAddressLeftJoin($this->queryBuilder, 'e');
    }

    public function testHasColumnWithPrefix()
    {
        $this->report->method('getSelectAndAggregatorAndOrderAndGroupByColumns')
            ->willReturn(['e.id', 'c.first_name', 'comp.name']);

        $this->assertTrue($this->reportGeneratorEvent->hasColumnWithPrefix('e'));
        $this->assertTrue($this->reportGeneratorEvent->hasColumnWithPrefix('c'));
        $this->assertTrue($this->reportGeneratorEvent->hasColumnWithPrefix('comp'));
        $this->assertFalse($this->reportGeneratorEvent->hasColumnWithPrefix('a'));
        $this->assertFalse($this->reportGeneratorEvent->hasColumnWithPrefix('lump'));
        $this->assertFalse($this->reportGeneratorEvent->hasColumnWithPrefix('c.'));
    }
}
