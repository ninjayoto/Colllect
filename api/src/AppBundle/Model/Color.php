<?php

namespace AppBundle\Model;

class Color extends Element
{
    /**
     * {@inheritdoc}
     */
    public function shouldLoadContent()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function setContent($content)
    {
        return $this;
    }
}