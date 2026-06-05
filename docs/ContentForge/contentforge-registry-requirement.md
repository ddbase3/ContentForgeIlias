# ContentForge Registry Requirement

Version: 0.1.4

This integration expects ContentForge to resolve export targets through the BASE3 classmap, not only through a hardcoded local target list.

Expected lookup behaviour:

```php
$classmap->getInstanceByInterfaceName(
	ContentForge\Api\IContentForgeExportTarget::class,
	'contentforgeiliasfileexporttarget'
);
```

Equivalent `getInstances()` based lookup is also fine if it searches all BASE3 components and plugins.

This is required because `ContentForgeIliasFileExportTarget` deliberately lives in `Base3IliasLab`, not in the generic ContentForge plugin.

## Symptom if this is missing

The UIHook can render ContentForge correctly and the full generation workflow can complete, but the final result still shows a generic ContentForge download link. That means the widget configuration was accepted, but the export target registry did not resolve `contentforgeiliasfileexporttarget` and the default download target was used.

## Required ContentForge-side patch

`ContentForgeExportTargetRegistry::getTarget($name)` must first check locally registered targets and then ask `IClassMap` for an implementation with the same technical name. This keeps ContentForge host-independent while allowing host-specific targets to live in separate BASE3 components such as `Base3IliasLab`.


The same registry requirement applies to the SCORM target:

```php
$classmap->getInstanceByInterfaceName(
	IContentForgeExportTarget::class,
	'contentforgeiliasscormexporttarget'
);
```
