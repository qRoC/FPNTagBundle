<?php

/*
 * This file is part of the FPNTagBundle package.
 * (c) 2011 Fabien Pennequin <fabien@pennequin.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FPN\TagBundle\Entity;

use DoctrineExtensions\Taggable\TagManager as BaseTagManager;
use Doctrine\ORM\EntityManager;
use Cocur\Slugify\Slugify;

class TagManager extends BaseTagManager
{
    protected $slugifier;

    /**
     * @see DoctrineExtensions\Taggable\TagManager::__construct()
     */
    public function __construct(EntityManager $em, $tagClass = null, $taggingClass = null)
    {
        parent::__construct($em, $tagClass, $taggingClass);
        $this->slugifier = new Slugify();
    }

    /**
     * @see DoctrineExtensions\Taggable\TagManager::createTag()
     */
    protected function createTag($name)
    {
        $tag = parent::createTag($name);
        $tag->setSlug($this->slugifier->slugify($name));

        return $tag;
    }

    /**
     * @see DoctrineExtensions\Taggable\TagManager::loadOrCreateTags()
     */
    public function loadOrCreateTags(array $names)
    {
        if (empty($names)) {
            return array();
        }

        $names = $this->slugifyNames($names);

        $names = array_unique($names);
        $builder = $this->em->createQueryBuilder();
        $tags = $builder
            ->select('t')
            ->from($this->tagClass, 't')
            ->where($builder->expr()->in('t.slug', $names))
            ->getQuery()
            ->getResult()
        ;
        $loadedNames = array();
        foreach ($tags as $tag) {
            $loadedNames[] = $tag->getName();
        }
        $missingNames = array_udiff($names, $loadedNames, 'strcasecmp');
        if (sizeof($missingNames)) {
            foreach ($missingNames as $name) {
                $tag = $this->createTag($name);
                $this->em->persist($tag);
                $tags[] = $tag;4
            }
            $this->em->flush();
        }
        return $tags;
    }

    protected function slugifyNames($names)
    {
        $newNames = array();

        foreach ($names as $name) {
            $slug = $this->slugifier->slugify($name);

            if (!in_array($slug, $newNames)) {
                $newNames[] = $slug;
            }
        }

        return $newNames;
    }
}
