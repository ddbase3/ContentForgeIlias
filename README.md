# ContentForgeIlias

ContentForgeIlias is a BASE3 integration plugin that extends ContentForge with ILIAS-specific export targets.

The plugin contains classes that are both ContentForge-related and ILIAS-specific. Its purpose is to keep the generic `ContentForge` plugin independent from host-system details while also keeping ILIAS-specific integration code out of unrelated plugins such as `Base3IliasLab`.

## Purpose

ContentForge is a generic human-in-the-loop artifact generation engine. It can generate artifacts such as HTML packages, PDFs, SCORM packages, documents, presentations, checklists, FAQs, and other structured outputs.

ContentForgeIlias provides the ILIAS placement layer for these generated artifacts.

Instead of only returning a download link, ContentForgeIlias can create native ILIAS repository objects from generated ContentForge exports.

## Current Scope

The current integration supports:

- ILIAS file object creation from ContentForge exports
- ILIAS SCORM 1.2 learning module creation from ContentForge SCORM exports

The file export target can place generated single-file exports directly into the ILIAS repository. Multi-file exports are packaged as ZIP files before being stored as ILIAS file objects.

The SCORM export target creates a native ILIAS SCORM learning module from a generated SCORM 1.2 package.

## Repository Role

This repository is intentionally separate from:

- `ContentForge`
- `Base3IliasLab`
- ILIAS UIHook plugins

`ContentForge` remains generic and host-independent.

`Base3IliasLab` should not contain reusable ContentForge/ILIAS bridge classes.

ILIAS UIHook plugins are responsible for embedding ContentForge into ILIAS screens, but they should not contain export target logic.

ContentForgeIlias provides the reusable bridge layer between ContentForge export results and ILIAS repository objects.

## Planned Namespace

```php
namespace ContentForgeIlias;
```

Recommended class locations:

```text
ContentForgeIlias/
├── src/
│   ├── ContentForgeIliasPlugin.php
│   └── ExportTarget/
│       ├── ContentForgeIliasFileExportTarget.php
│       ├── ContentForgeIliasScormExportTarget.php
│       └── ContentForgeIliasReturningFileProcessor.php
├── docs/
│   ├── architecture.md
│   ├── file-export-target.md
│   ├── scorm-export-target.md
│   └── integration-contract.md
├── README.md
├── LICENSE
└── VERSION
```

## Export Targets

### File Export Target

Technical name:

```text
contentforgeiliasfileexporttarget
```

Responsibility:

- receive a `ContentForgeExportResult`
- create an ILIAS ResourceStorage entry
- create a native `ilObjFile`
- insert the file object into the selected ILIAS repository container
- return redirect metadata for the UI integration

General rule:

```text
one exported file  -> native ILIAS file object
multiple files     -> ZIP file object
```

This applies to all single-file exports, not only PDFs. Future formats such as DOCX, PPTX, XLSX, ODT, CSV, or other single-file exports should be stored directly and not wrapped into ZIP files.

### SCORM Export Target

Technical name:

```text
contentforgeiliasscormexporttarget
```

Responsibility:

- receive a `scorm12_package` export result
- create a native `ilObjSCORMLearningModule`
- insert it into the selected ILIAS repository container
- write generated SCORM files into the module data directory
- let ILIAS parse `imsmanifest.xml`
- initialize ILIAS learning-progress settings
- return redirect metadata for the UI integration

## Integration Contract

ContentForge discovers export targets through the BASE3 class map.

Expected lookup:

```php
$classmap->getInstanceByInterfaceName(
	IContentForgeExportTarget::class,
	'contentforgeiliasfileexporttarget'
);
```

and:

```php
$classmap->getInstanceByInterfaceName(
	IContentForgeExportTarget::class,
	'contentforgeiliasscormexporttarget'
);
```

This allows ContentForge export targets to live outside the generic ContentForge plugin while still being discoverable at runtime.

## UI Integration

This repository does not have to provide the ILIAS UIHook itself.

