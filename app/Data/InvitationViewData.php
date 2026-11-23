<?php

namespace App\Data;

use App\Enums\CoupleOrder;

final readonly class InvitationViewData
{
    /**
     * @param  array<string, bool>  $features
     * @param  array<string, string|null>  $theme
     * @param  array<string, string>  $endpoints
     * @param  array<int, EventViewData>  $events
     * @param  array<int, StoryViewData>  $stories
     * @param  array<int, GalleryViewData>  $galleries
     * @param  array<int, string>  $supportingPhotos
     * @param  array<int, GiftAccountViewData>  $giftAccounts
     * @param  string|null  $giftAddress
     * @param  array<int, string>  $families
     * @param  array<int, GreetingViewData>  $greetings
     * @param  array{title:string,message:string,expired_message:string,activation_url:string,dashboard_url:string,price:string,remaining_seconds:int,remaining_label:string,expired:bool}|null  $activationPrompt
     */
    public function __construct(
        public string $key,
        public string $templateName,
        public string $templateVersion,
        public string $title,
        public PersonViewData $bride,
        public PersonViewData $groom,
        public array $events,
        public array $stories,
        public array $galleries,
        public array $supportingPhotos,
        public array $giftAccounts,
        public ?string $giftAddress,
        public array $families,
        public array $greetings,
        public array $features,
        public array $theme,
        public ?string $musicUrl,
        public bool $musicAutoplay,
        public string $guestName,
        public string $saveTheDate,
        public ?string $countdownTarget,
        public string $invitationMessage,
        public string $quote,
        public string $quoteSource,
        public array $endpoints,
        public ?string $csrfToken,
        public bool $sample,
        public bool $preview,
        public ?string $publicStatus = null,
        public ?array $activationPrompt = null,
        /** @var array<string, array<int, string>> */
        public array $backgrounds = [],
        /** @var array<string, array<int, string>> */
        public array $photoGallery = [],
        /** @var array{number:string,display_name:string,message:string,url:string}|null */
        public ?array $whatsapp = null,
        public ?string $shareTitle = null,
        public ?string $shareDescription = null,
        public ?string $shareImage = null,
        public ?CoupleOrder $coupleOrder = null,
    ) {
    }

    /**
     * One decision for the whole invitation. A null setting keeps the order the
     * templates used before couple_order existed (bride first).
     */
    public function coupleOrder(): CoupleOrder
    {
        return $this->coupleOrder ?? CoupleOrder::FemaleFirst;
    }

    public function hasExplicitCoupleOrder(): bool
    {
        return $this->coupleOrder !== null;
    }

    /** @return array{0: PersonViewData, 1: PersonViewData} */
    public function orderedPeople(): array
    {
        return $this->coupleOrder()->isMaleFirst()
            ? [$this->groom, $this->bride]
            : [$this->bride, $this->groom];
    }

    public function firstPerson(): PersonViewData
    {
        return $this->orderedPeople()[0];
    }

    public function secondPerson(): PersonViewData
    {
        return $this->orderedPeople()[1];
    }

    public function coupleLabel(): string
    {
        $names = array_values(array_filter(array_map(
            static fn (PersonViewData $person): string => trim($person->displayName()),
            $this->orderedPeople(),
        ), fn (string $name): bool => $name !== ''));

        return implode(' & ', $names);
    }

    public function primaryEvent(): ?EventViewData
    {
        return $this->events[0] ?? null;
    }

    public function esc(?string $value): string
    {
        return e($value ?? '');
    }
}
