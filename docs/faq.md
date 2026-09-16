# ContentForgeIlias FAQ

## What is ContentForgeIlias?

ContentForgeIlias is a BASE3 integration plugin that provides ILIAS-specific export targets for ContentForge results.

Its responsibility is artifact placement. It takes an already generated ContentForge export and creates a native ILIAS repository object from it.

## Does ContentForgeIlias generate content?

No. Content generation, review, proposal handling, and export creation belong to ContentForge. ContentForgeIlias begins at the delivery boundary, after an exporter has produced a `ContentForgeExportResult`.

## Which ILIAS object types are currently supported?

The current component provides two export targets:

- `contentforgeiliasfileexporttarget` for native ILIAS file objects
- `contentforgeiliasscormexporttarget` for native ILIAS SCORM 1.2 learning modules

## How are the export targets found?

The targets implement the ContentForge export-target contract and are discoverable through the BASE3 class map by their stable technical names.

The plugin class itself only registers the plugin instance. It does not maintain a separate export-target registry.

## What does the file export target do?

The file target:

1. Receives a generated ContentForge export result.
2. Requires a target `parent_ref_id`.
3. Creates an ILIAS ResourceStorage entry.
4. Uses the current ILIAS user ID as the ResourceStorage stakeholder ID.
5. Creates a native ILIAS file object in the selected repository container.
6. Returns ILIAS object identifiers and redirect metadata to the caller.

If the ContentForge export contains one file, that file is stored directly. If it contains multiple files, the target creates a ZIP package first.

## How are multi-file exports packaged?

The file target uses PHP `ZipArchive` when available. It creates a temporary file in the system temporary directory, writes the ZIP package, reads the resulting bytes, and then attempts to delete the temporary file immediately.

Package paths are normalized before being added to the ZIP archive.

## What happens if ZipArchive is unavailable?

For a multi-file file export, the current implementation falls back to a JSON document containing the file-name list. That fallback is then stored as the generated file object's content.

## What does the SCORM export target do?

The SCORM target only accepts `scorm12_package` results that contain `imsmanifest.xml`.

It creates a native ILIAS SCORM learning module, inserts it into the repository tree, applies ILIAS permissions, creates the module data directory, writes the generated package files, lets ILIAS process the manifest, and initializes learning-progress settings.

## Where are SCORM files stored?

The target writes generated package files into the data directory created by the ILIAS SCORM object. Storage location and lifecycle are therefore governed by the ILIAS installation rather than by a separate ContentForgeIlias storage directory.

## Does ContentForgeIlias maintain its own database or JSON storage?

No. The component has no independent persistent storage service. Its durable effect is the creation of ILIAS repository objects and their associated ResourceStorage or SCORM data.

The returned `ContentForgeDeliveredExport` is passed back to the caller and contains object identifiers and redirect metadata, but ContentForgeIlias does not persist that result by itself.

## Does ContentForgeIlias call external services?

No external HTTP API is called by the current ContentForgeIlias code. It interacts with local ILIAS APIs, ILIAS ResourceStorage, the repository tree, and the local filesystem used by ILIAS.

## Which user information does the file target use?

The file target reads the current ILIAS user ID from the ILIAS dependency injection container and uses it when creating the ResourceStorage stakeholder.

It does not copy the complete user profile into the generated file object.

## Does the plugin perform its own permission check before creating objects?

The current export-target classes do not perform an explicit user authorization check before object creation. They receive the target repository `parent_ref_id` from export-target configuration and then call the ILIAS object-creation APIs.

The invoking ILIAS integration must therefore ensure that the current user is authorized to create the requested object in the selected repository container before invoking the target.

## What metadata is returned after delivery?

Depending on the target, the delivered result can include:

- ILIAS reference ID
- ILIAS object ID
- parent reference ID
- filename and MIME type
- number of generated files
- object or file URL
- return URL and redirect URL
- automatic-redirect flags

## How are titles and descriptions handled?

A caller can provide title and description values in the export target configuration. If not provided, the export result title and a generated description are used.

These values become metadata of the created ILIAS object and can therefore be visible wherever ILIAS displays the object.

## How are generated objects deleted?

ContentForgeIlias does not implement a separate deletion lifecycle. Once a file or SCORM module has been created, its retention, deletion, recycle-bin behavior, backups, and permission lifecycle are controlled by ILIAS.

## Does ContentForgeIlias log content?

The current component does not contain its own logger calls or persistent log store. Exceptions can still be recorded by the surrounding BASE3 or ILIAS runtime according to its configured error handling.

## Where is privacy information documented?

See [../PRIVACY.md](../PRIVACY.md).
