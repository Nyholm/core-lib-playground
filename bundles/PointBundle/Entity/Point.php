<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PointBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class Point
 *
 * @package Mautic\PointBundle\Entity
 *
 * @Serializer\ExclusionPolicy("all")
 */
class Point extends FormEntity
{

    /**
     * @var int
     *
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"pointDetails", "pointList"})
     */
    private $id;

    /**
     * @var string
     *
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"pointDetails", "pointList"})
     */
    private $name;

    /**
     * @var string
     *
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"pointDetails", "pointList"})
     */
    private $description;

    /**
     * @var string
     *
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"pointDetails", "pointList"})
     */
    private $type;


    /**
     * @var \DateTime
     *
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"pointDetails"})
     */
    private $publishUp;

    /**
     * @var \DateTime
     *
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"pointDetails"})
     */
    private $publishDown;

    /**
     * @var int
     *
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"pointDetails"})
     */
    private $delta = 0;

    /**
     * @var array
     *
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"pointDetails"})
     */
    private $properties = array();

    /**
     * @var ArrayCollection
     */
    private $log;

    /**
     * @var \Mautic\CategoryBundle\Entity\Category
     *
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"pointDetails", "pointList"})
     **/
    private $category;

    /**
     * Construct
     */
    public function __construct ()
    {
        $this->log = new ArrayCollection();
    }

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata (ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('points')
            ->setCustomRepositoryClass('Mautic\PointBundle\Entity\PointRepository');

        $builder->addIdColumns();

        $builder->createField('type', 'string')
            ->length(50)
            ->build();

        $builder->addPublishDates();

        $builder->addField('delta', 'integer');

        $builder->addField('properties', 'array');

        $builder->createOneToMany('log', 'LeadPointLog')
            ->mappedBy('point')
            ->cascadePersist()
            ->cascadeRemove()
            ->fetchExtraLazy()
            ->build();

        $builder->addCategory();
    }

    /**
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata (ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('name', new Assert\NotBlank(array(
            'message' => 'mautic.core.name.required'
        )));

        $metadata->addPropertyConstraint('type', new Assert\NotBlank(array(
            'message' => 'mautic.point.type.notblank'
        )));
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId ()
    {
        return $this->id;
    }

    /**
     * Set properties
     *
     * @param array $properties
     *
     * @return Action
     */
    public function setProperties ($properties)
    {
        $this->isChanged('properties', $properties);

        $this->properties = $properties;

        return $this;
    }

    /**
     * Get properties
     *
     * @return array
     */
    public function getProperties ()
    {
        return $this->properties;
    }

    /**
     * Set type
     *
     * @param string $type
     *
     * @return Action
     */
    public function setType ($type)
    {
        $this->isChanged('type', $type);
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType ()
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function convertToArray ()
    {
        return get_object_vars($this);
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Action
     */
    public function setDescription ($description)
    {
        $this->isChanged('description', $description);
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription ()
    {
        return $this->description;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Action
     */
    public function setName ($name)
    {
        $this->isChanged('name', $name);
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName ()
    {
        return $this->name;
    }

    /**
     * Add log
     *
     * @param LeadPointLog $log
     *
     * @return Log
     */
    public function addLog (LeadPointLog $log)
    {
        $this->log[] = $log;

        return $this;
    }

    /**
     * Remove log
     *
     * @param LeadPointLog $log
     */
    public function removeLog (LeadPointLog $log)
    {
        $this->log->removeElement($log);
    }

    /**
     * Get log
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getLog ()
    {
        return $this->log;
    }

    /**
     * Set publishUp
     *
     * @param \DateTime $publishUp
     *
     * @return Point
     */
    public function setPublishUp ($publishUp)
    {
        $this->isChanged('publishUp', $publishUp);
        $this->publishUp = $publishUp;

        return $this;
    }

    /**
     * Get publishUp
     *
     * @return \DateTime
     */
    public function getPublishUp ()
    {
        return $this->publishUp;
    }

    /**
     * Set publishDown
     *
     * @param \DateTime $publishDown
     *
     * @return Point
     */
    public function setPublishDown ($publishDown)
    {
        $this->isChanged('publishDown', $publishDown);
        $this->publishDown = $publishDown;

        return $this;
    }

    /**
     * Get publishDown
     *
     * @return \DateTime
     */
    public function getPublishDown ()
    {
        return $this->publishDown;
    }

    /**
     * @return mixed
     */
    public function getCategory ()
    {
        return $this->category;
    }

    /**
     * @param mixed $category
     */
    public function setCategory ($category)
    {
        $this->category = $category;
    }

    /**
     * @return mixed
     */
    public function getDelta ()
    {
        return $this->delta;
    }

    /**
     * @param mixed $delta
     */
    public function setDelta ($delta)
    {
        $this->delta = (int)$delta;
    }
}
