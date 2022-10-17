<?php

use ParagonIE\ConstantTime\Base64;
use wcf\data\minecraft\MinecraftEditor;
use wcf\data\minecraft\MinecraftList;
use wcf\system\database\exception\DatabaseException;
use wcf\system\user\authentication\password\PasswordAlgorithmManager;

$manager = PasswordAlgorithmManager::getInstance();
$algorithm = $manager->getDefaultAlgorithm();

$minecraftList = new MinecraftList();
$minecraftList->readObjects();
/** @var \wcf\data\minecraft\Minecraft */
$minecrafts = $minecraftList->getObjects();

foreach ($minecrafts as $minecraft) {
    $editor = new MinecraftEditor($minecraft);
    $algorithmName = PasswordAlgorithmManager::getInstance()->getNameFromAlgorithm($algorithm);
    try {
        $authEncoded = Base64::decode($minecraft->auth);
    } catch (RangeException $e) {
        // Should never happen
        \wcf\functions\exception\logThrowable($e);
        continue;
    } catch (TypeError $e) {
        // Should never happen
        \wcf\functions\exception\logThrowable($e);
        continue;
    }
    [$user, $password] = \explode(':', $authEncoded, 2);
    $this->updateData($editor, $user, $password, $algorithmName, $algorithm);
}

function updateData($editor, $user, $password, $algorithmName, $algorithm, $i = 0)
{
    try {
        $editor->update([
            'user' => $user,
            'password' => $algorithmName . ':' . $algorithm->hash($password)
        ]);
    } catch (DatabaseException $e) {
        $i = $i++;
        updateData($editor, $user . $i, $password, $algorithmName, $algorithm, $i);
        \wcf\functions\exception\logThrowable($e);
    }
}
