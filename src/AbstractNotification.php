<?php

namespace AnourValar\EloquentNotification;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

abstract class AbstractNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
    * Create a new notification instance.
    */
    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * Prevent duplicate notifications for a period of time (seconds)
     *
     * @return int
     */
    protected function preventDuplicates(): int
    {
        return 0; // disabled
    }

    /**
     * Cache settings for a period of time (seconds)
     *
     * @return int
     */
    protected function cacheChannels(): int
    {
        return 2 * 60; // 2 minutes
    }

    /**
     * Subject's ID to fetch settings
     *
     * @param mixed $notifiable
     * @return int
     */
    protected function notifiableIdForSettings($notifiable): int
    {
        return $notifiable->id;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array<int, string>
     * @throws \RuntimeException
     */
    public function via($notifiable)
    {
        // Locale
        if (! $notifiable instanceof \Illuminate\Contracts\Translation\HasLocalePreference) {
            $this->locale = config('app.locale');
        }

        // Deleted?
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses($notifiable->getModel()))) {
            $column = $notifiable->getDeletedAtColumn();
            if ($notifiable->$column) {
                return [];
            }
        }

        // Prevent duplicates?
        $keys = [__METHOD__, get_class($notifiable), $notifiable->id, get_class($this)];
        foreach ((new \ReflectionClass($this))->getConstructor()->getParameters() as $param) {
            $name = $param->getName();
            $keys[] = $this->paramToKey($this->$name);
        }

        // Send notification
        $lockFor = $this->preventDuplicates();
        $notifiableId = $this->notifiableIdForSettings($notifiable);
        if (! $lockFor || \Cache::lock(implode(' / ', $keys), $lockFor)->get()) {
            $notifications = \Cache::memo()->remember(
                implode(' / ', [__METHOD__, get_class($notifiable), $notifiableId]),
                $this->cacheChannels(),
                function () use ($notifiableId) {
                    $class = config('eloquent_notification.model');
                    return $class::where('user_id', '=', $notifiableId)->get(['trigger', 'channels'])->pluck('channels', 'trigger')->toArray();
                }
            );

            return $notifications[$this->getTrigger()] ?? [];
        };

        return [];
    }

    /**
     * Serialize models as objects
     *
     * @see \Illuminate\Queue\SerializesAndRestoresModelIdentifiers::getSerializedPropertyValue()
     */
    #[\Override]
    protected function getSerializedPropertyValue($value, $withRelations = true)
    {
        return $value;
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(object $notifiable): string
    {
        return $this->getTrigger();
    }

    /**
     * @return string
     * @throws \RuntimeException
     */
    private function getTrigger(): string
    {
        foreach (config('eloquent_notification.trigger') as $trigger => $details) {
            if (get_class($this) === $details['bind']) {
                return $trigger;
            }
        }

        throw new \RuntimeException('Trigger not found for found for: ' . get_class($this));
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function paramToKey($value)
    {
        if ($value instanceof \Illuminate\Database\Eloquent\Model) {

            return $value->getKey();

        } elseif (is_iterable($value)) {

            $subKeys = [];
            foreach ($value as $item) {
                $subKeys[] = $this->paramToKey($item);
            }
            return json_encode($subKeys);

        } else {

            return $value;

        }
    }

    /**
     * Helper
     *
     * @param string ...$markdown
     * @return \Illuminate\Support\HtmlString
     */
    protected function markdown(string ...$markdown): \Illuminate\Support\HtmlString
    {
        return new HtmlString(\Str::inlineMarkdown(implode("\n", $markdown))); // \Illuminate\Mail\Markdown
    }
}
