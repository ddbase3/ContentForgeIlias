<?php declare(strict_types=1);

namespace ContentForgeIlias\ExportTarget;

use ContentForge\Api\IContentForgeExportTarget;
use ContentForge\Model\ContentForgeDeliveredExport;
use ContentForge\Model\ContentForgeExportRequest;
use ContentForge\Model\ContentForgeExportResult;
use ContentForge\Model\ContentForgeProject;
use ContentForge\Model\ContentForgeWorkflowContext;

/**
 * Places a generated ContentForge SCORM 1.2 export as a native ILIAS SCORM object.
 *
 * The implementation mirrors the essential creation path used by
 * ilObjSAHSLearningModuleGUI::uploadObject(): create object, insert it into the
 * repository tree, copy SCORM package files into the module data directory,
 * parse the manifest through readObject() and initialize learning progress.
 */
class ContentForgeIliasScormExportTarget implements IContentForgeExportTarget {

	public static function getName(): string {
		return 'contentforgeiliasscormexporttarget';
	}

	public function deliver(ContentForgeExportResult $result, ContentForgeWorkflowContext $context, ContentForgeExportRequest $request): ContentForgeDeliveredExport {
		$this->loadIliasScormClasses();

		$targetConfig = $this->getTargetConfig($request);
		$parentRefId = (int) ($targetConfig['parent_ref_id'] ?? 0);

		if ($parentRefId <= 0) {
			throw new \InvalidArgumentException('ContentForge ILIAS SCORM export target requires targetConfig.parent_ref_id.');
		}

		if ($result->type !== 'scorm12_package') {
			throw new \InvalidArgumentException('ContentForge ILIAS SCORM export target only accepts scorm12_package results.');
		}

		if (!isset($result->files['imsmanifest.xml'])) {
			throw new \InvalidArgumentException('ContentForge ILIAS SCORM export target requires imsmanifest.xml in the export result.');
		}

		$object = $this->createScormObject($result, $targetConfig, $parentRefId);
		$refId = method_exists($object, 'getRefId') ? (int) $object->getRefId() : 0;
		$objId = method_exists($object, 'getId') ? (int) $object->getId() : 0;
		$objectUrl = $this->getObjectUrl($refId);
		$returnUrl = $this->getRedirectUrl($targetConfig, $refId, $parentRefId);

		return new ContentForgeDeliveredExport(
			ContentForgeProject::newId('delivered'),
			$result->type,
			(string) $objId,
			$returnUrl,
			[
				'target' => self::getName(),
				'ilias_ref_id' => $refId,
				'ilias_obj_id' => $objId,
				'parent_ref_id' => $parentRefId,
				'scorm_sub_type' => 'scorm',
				'fileCount' => count($result->files),
				'object_url' => $objectUrl,
				'file_url' => $objectUrl,
				'return_url' => $returnUrl,
				'redirect_url' => $returnUrl,
				'contentForgeAutoRedirect' => true,
				'autoRedirect' => true
			]
		);
	}

	protected function createScormObject(ContentForgeExportResult $result, array $targetConfig, int $parentRefId): \ilObjSCORMLearningModule {
		$object = new \ilObjSCORMLearningModule();
		$object->setTitle((string) ($targetConfig['title'] ?? $result->title));
		$object->setSubType('scorm');
		$object->setDescription((string) ($targetConfig['description'] ?? $this->buildDescription($result)));
		$object->create(true);
		$object->createReference();
		$object->putInTree($parentRefId);
		$object->setPermissions($parentRefId);
		$object->setOfflineStatus(true);
		$object->createDataDirectory();

		$this->writePackageFiles($result->files, $object->getDataDirectory());
		\ilFileUtils::renameExecutables($object->getDataDirectory());

		$title = $object->readObject();

		if (is_string($title) && trim($title) !== '') {
			\ilObject::_writeTitle($object->getId(), $title);
		}

		$object->setLearningProgressSettingsAtUpload();

		return $object;
	}

	protected function writePackageFiles(array $files, string $targetDirectory): void {
		if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
			throw new \RuntimeException('Could not create SCORM data directory: ' . $targetDirectory);
		}

		foreach ($files as $name => $content) {
			$relativePath = $this->safePackagePath((string) $name);
			$filePath = rtrim($targetDirectory, '/\\') . '/' . $relativePath;
			$dir = dirname($filePath);

			if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
				throw new \RuntimeException('Could not create SCORM package directory: ' . $dir);
			}

			file_put_contents($filePath, (string) $content);
		}
	}

	protected function safePackagePath(string $path): string {
		$path = trim(str_replace('\\', '/', $path));
		$path = preg_replace('#(^|/)\.\.(/|$)#', '/', $path) ?: 'file.bin';
		$path = preg_replace('/[^a-zA-Z0-9._\/-]+/', '-', $path) ?: 'file.bin';
		$path = trim($path, '/');

		return $path !== '' ? $path : 'file.bin';
	}

	protected function buildDescription(ContentForgeExportResult $result): string {
		return 'Generated with BASE3 ContentForge. Export type: ' . $result->type;
	}

	protected function getTargetConfig(ContentForgeExportRequest $request): array {
		$config = $request->config['targetConfig'] ?? [];
		return is_array($config) ? $config : [];
	}

	protected function getObjectUrl(int $refId): string {
		if ($refId > 0 && class_exists(\ilLink::class)) {
			return \ilLink::_getLink($refId, 'sahs');
		}

		return '';
	}

	protected function getRedirectUrl(array $targetConfig, int $refId, int $parentRefId): string {
		if ($refId > 0) {
			return 'ilias.php?baseClass=ilSAHSEditGUI&ref_id=' . $refId;
		}

		$returnUrl = trim((string) ($targetConfig['return_url'] ?? ''));

		if ($returnUrl !== '') {
			return $returnUrl;
		}

		if ($parentRefId > 0 && class_exists(\ilLink::class)) {
			return \ilLink::_getLink($parentRefId);
		}

		return '';
	}

	protected function loadIliasScormClasses(): void {
		$root = $this->getIliasRoot();
		$files = [
			'components/ILIAS/ScormAicc/classes/class.ilObjSAHSLearningModule.php',
			'components/ILIAS/ScormAicc/classes/class.ilObjSCORMLearningModule.php'
		];

		foreach ($files as $file) {
			$path = $root . '/' . $file;
			if (is_file($path)) {
				require_once($path);
			}
		}
	}

	protected function getIliasRoot(): string {
		if (defined('ILIAS_ABSOLUTE_PATH')) {
			return rtrim((string) ILIAS_ABSOLUTE_PATH, '/');
		}

		return rtrim((string) getcwd(), '/');
	}
}
