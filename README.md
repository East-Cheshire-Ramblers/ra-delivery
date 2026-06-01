# ra_delivery

Standalone delivery-integration workspace.

This project is intended to hold a provider-neutral delivery component and supporting console plug-in.

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