A UIHook plugin can embed the ContentForge widget into ILIAS object creation dialogs and configure ContentForge with `setData()`.

Example for file object creation:

```php
$display->setData([
	'export_template' => '',
	'export_template_locked' => false,
	'export_target' => 'contentforgeiliasfileexporttarget',
	'export_target_config' => [
		'integration' => 'ilias_file_create',
		'parent_ref_id' => $parentRefId,
		'return_url' => $returnUrl,
		'object_type' => 'file'
	]
]);
```

Example for SCORM object creation:

```php
$display->setData([
	'export_template' => 'scorm12',
	'export_template_locked' => true,
	'export_target' => 'contentforgeiliasscormexporttarget',
	'export_target_config' => [
		'integration' => 'ilias_scorm_create',
		'parent_ref_id' => $parentRefId,
		'return_url' => $returnUrl,
		'object_type' => 'sahs',
		'scorm_sub_type' => 'scorm'
	]
]);
```

The UIHook is responsible for display placement.

ContentForgeIlias is responsible for final artifact placement into ILIAS.

## BASE3 Plugin

ContentForgeIlias should provide a minimal BASE3 plugin class:

```php
<?php declare(strict_types=1);

namespace ContentForgeIlias;

use Base3\Api\IContainer;
use Base3\Api\IPlugin;

class ContentForgeIliasPlugin implements IPlugin {

	public function __construct(private readonly IContainer $container) {}

	public static function getName(): string {
		return 'contentforgeiliasplugin';
	}

	public function init() {
		$this->container
			->set(self::getName(), $this, IContainer::SHARED);
	}
}
```

The plugin normally does not need to manually register export targets if the BASE3 class map can discover them by interface and technical name.

## Dependencies

Required:

- BASE3 Framework
- BASE3 ILIAS integration
- ContentForge
- ILIAS 10 or compatible ILIAS version

Runtime assumptions:

- `$GLOBALS['DIC']` contains the ILIAS services
- BASE3 services are available through the same ILIAS-integrated container
- `IClassMap` can discover classes across BASE3 components/plugins
- ContentForge resolves export targets through the class map

## Migration From Base3IliasLab

Move these classes out of `Base3IliasLab`:

```text
Base3IliasLab\ContentForge\ContentForgeIliasFileExportTarget
Base3IliasLab\ContentForge\ContentForgeIliasScormExportTarget
Base3IliasLab\ContentForge\ContentForgeIliasReturningFileProcessor
```

into:

```text
ContentForgeIlias\ExportTarget\ContentForgeIliasFileExportTarget
ContentForgeIlias\ExportTarget\ContentForgeIliasScormExportTarget
ContentForgeIlias\ExportTarget\ContentForgeIliasReturningFileProcessor
```

The technical names should remain stable:

```text
contentforgeiliasfileexporttarget
contentforgeiliasscormexporttarget
```

This avoids breaking existing UIHook configuration and ContentForge export target selection.

## Design Principles

- Keep ContentForge generic.
- Keep ILIAS-specific export logic outside ContentForge.
- Keep reusable ContentForge/ILIAS bridge code outside Base3IliasLab.
- Use BASE3 class map discovery instead of hardcoded factories.
- Preserve stable technical names.
- Use ILIAS APIs for repository object creation.
- Avoid direct database writes for ILIAS objects.
- Keep one PHP class per file.
- Use English code comments.
- Use tab indentation.
- Put opening braces on the same line.

## Roadmap

Planned extensions:

- move existing file and SCORM export targets from Base3IliasLab into this plugin
- add documentation for UIHook integration
- add additional export targets for other ILIAS object types
- evaluate ILIAS HTML learning module integration
- evaluate glossary generation
- evaluate test/question pool generation
- evaluate wiki and survey generation
- add host-specific validation and diagnostics
- add automated checks for required ILIAS classes and services

## License

GPL-3.0, unless stated otherwise by the surrounding BASE3 project setup.
