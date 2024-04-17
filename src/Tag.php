<?php declare(strict_types=1);

namespace NZTim\Mailchimp;

class Tag
{
    public int $id;
    public string $name;
    public string $dateAdded;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->dateAdded = $data['date_added'];
    }
}
