<?php

namespace AnourValar\EloquentNotification\Observers;

use AnourValar\EloquentNotification\UserNotification;

class UserNotificationObserver
{
    /**
     * Handle the "saving" event.
     *
     * @param  \AnourValar\EloquentNotification\UserNotification  $model
     * @return mixed
     */
    public function saving(UserNotification $model)
    {
        if (! $model->channels) {
            if ($model->exists) {
                $model->delete();
            }

            return false;
        }
    }

    /**
     * Handle the "creating" event.
     *
     * @param  \AnourValar\EloquentNotification\UserNotification  $model
     * @return mixed
     */
    public function creating(UserNotification $model)
    {

    }

    /**
     * Handle the "updating" event.
     *
     * @param  \AnourValar\EloquentNotification\UserNotification  $model
     * @return mixed
     */
    public function updating(UserNotification $model)
    {

    }

    /**
     * Handle the "created" event.
     *
     * @param  \AnourValar\EloquentNotification\UserNotification  $model
     * @return void
     */
    public function created(UserNotification $model)
    {

    }

    /**
     * Handle the "updated" event.
     *
     * @param  \AnourValar\EloquentNotification\UserNotification  $model
     * @return void
     */
    public function updated(UserNotification $model)
    {

    }

    /**
     * Handle the "saved" event.
     *
     * @param  \AnourValar\EloquentNotification\UserNotification  $model
     * @return void
     */
    public function saved(UserNotification $model)
    {

    }

    /**
     * Handle the "deleting" event.
     *
     * @param  \AnourValar\EloquentNotification\UserNotification  $model
     * @return mixed
     */
    public function deleting(UserNotification $model)
    {

    }

    /**
     * Handle the "deleted" event.
     *
     * @param  \AnourValar\EloquentNotification\UserNotification  $model
     * @return void
     */
    public function deleted(UserNotification $model)
    {

    }
}
