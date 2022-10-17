<?php

use ParagonIE\ConstantTime\Base64;
use wcf\data\minecraft\MinecraftEditor;
use wcf\data\minecraft\MinecraftList;
use wcf\system\database\exception\DatabaseException;
use wcf\system\user\authentication\password\algorithm\Bcrypt;
use wcf\system\user\authentication\password\PasswordAlgorithmManager;

$algorithm = new Bcrypt(9);

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
        continue;
    } catch (TypeError $e) {
        continue;
    }
    [$user, $password] = \explode(':', $authEncoded, 2);
    try {
        $editor->update([
            'user' => $user,
            'password' => $algorithmName . ':' . $algorithm->hash($password)
        ]);
    } catch (DatabaseException $e) {
        \wcf\functions\exception\logThrowable($e);
    }
}
