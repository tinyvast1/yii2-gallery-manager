<?php

namespace aquy\gallery;

class GalleryImage
{
    public $name;
    public $description;
    public $id;
    public $sort;
    public $src;
    /**
     * @var GalleryBehavior
     */
    protected $galleryBehavior;

    /**
     * @param GalleryBehavior $galleryBehavior
     * @param array           $props
     */
    function __construct(GalleryBehavior $galleryBehavior, array $props)
    {

        $this->galleryBehavior = $galleryBehavior;

        $this->name = isset($props['name']) ? $props['name'] : '';
        $this->description = isset($props['description']) ? $props['description'] : '';
        $this->id = isset($props['id']) ? $props['id'] : '';
        $this->sort = isset($props['sort']) ? $props['sort'] : '';
        $this->src = isset($props['src']) ? $props['src'] : '';
    }

    public function getUrl()
    {
        return $this->galleryBehavior->getUrl($this->src);
    }

    public function getFilePath()
    {
        return $this->galleryBehavior->getFilePath($this->src);
    }

}
