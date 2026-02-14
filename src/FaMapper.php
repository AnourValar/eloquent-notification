<?php

namespace AnourValar\EloquentNotification;

final class FaMapper
{
    /**
     * @var string
     */
    public readonly string $name;

    /**
     * @var array
     */
    public readonly array $contacts;

    /**
     * @var int
     */
    public readonly int $expiredAt;

    /**
     * Fill in
     *
     * @param string $name
     * @param array $contacts
     * @return void
     * @throws \RuntimeException
     */
    public function __construct(string $name, array $contacts)
    {
        $this->name = $name;

        $this->contacts = array_map(fn ($item) => is_string($item) ? mb_strtolower($item) : $item, array_filter($contacts));
        if (! $this->contacts) {
            throw new \RuntimeException('Incorrect usage');
        }

        $this->expiredAt = now()->addSeconds(config('eloquent_notification.confirm.fa_expire'))->timestamp;
    }

    /**
     * Encryption to send to the client
     *
     * @return string
     */
    public function encrypt(): string
    {
        return encrypt($this);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->encrypt();
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            gzcompress(json_encode(
                [$this->name, $this->contacts, $this->expiredAt],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )),
        ];
    }

    /**
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $data = json_decode(gzuncompress($data[0]), true);

        $this->name = $data[0];
        $this->contacts = $data[1];
        $this->expiredAt = $data[2];
    }
}
