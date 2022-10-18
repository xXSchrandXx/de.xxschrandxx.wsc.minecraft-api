<?php

use ParagonIE\ConstantTime\Base64;
use wcf\data\minecraft\MinecraftEditor;
use wcf\data\minecraft\MinecraftList;
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
    $authEncoded = Base64::decode($minecraft->auth);
    [$user, $password] = \explode(':', $authEncoded, 2);
    $editor->update([
        'user' => $user,
        'password' => $algorithmName . ':' . $algorithm->hash($password)
    ]);
}
