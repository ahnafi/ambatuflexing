<?php

namespace JackBerck\Ambatuflexing\Model;

class FindPost
{
    public ?int $id = null;
    public ?string $title = null;
    public ?string $content = null;
    public ?string $category = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    public ?int $authorId = null;
    public ?string $author = null;
    public ?string $authorPosition = null;
    public ?string $authorPhoto = null;
    public ?string $banner = null;
    public int $commentCount = 0;
    public int $likeCount = 0;
}