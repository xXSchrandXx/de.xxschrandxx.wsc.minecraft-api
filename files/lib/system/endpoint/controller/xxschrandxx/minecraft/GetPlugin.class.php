<?php

namespace wcf\system\endpoint\controller\xxschrandxx\minecraft;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use wcf\data\package\Package;
use wcf\data\package\PackageList;
use wcf\system\endpoint\GetRequest;
use wcf\system\endpoint\IController;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\WCF;

#[GetRequest('/xxschrandxx/minecraft/plugin')]
final class GetPlugin implements IController
{
    #[\Override]
    public function __invoke(ServerRequestInterface $request, array $variables): ResponseInterface
    {
        if (!ENABLE_DEBUG_MODE) {
            throw new PermissionDeniedException();
        }

        $packageList = new PackageList();
        $packageList->getConditionBuilder()->add("package = 'de.xxschrarndxx.wsc.minecraft-api'");
        $packageList->readObjects();
        $apiPackage = $packageList->getSingleObject();

        $extensions = [];
        $extensionPackages = $this->getExtensionPackages($apiPackage);
        foreach ($extensionPackages as $extensionPackage) {
            $extensions[] = $extensionPackage->getTitle();
        }

        return new JsonResponse([
            'version' => $apiPackage->packageVersion,
            'extensions' => $extensions,
            'php' => PHP_VERSION_ID,
            'wsc' => WCF_VERSION
        ]);
    }

    public function getExtensionPackages(Package $apiPackage, array &$dependentPackageIDs = []): array
    {
        foreach ($apiPackage->getDependentPackages() as $dependentPackageID => $dependentPackage) {
            if (!array_key_exists($dependentPackageID, $dependentPackageIDs)) {
                $dependentPackageIDs[$dependentPackageID] = $dependentPackage;
            }
            $this->getExtensionPackages($dependentPackage, $dependentPackageIDs);
        }
        return $dependentPackageIDs;
    }
}
