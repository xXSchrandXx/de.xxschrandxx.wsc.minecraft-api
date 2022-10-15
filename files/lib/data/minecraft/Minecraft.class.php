<?php

namespace wcf\data\minecraft;

use DateTime;
use wcf\data\DatabaseObject;
use wcf\system\user\authentication\password\algorithm\DoubleBcrypt;
use wcf\system\user\authentication\password\PasswordAlgorithmManager;

/**
 * Minecraft Data class
 *
 * @author   xXSchrandXx
 * @license  Creative Commons Zero v1.0 Universal (http://creativecommons.org/publicdomain/zero/1.0/)
 * @package  WoltLabSuite\Core\Data\Minecraft
 */
class Minecraft extends DatabaseObject
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
     * Returns title
     * @return ?string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Check user password
     * @return bool
     */
    public function check(string $password)
    {
        $isValid = false;

        $manager = PasswordAlgorithmManager::getInstance();

        // Compatibility for WoltLab Suite < 5.4.
        if (DoubleBcrypt::isLegacyDoubleBcrypt($this->password)) {
            $algorithmName = 'DoubleBcrypt';
            $hash = $this->password;
        } else {
            [$algorithmName, $hash] = \explode(':', $this->password, 2);
        }

        $algorithm = $manager->getAlgorithmFromName($algorithmName);

        $isValid = $algorithm->verify($password, $hash);

        if (!$isValid) {
            return false;
        }

        $defaultAlgorithm = $manager->getDefaultAlgorithm();
        if (\get_class($algorithm) !== \get_class($defaultAlgorithm) || $algorithm->needsRehash($hash)) {
            $minecraftEditor = new MinecraftEditor($this);
            $minecraftEditor->update([
                'password' => $password,
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
