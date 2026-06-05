<?php declare(strict_types=1);

namespace ContentForgeIlias\ExportTarget;

use ContentForge\Api\IContentForgeExportTarget;
use ContentForge\Model\ContentForgeDeliveredExport;
use ContentForge\Model\ContentForgeExportRequest;
use ContentForge\Model\ContentForgeExportResult;
use ContentForge\Model\ContentForgeProject;
use ContentForge\Model\ContentForgeWorkflowContext;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;
use ILIAS\ResourceStorage\Stakeholder\ResourceStakeholder;
use ILIAS\ResourceStorage\Services;
use ZipArchive;

/**
 * Places a generated ContentForge export as a native ILIAS file object.
 *
 * This target intentionally lives in Base3IliasLab, not in the generic
 * ContentForge plugin. ContentForge only knows the export-target interface;
 * this class knows ILIAS repository and ResourceStorage details.
 */
class ContentForgeIliasFileExportTarget implements IContentForgeExportTarget {

	public static function getName(): string {
		return 'contentforgeiliasfileexporttarget';
	}

	public function deliver(ContentForgeExportResult $result, ContentForgeWorkflowContext $context, ContentForgeExportRequest $request): ContentForgeDeliveredExport {
		$this->loadIliasFileClasses();

		$targetConfig = $this->getTargetConfig($request);
		$parentRefId = (int) ($targetConfig['parent_ref_id'] ?? 0);

		if ($parentRefId <= 0) {
			throw new \InvalidArgumentException('ContentForge ILIAS file export target requires targetConfig.parent_ref_id.');
		}

		$exportFile = $this->buildExportFile($result);
		$storage = $this->getResourceStorage();
		$stakeholder = new \ilObjFileStakeholder($this->getCurrentUserId());
		$rid = $storage->manage()->stream(
			Streams::ofString($exportFile['content']),
			$stakeholder,
			$exportFile['filename']
		);

		$fileObject = $this->createFileObject(
			$rid,
			$stakeholder,
			$storage,
			$parentRefId,
			(string) ($targetConfig['title'] ?? $result->title),
			(string) ($targetConfig['description'] ?? $this->buildDescription($result))
		);

		$refId = method_exists($fileObject, 'getRefId') ? (int) $fileObject->getRefId() : 0;
		$objId = method_exists($fileObject, 'getId') ? (int) $fileObject->getId() : 0;
		$fileUrl = $refId > 0 && class_exists(\ilLink::class) ? \ilLink::_getLink($refId, 'file') : '';
		$returnUrl = $this->getReturnUrl($targetConfig, $parentRefId);

		return new ContentForgeDeliveredExport(
			ContentForgeProject::newId('delivered'),
			$result->type,
			$rid->serialize(),
			$returnUrl,
			[
				'target' => self::getName(),
				'ilias_ref_id' => $refId,
				'ilias_obj_id' => $objId,
				'parent_ref_id' => $parentRefId,
				'filename' => $exportFile['filename'],
				'mimeType' => $exportFile['mimeType'],
				'fileCount' => count($result->files),
				'file_url' => $fileUrl,
				'return_url' => $returnUrl,
				'redirect_url' => $returnUrl,
				'contentForgeAutoRedirect' => true,
				'autoRedirect' => true
			]
		);
	}

	protected function createFileObject(
		ResourceIdentification $rid,
		ResourceStakeholder $stakeholder,
		Services $storage,
		int $parentRefId,
		string $title,
		string $description
	): \ilObjFile {
		$idType = defined('ilObjFileGUI::REPOSITORY_NODE_ID') ? \ilObjFileGUI::REPOSITORY_NODE_ID : 0;
		$gui = new \ilObjFileGUI(0, $idType, $parentRefId);
		$processor = new ContentForgeIliasReturningFileProcessor(
			$stakeholder,
			$gui,
			$storage,
			$this->getFileServiceSettings()
		);

		return $processor->createFromRid($rid, $parentRefId, $title, $description);
	}

	protected function buildExportFile(ContentForgeExportResult $result): array {
		$files = $result->files;

		if (count($files) === 1) {
			$name = (string) array_key_first($files);
			$content = (string) reset($files);
			$filename = $this->safeFileName($name !== '' ? $name : $this->safeBaseName($result->title) . '.bin');

			return [
				'filename' => $filename,
				'content' => $content,
				'mimeType' => $this->guessMimeType($filename)
			];
		}

		return [
			'filename' => $this->safeBaseName($result->title) . '.zip',
			'content' => $this->zipFiles($files),
			'mimeType' => 'application/zip'
		];
	}

