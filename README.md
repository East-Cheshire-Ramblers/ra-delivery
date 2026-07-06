# ra_delivery

Standalone delivery-integration workspace.

This is a simple package to monitor and report on emails that are sent but could not be delivered. There are two parts:
  1. A batch plug-in that interrogates an SMTP server and logs delivery exceptions to a database table.
  2. An on-line component that allows viewing of the database records, and allows configuration of the plug-in.

The initial version of the plugin is specific to the API provided by SMTP2GO, but it would be straightforward to customise or clone the interface for a different provider.


Current scope:

- Phase 1: poll provider delivery activity and store per-message events locally
- Phase 2: optional provider-based send transport behind a stable local interface

Folder structure:

- `com_ra_delivery` Joomla component
- `plg_ra_delivery` Joomla console plug-in

Initial phase 1 design points:

- Poll SMTP2GO `/activity/search`
- Filter by configured event types
- Persist extract watermark in `#__ra_control` using `record_type = 2`
- Store raw and normalised event rows locally
- Run polling from a Joomla CLI command
