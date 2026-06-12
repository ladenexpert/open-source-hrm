<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\Topic;

class TopicPolicy extends BasePolicy
{
    public function viewAny(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function view(Employee $user, Topic $topic): bool
    {
        return $this->isParticipant($user, $topic);
    }

    public function create(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    public function update(Employee $user, Topic $topic): bool
    {
        return $this->isParticipant($user, $topic);
    }

    public function delete(Employee $user, Topic $topic): bool
    {
        return $this->isParticipant($user, $topic);
    }

    public function deleteAny(Employee $user): bool
    {
        return $this->isActiveUser($user);
    }

    protected function isParticipant(Employee $user, Topic $topic): bool
    {
        return $topic->creator_id === $user->id || $topic->receiver_id === $user->id;
    }
}
