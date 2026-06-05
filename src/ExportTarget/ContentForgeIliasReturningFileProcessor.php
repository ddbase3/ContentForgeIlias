<?php declare(strict_types=1);

namespace ContentForgeIlias\ExportTarget;

use ILIAS\ResourceStorage\Identification\ResourceIdentification;

/**
 * Small adapter around ILIAS' protected ilObjFileAbstractProcessor API.
 */
class ContentForgeIliasReturningFileProcessor extends \ilObjFileAbstractProcessor {

	public function process(ResourceIdentification $rid, string $title = null, string $description = null, string $copyright_id = null): void {
		$this->createFromRid($rid, $this->gui_object->getParentId(), $title, $description, $copyright_id);
	}

	public function createFromRid(ResourceIdentification $rid, int $parentId, string $title = null, string $description = null, string $copyright_id = null): \ilObjFile {
		return $this->createFileObj($rid, $parentId, $title, $description, $copyright_id);
	}
}

