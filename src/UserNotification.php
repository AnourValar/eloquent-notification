<?php

namespace AnourValar\EloquentNotification;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class UserNotification extends Model
{
    use \AnourValar\EloquentValidation\ModelTrait;
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Trim columns
     *
     * @var array
     */
    protected $trim = [
        'trigger', 'channels',
    ];

    /**
     * '',[] => null convert
     *
     * @var array
     */
    protected $nullable = [
        'channels',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'updated_at',
    ];

    /**
     * The model's attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [

    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'trigger' => 'string',
        'channels' => 'json:unicode',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Mutators for nested JSON.
     * jsonb - sort an array by key
     * nullable - '',[] => null convert (nested)
     * purges - remove null elements (nested)
     * types - set the type of value (nested)
     * sorts - sort an array (nested)
     * lists - drop array keys (nested)
     *
     * @var array
     */
    protected $jsonNested = [
        'channels' => [
            'jsonb' => true,
            'nullable' => ['*'],
            'purges' => ['*'],
            'types' => ['$.*' => 'string'],
            'sorts' => ['*'],
            'lists' => ['*'],
        ],
    ];

    /**
     * Calculated columns
     *
     * @var array
     */
    protected $computed = [

    ];

    /**
     * Immutable columns
     *
     * @var array
     */
    protected $unchangeable = [
        'user_id',
    ];

    /**
     * Unique columns sets
     *
     * @var array
     */
    protected $unique = [
        ['user_id', 'trigger'],
    ];

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::observe(\AnourValar\EloquentNotification\Observers\UserNotificationObserver::class);
    }

    /**
     * @see \AnourValar\EloquentValidation\ModelTrait::getAttributeNamesFromModelLang()
     *
     * @return array
     */
    protected function getAttributeNamesFromModelLang(): array
    {
        $attributeNames = trans('notification::user_notification.attributes');

        return is_array($attributeNames) ? $attributeNames : [];
    }

    /**
     * @var string
     */
    protected static $factory = \AnourValar\EloquentNotification\resources\database\factories\UserNotificationFactory::class;

    /**
     * Get the validation rules
     *
     * @return array
     */
    public function saveRules()
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'trigger' => ['required', 'config:notification.trigger'],
            'channels' => ['nullable', 'list', 'min:1', 'max:10'], // required in a fact
                'channels.*' => ['required', 'string', 'distinct'],
        ];
    }

    /**
     * "Save" after-validation
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param bool $basic
     * @return void
     */
    public function saveAfterValidation(\Illuminate\Validation\Validator $validator, bool $basic): void
    {
        // user_id
        if ($this->isDirty('user_id') && ! $basic) {
            $class = config('auth.providers.users.model');
            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses($class))) {
                $user = $class::withTrashed()->find($this->user_id);
            } else {
                $user = $class::find($this->user_id);
            }

            if (! $user) {
                $validator->errors()->add('user_id', trans('notification::user_notification.user_id_not_exists'));
            }
        }

        // channels
        if ($this->channels && array_diff($this->channels, config("notification.trigger.{$this->trigger}.channels"))) {
            $validator->errors()->add('user_id', trans('notification::user_notification.channels_not_exists'));
        }
    }

    /**
     * "Delete" after-validation
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param bool $basic
     * @return void
     */
    public function deleteAfterValidation(\Illuminate\Validation\Validator $validator, bool $basic): void
    {

    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        $class = config('auth.providers.users.model');

        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses($class))) {
            return $this->belongsTo($class)->withTrashed();
        }

        return $this->belongsTo($class);
    }

    /**
     * Light columns
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    public function scopeLight(\Illuminate\Database\Eloquent\Builder $builder): void
    {
        $builder
            ->select([
                'id', 'user_id', 'trigger', 'channels', 'created_at',
            ]);
    }
}
