<?php

namespace App\Entity\Chat;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\Chat\MessageAttachmentRepository;

#[ORM\Entity(repositoryClass: MessageAttachmentRepository::class)]
#[ORM\Table(name: 'message_attachments')]
#[ORM\Index(name: "index_attachment_message", columns: ["message_id"])]
class MessageAttachment
{
    public const MAX_FILE_SIZE_BYTES = 31457280;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $message_id = null;

    public function getMessage_id(): ?int
    {
        return $this->message_id;
    }

    public function setMessage_id(int $message_id): self
    {
        $this->message_id = $message_id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $file_name = null;

    public function getFile_name(): ?string
    {
        return $this->file_name;
    }

    public function setFile_name(string $file_name): self
    {
        $this->setFileName($file_name);
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $mime_type = null;

    public function getMime_type(): ?string
    {
        return $this->mime_type;
    }

    public function setMime_type(string $mime_type): self
    {
        $this->setMimeType($mime_type);
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $size_bytes = null;

    public function getSize_bytes(): ?int
    {
        return $this->size_bytes;
    }

    public function setSize_bytes(int $size_bytes): self
    {
        $this->setSizeBytes($size_bytes);
        return $this;
    }

    #[ORM\Column(type: 'blob', columnDefinition: 'LONGBLOB', nullable: false)]
    private mixed $data = null;

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setData(mixed $data): self
    {
        if (is_resource($data)) {
            $this->data = $data;
            return $this;
        }

        if (!is_string($data) || $data === '') {
            throw new \InvalidArgumentException('File content is empty.');
        }

        $this->data = $data;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(?\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getMessageId(): ?int
    {
        return $this->message_id;
    }

    public function setMessageId(int $message_id): static
    {
        $this->message_id = $message_id;

        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->file_name;
    }

    public function setFileName(string $file_name): static
    {
        $normalized = trim($file_name);
        if ($normalized === '') {
            throw new \InvalidArgumentException('File name cannot be empty.');
        }

        if (mb_strlen($normalized) > 255) {
            throw new \InvalidArgumentException('File name is too long.');
        }

        $this->file_name = $normalized;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mime_type;
    }

    public function setMimeType(string $mime_type): static
    {
        $normalized = trim($mime_type);
        if ($normalized === '') {
            throw new \InvalidArgumentException('Invalid file type.');
        }

        $this->mime_type = $normalized;

        return $this;
    }

    public function getSizeBytes(): ?int
    {
        return $this->size_bytes;
    }

    public function setSizeBytes(int $size_bytes): static
    {
        if ($size_bytes <= 0) {
            throw new \InvalidArgumentException('Invalid file size.');
        }

        if ($size_bytes > self::MAX_FILE_SIZE_BYTES) {
            throw new \InvalidArgumentException('File is too big. Maximum size is 30 MB.');
        }

        $this->size_bytes = $size_bytes;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTime $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

}
