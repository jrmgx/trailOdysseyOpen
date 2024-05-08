<?php

namespace App\Security\Voter;

use App\Entity\Bag;
use App\Entity\DiaryEntry;
use App\Entity\Gear;
use App\Entity\GearInBag;
use App\Entity\Interest;
use App\Entity\Routing;
use App\Entity\Segment;
use App\Entity\Stage;
use App\Entity\Trip;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends Voter<string, Trip|Stage|Routing>
 */
class UserVoter extends Voter
{
    public const EDIT = 'EDIT';
    public const VIEW = 'VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::VIEW], true) && (
            null === $subject
            || $subject instanceof Trip
            || $subject instanceof Stage
            || $subject instanceof Routing
            || $subject instanceof Interest
            || $subject instanceof Segment
            || $subject instanceof Gear
            || $subject instanceof Bag
            || $subject instanceof GearInBag
            || $subject instanceof DiaryEntry
        );
    }

    /**
     * @param Trip|Stage|Routing|Interest|Segment|Gear|Bag|GearInBag|DiaryEntry|null $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (null === $subject) {
            return true;
        }

        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }

        switch ($attribute) {
            case self::VIEW:
            case self::EDIT:
                return $subject->getUser() === $user;
        }

        return false;
    }
}
