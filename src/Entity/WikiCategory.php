<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @Entity()
 * @Table(name="wiki_categories", options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4"})
 */
class WikiCategory implements WikiAccessInterface {

    /**
     * @Id()
     * @GeneratedValue()
     * @Column(type="integer")
     */
    private $id;

    /**
     * @ManyToOne(targetEntity="WikiCategory", inversedBy="categories")
     * @JoinColumn(name="parent", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     */
    private $parent;

    /**
     * @Column(type="string")
     * @Assert\NotBlank()
     */
    private $name;

    /**
     * @Column(type="string", length=32)
     */
    private $slug;

    /**
     * @OneToMany(targetEntity="WikiCategory", mappedBy="parent")
     */
    private $categories;

    /**
     * @OneToMany(targetEntity="WikiArticle", mappedBy="category")
     */
    private $articles;

    /**
     * @Column(type="string")
     */
    private $access = WikiAccessInterface::ACCESS_INHERIT;

    public function __construct() {
        $this->categories = new ArrayCollection();
        $this->articles = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return WikiCategory|null
     */
    public function getParent() {
        return $this->parent;
    }

    /**
     * @param WikiCategory|null $parent
     * @return WikiCategory
     */
    public function setParent(WikiCategory $parent = null) {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $name
     * @return WikiCategory
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getSlug() {
        return $this->slug;
    }

    /**
     * @param string $slug
     * @return WikiCategory
     */
    public function setSlug($slug) {
        $this->slug = $slug;
        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getCategories() {
        return $this->categories;
    }

    /**
     * @param WikiCategory $category
     */
    public function addCategory(WikiCategory $category) {
        $this->categories->add($category);
    }

    /**
     * @param WikiCategory $category
     */
    public function removeCategory(WikiCategory $category) {
        $this->categories->removeElement($category);
    }

    /**
     * @return ArrayCollection
     */
    public function getArticles() {
        return $this->articles;
    }

    /**
     * @param WikiArticle $article
     */
    public function addArticle(WikiArticle $article) {
        $this->articles->add($article);
    }

    /**
     * @param WikiArticle $article
     */
    public function removeArticle(WikiArticle $article) {
        $this->articles->removeElement($article);
    }

    /**
     * @return string
     */
    public function getAccess() {
        return $this->access;
    }

    /**
     * @param string $access
     * @return WikiCategory
     */
    public function setAccess($access) {
        $this->access = $access;
        return $this;
    }

}