<?php

namespace App\Data;

final readonly class PersonViewData
{
    /**
     * @param  list<array{label:string,url:string}>  $socialLinks
     */
    public function __construct(
        public string $fullName,
        public string $nickname,
        public ?string $photo,
        public string $fatherName,
        public string $motherName,
        public ?string $instagram,
        public string $parentsLabel,
        public array $socialLinks = [],
    ) {
    }

    public function displayName(): string
    {
        return $this->nickname !== '' ? $this->nickname : $this->fullName;
    }

    public function parentLine(): string
    {
        $parents = trim($this->fatherName . ' & ' . $this->motherName, ' &');

        if ($parents === '') {
            return '';
        }

        return $this->parentsLabel . ': ' . $parents;
    }
}
