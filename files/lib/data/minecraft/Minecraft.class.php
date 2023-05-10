<?php

namespace wcf\data\minecraft;

use DateTime;
use wcf\data\DatabaseObject;
use wcf\data\ITitledObject;
use wcf\system\user\authentication\password\PasswordAlgorithmManager;

/**
 * Minecraft Data class
 *
 * @author   xXSchrandXx
 * @license  Creative Commons Zero v1.0 Universal (http://creativecommons.org/publicdomain/zero/1.0/)
 * @package  WoltLabSuite\Core\Data\Minecraft
 *
 * @property-read int $minecraftID
 * @property-read string $title
 * @property-read string $user
 * @property-read string $password
 * @property-read int $creationDate
 */
class Minecraft extends DatabaseObject implements ITitledObject
{
    /**
     * @inheritDoc
     */
    protected static $databaseTableName = 'minecraft';

    /**
     * @inheritDoc
     */
    protected static $databaseTableIndexName = 'minecraftID';

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Check user password
     * @param string $password
     * @return bool
     */
    public function check(
        #[\SensitiveParameter]
        $password
    ): bool {
        $isValid = false;

        $manager = PasswordAlgorithmManager::getInstance();

        [$algorithmName, $hash] = \explode(':', $this->password, 2);

        $algorithm = $manager->getAlgorithmFromName($algorithmName);

        $isValid = $algorithm->verify($password, $hash);

        if (!$isValid) {
            return false;
        }

        $defaultAlgorithm = $manager->getDefaultAlgorithm();
        if (\get_class($algorithm) !== \get_class($defaultAlgorithm) || $algorithm->needsRehash($hash)) {
            $minecraftEditor = new MinecraftEditor($this);
            $algorithmName = PasswordAlgorithmManager::getInstance()->getNameFromAlgorithm($algorithm);
            $minecraftEditor->update([
                'password' => $algorithmName . ':' . $algorithm->hash($password)
            ]);
        }

        // $isValid is always true at this point. However we intentionally use a variable
        // that defaults to false to prevent accidents during refactoring.
        \assert($isValid);

        return $isValid;
    }

    /**
     * Returns user
     * @return ?string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Returns createdTimestamp
     * @return ?int
     */
    public function getCreatedTimestamp()
    {
        return $this->creationDate;
    }

    /**
     * Returns date
     * @return ?DateTime
     */
    public function getCreatdDate()
    {
        return new DateTime($this->getCreatedTimestamp());
    }
}