	protected function zipFiles(array $files): string {
		if (!class_exists(ZipArchive::class)) {
			return json_encode(['files' => array_keys($files)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
		}

		$tempFile = tempnam(sys_get_temp_dir(), 'cf_ilias_export_');
		if ($tempFile === false) {
			throw new \RuntimeException('Could not create temporary file for ContentForge ILIAS export.');
		}

		$zip = new ZipArchive();
		if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			@unlink($tempFile);
			throw new \RuntimeException('Could not create ZIP package for ContentForge ILIAS export.');
		}

		foreach ($files as $name => $content) {
			$zip->addFromString($this->safeZipPath((string) $name), (string) $content);
		}

		$zip->close();
		$content = file_get_contents($tempFile);
		@unlink($tempFile);

		if (!is_string($content)) {
			throw new \RuntimeException('Could not read temporary ZIP package for ContentForge ILIAS export.');
		}

		return $content;
	}

	protected function buildDescription(ContentForgeExportResult $result): string {
		return 'Generated with BASE3 ContentForge. Export type: ' . $result->type;
	}

	protected function getTargetConfig(ContentForgeExportRequest $request): array {
		$config = $request->config['targetConfig'] ?? [];
		return is_array($config) ? $config : [];
	}

	protected function getReturnUrl(array $targetConfig, int $parentRefId): string {
		$returnUrl = trim((string) ($targetConfig['return_url'] ?? ''));

		if ($returnUrl !== '') {
			return $returnUrl;
		}

		if ($parentRefId > 0 && class_exists(\ilLink::class)) {
			return \ilLink::_getLink($parentRefId);
		}

		return '';
	}

	protected function getResourceStorage(): Services {
		return $GLOBALS['DIC']->resourceStorage();
	}

	protected function getFileServiceSettings(): \ilFileServicesSettings {
		return $GLOBALS['DIC']->fileServiceSettings();
	}

	protected function getCurrentUserId(): int {
		try {
			return (int) $GLOBALS['DIC']->user()->getId();
		} catch (\Throwable) {
			return 0;
		}
	}

	protected function loadIliasFileClasses(): void {
		$root = $this->getIliasRoot();
		$files = [
			'components/ILIAS/File/classes/Processors/interface.ilObjFileProcessorInterface.php',
			'components/ILIAS/File/classes/Processors/class.ilObjFileAbstractProcessor.php',
			'components/ILIAS/File/classes/class.ilObjFile.php',
			'components/ILIAS/File/classes/class.ilObjFileStakeholder.php',
			'components/ILIAS/File/classes/class.ilObjFileUploadHandlerGUI.php',
			'components/ILIAS/File/classes/class.ilObjFileGUI.php'
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

	protected function safeBaseName(string $title): string {
		$title = trim($title) !== '' ? $title : 'contentforge-export';
		$title = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $title) ?: 'contentforge-export';
		$title = trim($title, '-_');

		return substr($title !== '' ? $title : 'contentforge-export', 0, 90);
	}

	protected function safeFileName(string $filename): string {
		$filename = basename($filename);
		$filename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filename) ?: 'contentforge-export.bin';

		return trim($filename, '-_') ?: 'contentforge-export.bin';
	}

	protected function safeZipPath(string $path): string {
		$path = trim(str_replace('\\', '/', $path));
		$path = preg_replace('#(^|/)\.\.(/|$)#', '/', $path) ?: 'file.bin';
		$path = preg_replace('/[^a-zA-Z0-9._\/-]+/', '-', $path) ?: 'file.bin';
		$path = trim($path, '/');

		return $path !== '' ? $path : 'file.bin';
	}

	protected function guessMimeType(string $filename): string {
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		return match ($extension) {
			'pdf' => 'application/pdf',
			'zip' => 'application/zip',
			'html', 'htm' => 'text/html',
			'txt' => 'text/plain',
			'json' => 'application/json',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'odt' => 'application/vnd.oasis.opendocument.text',
			'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
			'odp' => 'application/vnd.oasis.opendocument.presentation',
			'csv' => 'text/csv',
			'xml' => 'application/xml',
			'md' => 'text/markdown',
			default => 'application/octet-stream'
		};
	}
}
