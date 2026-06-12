<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Message;

class MessagePolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function view(Employee $user, Message $message): bool
    {
        return $this->isMessageParticipant($user, $message);
    }

    public function create(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function update(Employee $user, Message $message): bool
    {
        return (int) $message->sender_id === (int) $user->id;
    }

    public function delete(Employee $user, Message $message): bool
    {
        return (int) $message->sender_id === (int) $user->id;
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    protected function isMessageParticipant(Employee $user, Message $message): bool
    {
        if ($message->sender_id === $user->id || $message->receiver_id === $user->id) {
            return true;
        }

        $topic = $message->relationLoaded('topic') ? $message->getRelation('topic') : $message->topic;

        if (! $topic) {
            return false;
        }

        return $topic->creator_id === $user->id || $topic->receiver_id === $user->id;
    }
}
