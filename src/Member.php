<?php namespace NZTim\Mailchimp;

class Member
{
    /** @var string */
    private $subscriber_hash;

    private $parameters;

    public function __construct(string $email)
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address");
        }
        $this->subscriber_hash = md5($email);
        $this->parameters['email_address'] = $email;
        $this->parameters['status_if_new'] = 'pending'; // Double-opt-in is default
    }

    public function email_type(string $type): Member
    {
        if (!in_array($type, ['html', 'text'])) {
            throw new \InvalidArgumentException('Type must be html or text');
        }
        $this->parameters['email_type'] = $type;
        return $this;
    }

    // Note this doesn't affect status_if_new, therefore it's not possible to add a new member as unsubscribed or cleaned
    public function status(string $status): Member
    {
        if (!in_array($status, ['subscribed', 'unsubscribed', 'cleaned', 'pending'])) {
            throw new \InvalidArgumentException('Status must be subscribed, unsubscribed, cleaned or pending');
        }
        $this->parameters['status'] = $status;
        return $this;
    }

    public function confirm(bool $confirm): Member
    {
        $this->parameters['status'] = $confirm ? 'pending' : 'subscribed';
        $this->parameters['status_if_new'] = $confirm ? 'pending' : 'subscribed';
        return $this;
    }

    public function merge_fields(array $merge): Member
    {
        $this->parameters['merge_fields'] = $merge;
        return $this;
    }

    public function interests(array $interests): Member
    {
        $this->parameters['interests'] = $interests;
        return $this;
    }

    public function language(string $language): Member
    {
        if (!in_array($language, $this->valid_languages)) {
            throw new \InvalidArgumentException('Invalid language code, see https://kb.mailchimp.com/lists/manage-contacts/view-and-edit-subscriber-languages');
        }
        $this->parameters['language'] = $language;
        return $this;
    }

    public function vip(bool $vip): Member
    {
        $this->parameters['vip'] = $vip;
        return $this;
    }

    public function location(float $latitude, float $longitude): Member
    {
        $this->parameters['location']['latitude'] = $latitude;
        $this->parameters['location']['longitude'] = $longitude;
        return $this;
    }

    // Appears as though these parameters cannot be set via the API,
    // do they only relate to Mailchimp's own forms?
//    public function ip_signup(string $ip): Member
//    {
//        $this->parameters['ip_signup'] = $ip;
//        return $this;
//    }
//
//    public function timestamp_signup(string $timestamp): Member
//    {
//        $this->parameters['timestamp_signup'] = $timestamp;
//        return $this;
//    }
//
//    public function ip_opt(string $ip): Member
//    {
//        $this->parameters['ip_opt'] = $ip;
//        return $this;
//    }
//
//    public function timestamp_opt(string $timestamp): Member
//    {
//        $this->parameters['timestamp_opt'] = $timestamp;
//        return $this;
//    }

    public function hash(): string
    {
        return $this->subscriber_hash;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    protected $valid_languages = [
        "en",
        "ar",
        "af",
        "be",
        "bg",
        "ca",
        "zh",
        "hr",
        "cs",
        "da",
        "nl",
        "et",
        "fa",
        "fi",
        "fr",
        "fr_CA",
        "de",
        "el",
        "he",
        "hi",
        "hu",
        "is",
        "id",
        "ga",
        "it",
        "ja",
        "km",
        "ko",
        "lv",
        "lt",
        "mt",
        "ms",
        "mk",
        "no",
        "pl",
        "pt",
        "pt_PT",
        "ro",
        "ru",
        "sr",
        "sk",
        "sl",
        "es",
        "es_ES",
        "sw",
        "sv",
        "ta",
        "th",
        "tr",
        "uk",
        "v",
    ];
}
