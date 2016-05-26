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
    protected $namesBySlug;

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

        $names = array_unique($names);
        $slugs = $this->slugifyNames($names);

        $builder = $this->em->createQueryBuilder();
        $tags = $builder
            ->select('t')
            ->from($this->tagClass, 't')
            ->where($builder->expr()->in('t.slug', $slugs))
            ->getQuery()
            ->getResult()
        ;
        $loadedSlugs = $namesForSlugs = array();
        foreach ($tags as $tag) {
            $loadedSlugs[] = $tag->getSlug();
            $namesForSlugs[$tag->getSlug()] = $tag->getName();
        }
        $missingSlugs = array_udiff($slugs, $loadedSlugs, 'strcasecmp');
        if (sizeof($missingSlugs)) {
            foreach ($missingSlugs as $slug) {
                $tag = $this->createTag($this->namesBySlug[$slug]);
                $this->em->persist($tag);
                $tags[] = $tag;
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
                $this->namesBySlug[$slug] = $name;
            }
        }

        return $newNames;
    }
}
