# ContentForge ILIAS File Export Target

Version: 0.1.4

`Base3IliasLab\ContentForge\ContentForgeIliasFileExportTarget` creates native ILIAS file objects from ContentForge exports.

## Why it lives in Base3IliasLab

The generic ContentForge plugin must stay host-independent. Creating ILIAS repository objects is a host integration task, so the export target belongs to `Base3IliasLab`.

## Files

```text
components/Base3/Base3IliasLab/src/ContentForge/ContentForgeIliasFileExportTarget.php
components/Base3/Base3IliasLab/src/ContentForge/ContentForgeIliasReturningFileProcessor.php
```

The helper processor is intentionally in its own file because the BASE3 classmap/autoloader expects file names to match class names.

## Required target config

```php
[
	'parent_ref_id' => 123,
	'title' => 'Optional title override',
	'description' => 'Optional description override'
]
```

## ResourceStorage flow

The target writes generated content through:

```php
$DIC->resourceStorage()->manage()->stream(
	Streams::ofString($content),
	new ilObjFileStakeholder($DIC->user()->getId()),
	$filename
);
```

Then it uses `ContentForgeIliasReturningFileProcessor`, a small adapter around `ilObjFileAbstractProcessor`, so ILIAS performs normal file object creation in the selected parent container.

## Export handling in file workflow

The file workflow intentionally does not restrict the ContentForge export type.

- PDF exports become `.pdf` file objects.
- Single-file exports become that file object.
- Multi-file exports, HTML packages and SCORM packages become `.zip` file objects.

If the result still appears as a ContentForge download link instead of a new ILIAS file object, ContentForge is not resolving the external target yet. In that case the ContentForge export target registry must look up targets through the BASE3 classmap by target name.


## Redirect behaviour

After the generated ILIAS file object has been created, the target returns the normal parent container URL as the delivered export URL. The actual file object URL is kept in metadata under `file_url`.

The target metadata also contains:

```text
contentForgeAutoRedirect = true
autoRedirect = true
redirect_url = <parent return url>
return_url = <parent return url>
```

The UIHook JavaScript observes ContentForge AJAX responses for this explicit marker and then redirects the browser back to the normal ILIAS page view. This is necessary because the ContentForge flow runs inside AJAX; a server-side redirect header alone would not navigate the full page.
