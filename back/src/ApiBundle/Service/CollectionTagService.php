<?php

namespace ApiBundle\Service;

use ApiBundle\EnhancedFlysystemAdapter\EnhancedFilesystemInterface;
use ApiBundle\FilesystemAdapter\FilesystemAdapterManager;
use ApiBundle\Form\Type\TagType;
use ApiBundle\Model\Element;
use ApiBundle\Model\ElementFile;
use ApiBundle\Model\Tag;
use ApiBundle\Util\Base64;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CollectionTagService
{
    /**
     * @var CollectionElementService
     */
    private $collectionElementService;

    /**
     * @var CollectionTagFileService
     */
    private $collectionTagFileService;

    /**
     * @var FormFactory
     */
    private $formFactory;


    public function __construct(
        CollectionElementService $collectionElementService,
        CollectionTagFileService $collectionTagFileService,
        FormFactory $formFactory
    )
    {
        $this->collectionElementService = $collectionElementService;
        $this->collectionTagFileService = $collectionTagFileService;
        $this->formFactory = $formFactory;
    }

    /**
     * Get an array of tags from a collection
     *
     * @param string $encodedCollectionPath Base 64 encoded collection path
     * @return Tag[]
     */
    public function list(string $encodedCollectionPath): array
    {
        return $this->collectionTagFileService->getAll($encodedCollectionPath);
    }

    /**
     * Add a tag to a collection
     *
     * @param string $encodedCollectionPath Base 64 encoded collection path
     * @param Request $request
     * @return Tag|FormInterface
     */
    public function create(string $encodedCollectionPath, Request $request)
    {
        $tag = new Tag();
        $form = $this->formFactory->create(TagType::class, $tag);
        $requestContent = $request->request->all();
        $form->submit($requestContent, false);

        if (!$form->isValid()) {
            return $form;
        }

        $this->collectionTagFileService->add($encodedCollectionPath, $tag);
        $this->collectionTagFileService->save($encodedCollectionPath);

        return $tag;
    }

    /**
     * Get a tag from a collection
     *
     * @param string $encodedCollectionPath Base 64 encoded collection path
     * @param string $encodedTagName Base 64 encoded tag name
     * @return Tag
     */
    public function get(string $encodedCollectionPath, string $encodedTagName): Tag
    {
        if (!Base64::isValidBase64($encodedTagName)) {
            throw new BadRequestHttpException('request.badly_encoded_tag_name');
        }
        $tagName = Base64::decode($encodedTagName);

        $tag = $this->collectionTagFileService->get($encodedCollectionPath, $tagName);

        return $tag;
    }

    /**
     * Update a tag from a collection
     *
     * @param string $encodedCollectionPath Base 64 encoded collection path
     * @param string $encodedTagName Base 64 encoded tag name
     * @param Request $request
     * @return Tag|FormInterface
     */
    public function update(string $encodedCollectionPath, string $encodedTagName, Request $request)
    {
        $tag = $this->get($encodedCollectionPath, $encodedTagName);
        $oldTag = clone $tag;

        $form = $this->formFactory->create(TagType::class, $tag);
        $requestContent = $request->request->all();
        $form->submit($requestContent, false);

        if (!$form->isValid()) {
            return $form;
        }

        // If tag has not changed, just return the old one
        if ($oldTag->getName() === $tag->getName()) {
            return $oldTag;
        }

        // Add the new tag (throws if tag name already exists)
        $this->collectionTagFileService->add($encodedCollectionPath, $tag);

        // Rename all elements which has this tag
        $this->collectionElementService->batchRename(
            $encodedCollectionPath,
            function (Element $element) use ($oldTag) {
                return in_array($oldTag->getName(), $element->getTags());
            },
            function (ElementFile $elementFile) use ($oldTag, $tag) {
                $elementFile
                    ->removeTag($oldTag->getName())
                    ->addTag($tag->getName());
            }
        );

        // Remove the old one and save the tag file
        $this->collectionTagFileService->remove($encodedCollectionPath, $oldTag);
        $this->collectionTagFileService->save($encodedCollectionPath);

        return $tag;
    }

    /**
     * Delete a tag from a collection
     *
     * @param string $encodedCollectionPath Base 64 encoded collection path
     * @param string $encodedTagName Base 64 encoded tag name
     */
    public function delete(string $encodedCollectionPath, string $encodedTagName)
    {
        $tag = $this->get($encodedCollectionPath, $encodedTagName);

        // Add the new tag (throws if tag name already exists)
        $this->collectionTagFileService->remove($encodedCollectionPath, $tag);

        // Rename all elements which has this tag
        $this->collectionElementService->batchRename(
            $encodedCollectionPath,
            function (Element $element) use ($tag) {
                return in_array($tag->getName(), $element->getTags());
            },
            function (ElementFile $elementFile) use ($tag) {
                $elementFile->removeTag($tag->getName());
            }
        );

        // Save the tag file
        $this->collectionTagFileService->save($encodedCollectionPath);
    }
}
