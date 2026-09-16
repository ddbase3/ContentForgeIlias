# ContentForgeIlias Privacy and Data Processing

> This document describes the technical data handling implemented by the ContentForgeIlias component. It is not a legal privacy notice. The final ILIAS installation, ContentForge configuration, infrastructure, backups, and organizational rules determine the complete processing environment.

## Scope

ContentForgeIlias is an integration component that places generated ContentForge export results into ILIAS as native repository objects.

The current implementation supports:

- native ILIAS file objects
- native ILIAS SCORM 1.2 learning modules

The component does not generate the source content itself and does not provide a separate conversation, project, user, analytics, or database subsystem.

## Processing purposes

ContentForgeIlias processes data in order to:

- receive generated ContentForge export files
- package multi-file results when necessary
- create ILIAS ResourceStorage entries
- create ILIAS repository objects
- write SCORM package files into an ILIAS module data directory
- return object IDs and navigation metadata to the invoking integration

## Data processed by the file export target

The file export target can process:

- the generated export file names and full file contents
- export type and export title
- optional target title and description
- target repository `parent_ref_id`
- optional return URL
- current ILIAS user ID
- generated ResourceStorage identifier
- resulting ILIAS object ID and reference ID
- generated file URL and redirect metadata

Generated file content can contain personal or confidential information if such information was present in the source ContentForge artifact.

## Current user ID

When creating a ResourceStorage entry, the file target reads:

```text
$GLOBALS['DIC']->user()->getId()
```

The resulting numeric user ID is used to construct the ILIAS file ResourceStorage stakeholder.

The component does not otherwise read the user's profile, login name, email address, or session data in the current implementation.

## ILIAS ResourceStorage and repository objects

Single-file exports are streamed into ILIAS ResourceStorage and then attached to a native `ilObjFile` object. The object is inserted into the repository container selected by `parent_ref_id`.

For SCORM, the target creates a native `ilObjSCORMLearningModule`, creates its repository reference, inserts it into the selected parent, applies permissions, creates its data directory, and writes the generated package files there.

After creation, storage, access, backup, retention, deletion, and presentation of these objects are governed by ILIAS.

## Multi-file temporary ZIP processing

When a file export contains multiple files and `ZipArchive` is available, ContentForgeIlias creates a temporary ZIP file using the system temporary directory.

The flow is:

1. create a temporary file with `tempnam()`
2. write the generated files into the ZIP archive
3. read the completed ZIP into memory
4. attempt to delete the temporary file immediately

The normal code path removes the temporary file after reading it and also removes it when ZIP creation cannot be opened. A process crash or abrupt termination can still leave an operating-system temporary file behind, so system temporary-directory retention is relevant to deployments handling sensitive exports.

If `ZipArchive` is not available, the current multi-file fallback contains only the generated file-name list rather than the original file bodies.

## SCORM package files

The SCORM target writes each generated package file into the module data directory after normalizing the relative path. It then calls ILIAS executable-file renaming and manifest processing.

The generated files can contain the complete learning content and must therefore be treated with the same confidentiality requirements as the original artifact.

## Object metadata

The target can write title and description values into the created ILIAS object. If no explicit description is supplied, the component creates a technical description identifying the ContentForge export type.

Titles and descriptions can be visible to ILIAS users according to repository permissions. Integrations should not place unnecessary personal data into these metadata fields.

## Return and redirect information

The resulting `ContentForgeDeliveredExport` can contain:

- ILIAS object ID
- ILIAS reference ID
- parent reference ID
- ResourceStorage identifier for file exports
- file or object URL
- configured return URL
- redirect URL
- filename and MIME type
- file count

ContentForgeIlias returns this data to the caller but does not persist a separate copy itself.

The caller may persist the delivered result as part of its own workflow. That storage belongs to the calling component's data-processing scope.

## Authentication and authorization

The current ContentForgeIlias export targets do not contain an explicit user authorization check before creating the requested repository object.

They trust the supplied `parent_ref_id` and invoke ILIAS object-creation APIs. The calling integration must therefore enforce the ILIAS permission boundary before calling an export target.

The fact that a user ID is available through the ILIAS dependency injection container is not, by itself, an authorization decision.

## Permissions of created objects

For SCORM objects, the target explicitly calls the ILIAS permission setup for the selected parent after inserting the object into the tree.

File objects are created through the ILIAS file processor and ResourceStorage APIs. The resulting object's access behavior is then controlled by ILIAS repository permissions.

ContentForgeIlias does not maintain a parallel permission model.

## External data transfers

No direct external HTTP request or third-party API call was identified in the current ContentForgeIlias source code.

The component operates against the local ILIAS runtime, local ResourceStorage, and local filesystem paths. Any external storage, remote filesystem, backup, CDN, or infrastructure behavior configured underneath ILIAS is outside this component's direct implementation.

## Logging

The current ContentForgeIlias component does not implement its own logger calls or log file.

Errors are raised as exceptions. The surrounding PHP, BASE3, web-server, or ILIAS error-handling stack can record exception information according to its own configuration. Operators should therefore include those systems when documenting logging and retention.

## Cookies, sessions, and request bodies

The current export-target classes do not directly read cookies, raw HTTP request bodies, or PHP session variables.

They receive ContentForge export objects and target configuration from the caller and use the ILIAS dependency injection container for ResourceStorage, file-service settings, and the current user ID.

## Retention and deletion

ContentForgeIlias has no independent retention database and no cleanup job.

Once an export is created as an ILIAS object, deletion and retention are handled through the ILIAS object lifecycle. This can include repository deletion, recycle-bin behavior, ResourceStorage cleanup, SCORM data-directory cleanup, and infrastructure backups according to the ILIAS installation.

The component's temporary ZIP file is intended to be short-lived and is deleted on the normal processing path.

## Data minimization guidance

For privacy-sensitive deployments:

- authorize the target repository location before invoking the export target
- avoid unnecessary personal data in generated file names, titles, descriptions, and return URLs
- treat generated file and SCORM content according to the sensitivity of the underlying ContentForge artifact
- protect system temporary directories if multi-file exports can contain confidential data
- apply normal ILIAS permission and deletion rules to the created objects
- include ILIAS backups, ResourceStorage, and error logs in the deployment's retention documentation

## Deployment-specific documentation

The final installation should document at least:

- who may invoke ContentForgeIlias export targets
- which repository locations may be used
- ILIAS object permission behavior after creation
- ResourceStorage location and protection
- SCORM data-directory storage and retention
- temporary-directory handling
- backup and deletion behavior
- logging performed by the surrounding PHP, BASE3, web-server, or ILIAS runtime
