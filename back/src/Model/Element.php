<?php

declare(strict_types=1);

namespace App\Model;

use App\Exception\NotSupportedElementTypeException;
use App\Util\Base64;
use DateTime;
use Swagger\Annotations as SWG;

abstract class Element
{
    const IMAGE_TYPE = 'image';
    const NOTE_TYPE = 'note';
    const LINK_TYPE = 'link';
    const COLORS_TYPE = 'colors';

    const EXTENSIONS_BY_TYPE = [
        self::IMAGE_TYPE => ['jpg', 'jpeg', 'png', 'gif'],
        self::NOTE_TYPE => ['txt', 'md'],
        self::LINK_TYPE => ['link'],
        self::COLORS_TYPE => ['colors'],
    ];

    /**
     * @var string
     *
     * @SWG\Property(type="string")
     */
    private $type;

    /**
     * @var string
     *
     * @SWG\Property(type="string")
     */
    private $name;

    /**
     * @var array
     *
     * @SWG\Property(
     *     type="array",
     *     @SWG\Items(type="string")
     * )
     */
    private $tags;

    /**
     * @var DateTime
     *
     * @SWG\Property(type="string", format="date-time")
     */
    private $updated;

    /**
     * @var int
     *
     * @SWG\Property(type="integer")
     */
    private $size;

    /**
     * @var string
     *
     * @SWG\Property(type="string")
     */
    private $extension;

    /**
     * @var string
     *
     * @SWG\Property(type="string")
     */
    private $encodedColllectionPath;

    /**
     * @var string
     *
     * @SWG\Property(type="string")
     */
    private $encodedElementBasename;

    /**
     * @var string
     *
     * @SWG\Property(type="string")
     */
    private $proxyUrl;

    /**
     * Element constructor.
     *
     * @param string[] $meta
     *
     * @throws NotSupportedElementTypeException
     */
    public function __construct(array $meta, string $encodedColllectionPath)
    {
        $basename = pathinfo($meta['path'])['basename'];
        $elementMeta = self::parseBasename($basename);

        $updated = new DateTime();
        $updated->setTimestamp($meta['timestamp']);

        $this->type = $elementMeta['type'];
        $this->name = $elementMeta['name'];
        $this->tags = $elementMeta['tags'];
        $this->updated = $updated;
        $this->size = $meta['size'];
        $this->extension = $elementMeta['extension'];
        $this->encodedColllectionPath = $encodedColllectionPath;
        $this->encodedElementBasename = Base64::encode($basename);
    }

    /**
     * Determinate if this type of element should have his content loaded in response object.
     */
    abstract public static function shouldLoadContent(): bool;

    /**
     * Get content of the file from the element object
     * It can be handled differently by each element typed class.
     */
    abstract public function getContent(): ?string;

    /**
     * Set content of the file in the element object
     * It can be handled differently by each element typed class.
     */
    abstract public function setContent(string $content): void;

    /**
     * Get element type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get element name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set element name.
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Get element tags.
     *
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Get element last updated date.
     */
    public function getUpdated(): DateTime
    {
        return $this->updated;
    }

    /**
     * Get element size.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get element extension.
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * Get element's Colllection encoded path.
     */
    public function getEncodedColllectionPath(): string
    {
        return $this->encodedColllectionPath;
    }

    /**
     * Set element's Colllection encoded path.
     */
    public function setEncodedColllectionPath(string $encodedColllectionPath): void
    {
        $this->encodedColllectionPath = $encodedColllectionPath;
    }

    public function getEncodedElementBasename(): string
    {
        return $this->encodedElementBasename;
    }

    public function setEncodedElementBasename(string $encodedElementBasename): void
    {
        $this->encodedElementBasename = $encodedElementBasename;
    }

    public function getProxyUrl(): string
    {
        return '/proxy/' . $this->encodedColllectionPath . '/' . $this->encodedElementBasename;
    }

    /**
     * @throws NotSupportedElementTypeException
     */
    public static function getTypeByPath(string $elementFilePath): string
    {
        $pathInfos = pathinfo($elementFilePath);
        if (isset($pathInfos['extension'])) {
            foreach (self::EXTENSIONS_BY_TYPE as $type => $extensions) {
                if (\in_array($pathInfos['extension'], $extensions, true)) {
                    return $type;
                }
            }
        }

        throw new NotSupportedElementTypeException();
    }

    /**
     * Return typed element based on flysystem metadata.
     *
     * @param string[] $elementMetadata
     *
     * @return Color|Image|Link|Note
     *
     * @throws NotSupportedElementTypeException
     */
    public static function get(array $elementMetadata, string $encodedColllectionPath): self
    {
        $type = self::getTypeByPath($elementMetadata['path']);
        switch ($type) {
            case self::IMAGE_TYPE:
                return new Image($elementMetadata, $encodedColllectionPath);
                break;
        }

        throw new NotSupportedElementTypeException();
    }

    /**
     * Parse basename to get type, name, tags and extension.
     *
     * @throws NotSupportedElementTypeException
     */
    public static function parseBasename(string $basename): array
    {
        $meta = [];

        // Can throw an NotSupportedElementTypeException
        $meta['type'] = self::getTypeByPath($basename);

        $pathParts = pathinfo($basename);
        $filename = $pathParts['filename'];

        // Parse tags from filename
        preg_match_all('/#([^\s.,\/#!$%\^&\*;:{}=\-`~()]+)/', $filename, $tags);
        $tags = $tags[1];
        foreach ($tags as $k => $tag) {
            // Remove tags from filename
            $filename = str_replace("#$tag", '', $filename);
            // Replace underscores by spaces in tags
            $tags[$k] = str_replace('_', ' ', $tag);
        }
        sort($tags);

        // Replace multiple spaces by single space
        $name = preg_replace('/\s+/', ' ', trim($filename));

        $meta['name'] = $name;
        $meta['tags'] = $tags;
        $meta['extension'] = $pathParts['extension'];

        return $meta;
    }
}