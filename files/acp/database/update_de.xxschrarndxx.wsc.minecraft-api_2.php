<?php

use ParagonIE\ConstantTime\Base64;
use wcf\data\minecraft\MinecraftEditor;
use wcf\data\minecraft\MinecraftList;
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
    $authArr = \explode($authEncoded, 2);
    if (!$authArr) {
        continue;
    }
    $savedUser = $authArr[0];
    $savedPassword = $authArr[1];
    $editor->update([
        'user' => $savedUser,
        'password' => $algorithmName . ':' . $algorithm->hash($savedPassword)
    ]);
}
